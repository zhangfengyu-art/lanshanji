<?php

namespace App\Admin\Controllers;

use App\Models\Category;
use App\Models\ProcurementReferenceGallery;
use Encore\Admin\Controllers\ModelForm;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcurementReferenceItemsController extends Controller
{
    use ModelForm;

    const IMAGE_MAX_WIDTH = 1280;
    const IMAGE_MAX_HEIGHT = 1280;

    public function index()
    {
        return Admin::content(function (Content $content) {
            $content->header('代购素材库');
            $content->description('维护影子订单与代购内容生成所使用的参考商品素材');
            $content->body($this->grid());
        });
    }

    public function create()
    {
        return Admin::content(function (Content $content) {
            $content->header('新增素材');
            $content->body($this->form());
        });
    }

    public function edit($id)
    {
        return Admin::content(function (Content $content) use ($id) {
            $content->header('编辑素材');
            $content->body($this->form()->edit($id));
        });
    }

    public function importForm()
    {
        return Admin::content(function (Content $content) {
            $content->header('批量导入素材');
            $content->description('支持 CSV / XLSX，字段建议包含 item_name、reference_price、category_id、image_url、weight_estimate');
            $content->body($this->renderImportForm());
        });
    }

    public function importStore(Request $request)
    {
        $this->validate($request, [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx'],
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();
        $extension = strtolower((string) $file->getClientOriginalExtension());

        $rows = $this->parseImportRows($path, $extension);
        if (empty($rows)) {
            admin_toastr('未解析到有效数据行', 'warning');
            return redirect()->back();
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, &$created, &$updated) {
            foreach ($rows as $row) {
                $itemName = trim((string) data_get($row, 'item_name', ''));
                if ($itemName === '') {
                    continue;
                }

                $payload = [
                    'reference_price' => (float) data_get($row, 'reference_price', 0),
                    'category_id' => $this->normalizeCategoryId(data_get($row, 'category_id')),
                    'image_url' => trim((string) data_get($row, 'image_url', '')),
                    'weight_estimate' => data_get($row, 'weight_estimate', null) !== null && data_get($row, 'weight_estimate', '') !== ''
                        ? (float) data_get($row, 'weight_estimate')
                        : null,
                ];

                $record = ProcurementReferenceGallery::query()
                    ->where('item_name', $itemName)
                    ->where(function ($query) use ($payload) {
                        $query->where('category_id', $payload['category_id'])
                            ->orWhereNull('category_id');
                    })
                    ->first();

                if ($record) {
                    $record->fill(array_merge(['item_name' => $itemName], $payload));
                    $record->save();
                    $updated++;
                } else {
                    ProcurementReferenceGallery::query()->create(array_merge(['item_name' => $itemName], $payload));
                    $created++;
                }
            }
        });

        admin_toastr('导入完成：新增 ' . $created . ' 条，更新 ' . $updated . ' 条', 'success');

        return redirect()->route('admin.procurement_reference_items.index');
    }

    protected function grid()
    {
        return Admin::grid(ProcurementReferenceGallery::class, function (Grid $grid) {
            $grid->model()->orderBy('updated_at', 'desc');
            $grid->model()->with('category');

            $grid->id('ID')->sortable();
            $grid->item_name('商品名称');
            $grid->category_id('所属分类')->display(function ($value) {
                return data_get($this->category, 'name', '-');
            });
            $grid->reference_price('参考单价')->display(function ($value) {
                return 'JPY ¥' . number_format((float) $value, 2, '.', '');
            })->sortable();
            $grid->weight_estimate('预估重量')->display(function ($value) {
                return $value === null || $value === '' ? '-' : number_format((float) $value, 2, '.', '') . ' kg';
            })->sortable();
            $grid->image_url('图片')->image('', 56, 56);
            $grid->updated_at('更新时间')->sortable();

            $grid->filter(function (Grid\Filter $filter) {
                $filter->disableIdFilter();
                $filter->like('item_name', '商品名称');
                $filter->equal('category_id', '所属分类')->select($this->categoryOptions());
                $filter->between('reference_price', '参考单价');
                $filter->between('updated_at', '更新时间')->datetime();
            });

            $grid->actions(function ($actions) {
                $actions->disableView();
            });

            $grid->tools(function ($tools) {
                $tools->append('<a href="' . admin_url('procurement-reference-items/import') . '" class="btn btn-sm btn-success" style="margin-right:8px;">批量导入</a>');
                $tools->batch(function ($batch) {
                    $batch->disableDelete();
                });
            });
        });
    }

    protected function form()
    {
        return Admin::form(ProcurementReferenceGallery::class, function (Form $form) {
            $form->display('id', 'ID');
            $form->text('item_name', '商品名称')->rules('required|string|max:255');
            $form->select('category_id', '所属分类')->options($this->categoryOptions())->rules('nullable|integer');
            $form->currency('reference_price', '参考单价')->symbol('JPY ¥')->rules('required|numeric|min:0.01');
            $form->decimal('weight_estimate', '预估重量')->rules('nullable|numeric|min:0');
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

    protected function renderImportForm()
    {
        $action = admin_url('procurement-reference-items/import');
        $token = csrf_token();

        return <<<HTML
<div class="box box-primary">
  <div class="box-body" style="padding:24px;">
    <form action="{$action}" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="_token" value="{$token}">
      <div class="form-group">
        <label>导入文件</label>
        <input type="file" name="file" class="form-control" accept=".csv,.xlsx,.txt" required>
        <p class="help-block">支持 CSV / XLSX。表头建议：item_name, reference_price, category_id, image_url, weight_estimate</p>
      </div>
      <button type="submit" class="btn btn-primary">开始导入</button>
      <a href="{$this->gridUrl()}" class="btn btn-default" style="margin-left:8px;">返回列表</a>
    </form>
  </div>
</div>
HTML;
    }

    protected function gridUrl()
    {
        return admin_url('procurement-reference-items');
    }

    protected function parseImportRows($path, $extension)
    {
        if ($extension === 'csv' || $extension === 'txt') {
            return $this->parseCsvRows($path);
        }

        if ($extension === 'xlsx') {
            return $this->parseXlsxRows($path);
        }

        return [];
    }

    protected function parseCsvRows($path)
    {
        $rows = [];
        if (!is_file($path)) {
            return $rows;
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return $rows;
        }

        $header = null;
        while (($line = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map(function ($value) {
                    return strtolower(trim((string) $value));
                }, $line);
                continue;
            }

            if (count(array_filter($line, function ($value) {
                return trim((string) $value) !== '';
            })) === 0) {
                continue;
            }

            $rows[] = $this->combineHeaderRow($header, $line);
        }

        fclose($handle);

        return $rows;
    }

    protected function parseXlsxRows($path)
    {
        if (!class_exists('ZipArchive') || !is_file($path)) {
            return [];
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $sharedDoc = @simplexml_load_string($sharedXml);
            if ($sharedDoc && isset($sharedDoc->si)) {
                foreach ($sharedDoc->si as $si) {
                    $text = '';
                    if (isset($si->t)) {
                        $text = (string) $si->t;
                    } elseif (isset($si->r)) {
                        foreach ($si->r as $run) {
                            $text .= (string) $run->t;
                        }
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $sheetDoc = @simplexml_load_string($sheetXml);
        if (!$sheetDoc || !isset($sheetDoc->sheetData->row)) {
            return [];
        }

        $rows = [];
        $header = null;

        foreach ($sheetDoc->sheetData->row as $rowNode) {
            $row = $this->extractXlsxRow($rowNode, $sharedStrings);
            if ($header === null) {
                $header = array_map(function ($value) {
                    return strtolower(trim((string) $value));
                }, $row);
                continue;
            }

            if (count(array_filter($row, function ($value) {
                return trim((string) $value) !== '';
            })) === 0) {
                continue;
            }

            $rows[] = $this->combineHeaderRow($header, $row);
        }

        return $rows;
    }

    protected function extractXlsxRow(\SimpleXMLElement $rowNode, array $sharedStrings)
    {
        $values = [];
        $cells = [];

        foreach ($rowNode->c as $cell) {
            $ref = (string) $cell['r'];
            $columnIndex = $this->xlsxColumnIndex($ref);
            $value = '';

            if ((string) $cell['t'] === 's') {
                $sharedIndex = (int) $cell->v;
                $value = isset($sharedStrings[$sharedIndex]) ? $sharedStrings[$sharedIndex] : '';
            } elseif ((string) $cell['t'] === 'inlineStr') {
                $value = isset($cell->is->t) ? (string) $cell->is->t : '';
            } else {
                $value = isset($cell->v) ? (string) $cell->v : '';
            }

            $cells[$columnIndex] = $value;
        }

        if (!empty($cells)) {
            $maxIndex = max(array_keys($cells));
            for ($index = 0; $index <= $maxIndex; $index++) {
                $values[] = array_key_exists($index, $cells) ? $cells[$index] : '';
            }
        }

        return $values;
    }

    protected function xlsxColumnIndex($cellReference)
    {
        preg_match('/^[A-Z]+/i', (string) $cellReference, $matches);
        $letters = strtoupper((string) data_get($matches, 0, ''));
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return max(0, $index - 1);
    }

    protected function combineHeaderRow(array $header, array $row)
    {
        $data = [];

        foreach ($header as $index => $field) {
            if ($field === '') {
                continue;
            }

            $data[$field] = isset($row[$index]) ? trim((string) $row[$index]) : '';
        }

        return $data;
    }

    protected function normalizeCategoryId($value)
    {
        $value = trim((string) $value);
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }

    protected function categoryOptions()
    {
        return Category::query()
            ->orderBy('site_mode')
            ->orderBy('parent_id')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function (Category $category) {
                $label = sprintf('[%s] %s', $category->site_mode, $category->name);

                return [$category->id => $label];
            })
            ->all();
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
