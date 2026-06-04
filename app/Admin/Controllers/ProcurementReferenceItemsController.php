<?php

namespace App\Admin\Controllers;

use App\Models\ProcurementReferenceItem;
use Encore\Admin\Controllers\ModelForm;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;

class ProcurementReferenceItemsController extends Controller
{
    use ModelForm;

    const IMAGE_MAX_WIDTH = 1280;
    const IMAGE_MAX_HEIGHT = 1280;

    public function index()
    {
        return Admin::content(function (Content $content) {
            $content->header('参考商品库');
            $content->description('维护代购需求匹配所使用的标准化商品资料');
            $content->body($this->grid());
        });
    }

    public function create()
    {
        return Admin::content(function (Content $content) {
            $content->header('新增参考商品');
            $content->body($this->form());
        });
    }

    public function edit($id)
    {
        return Admin::content(function (Content $content) use ($id) {
            $content->header('编辑参考商品');
            $content->body($this->form()->edit($id));
        });
    }

    protected function grid()
    {
        return Admin::grid(ProcurementReferenceItem::class, function (Grid $grid) {
            $grid->model()->orderBy('updated_at', 'desc');

            $grid->id('ID')->sortable();
            $grid->name('商品名称');
            $grid->category('分类');
            $grid->reference_price('建议预算')->display(function ($value) {
                return 'JPY ¥' . number_format((float) $value, 2, '.', '');
            })->sortable();
            $grid->image_url('图片')->image('', 56, 56);
            $grid->updated_at('更新时间')->sortable();

            $grid->filter(function (Grid\Filter $filter) {
                $filter->disableIdFilter();
                $filter->like('name', '商品名称');
                $filter->like('category', '分类');
                $filter->between('reference_price', '建议预算');
                $filter->between('updated_at', '更新时间')->datetime();
            });

            $grid->actions(function ($actions) {
                $actions->disableView();
            });

            $grid->tools(function ($tools) {
                $tools->batch(function ($batch) {
                    $batch->disableDelete();
                });
            });
        });
    }

    protected function form()
    {
        return Admin::form(ProcurementReferenceItem::class, function (Form $form) {
            $form->display('id', 'ID');
            $form->text('name', '商品名称')->rules('required|string|max:255');
            $form->text('category', '分类')->rules('required|string|max:64')->help('示例：美妆、零食、数码、潮玩');
            $form->currency('reference_price', '参考预算')->symbol('JPY ¥')->rules('required|numeric|min:0.01');
            $form->image('image_url', '商品图片')
                ->disk('public')
                ->move('references')
                ->uniqueName()
                ->removable()
                ->help('上传目录：storage/app/public/references');

            $form->saving(function (Form $form) {
                $relativePath = ltrim((string) $form->image_url, '/');
                if ($relativePath === '') {
                    return;
                }

                $absolutePath = storage_path('app/public/' . $relativePath);
                $this->resizeAndCompressImage($absolutePath, self::IMAGE_MAX_WIDTH, self::IMAGE_MAX_HEIGHT);
            });

            $form->display('created_at', '创建时间');
            $form->display('updated_at', '更新时间');
        });
    }

    protected function resizeAndCompressImage($absolutePath, $maxWidth, $maxHeight)
    {
        if (!is_file($absolutePath)) {
            return;
        }

        $imageInfo = @getimagesize($absolutePath);
        if (!$imageInfo || empty($imageInfo[0]) || empty($imageInfo[1])) {
            return;
        }

        $sourceType = (int) $imageInfo[2];
        $sourceImage = $this->createImageResource($absolutePath, $sourceType);
        if (!$sourceImage) {
            return;
        }

        $sourceWidth = (int) $imageInfo[0];
        $sourceHeight = (int) $imageInfo[1];
        $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight, 1);
        $targetWidth = max(1, (int) floor($sourceWidth * $ratio));
        $targetHeight = max(1, (int) floor($sourceHeight * $ratio));

        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
        if (in_array($sourceType, [IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
        }

        imagecopyresampled(
            $targetImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        $this->writeImageResource($targetImage, $absolutePath, $sourceType);

        imagedestroy($sourceImage);
        imagedestroy($targetImage);
    }

    protected function createImageResource($absolutePath, $imageType)
    {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return @imagecreatefromjpeg($absolutePath);
            case IMAGETYPE_PNG:
                return @imagecreatefrompng($absolutePath);
            case IMAGETYPE_GIF:
                return @imagecreatefromgif($absolutePath);
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    return @imagecreatefromwebp($absolutePath);
                }
                return null;
            default:
                return null;
        }
    }

    protected function writeImageResource($imageResource, $absolutePath, $imageType)
    {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                @imagejpeg($imageResource, $absolutePath, 82);
                break;
            case IMAGETYPE_PNG:
                @imagepng($imageResource, $absolutePath, 6);
                break;
            case IMAGETYPE_GIF:
                @imagegif($imageResource, $absolutePath);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagewebp')) {
                    @imagewebp($imageResource, $absolutePath, 80);
                }
                break;
        }
    }
}
