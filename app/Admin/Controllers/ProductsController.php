<?php

namespace App\Admin\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;
use Illuminate\Support\Facades\Schema;

class ProductsController extends Controller
{
    use ModelForm;

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index()
    {
        return Admin::content(function (Content $content) {
            $categoryMode = request('category_mode', 'aggregate');
            $modeLabel = $categoryMode === 'direct' ? '直属' : '聚合';

            $content->header('商品列表');
            $content->row(view('admin.products._category_mode_badge', [
                'modeLabel' => $modeLabel,
                'categoryId' => request('category_id'),
            ]));
            $content->body($this->grid());
        });
    }

    /**
     * Edit interface.
     *
     * @param $id
     * @return Content
     */
    public function edit($id)
    {
        return Admin::content(function (Content $content) use ($id) {
            $content->header('编辑商品');
            $content->body($this->form()->edit($id));
        });
    }

    /**
     * Create interface.
     *
     * @return Content
     */
    public function create()
    {
        return Admin::content(function (Content $content) {
            $content->header('创建商品');
            $content->body($this->form());
        });
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Admin::grid(Product::class, function (Grid $grid) {
            $grid->model()->with(['category', 'skus']);
            $categoryMode = request('category_mode', 'aggregate');

            if ($categoryId = request('category_id')) {
                $category = Category::query()->find((int) $categoryId);
                if ($category) {
                    if ($categoryMode === 'direct') {
                        $grid->model()->where('category_id', (int) $categoryId);
                    } else {
                        $grid->model()->whereIn('category_id', $category->selfAndDescendantIds());
                    }
                } else {
                    $grid->model()->where('category_id', (int) $categoryId);
                }
            }

            $grid->id('ID')->sortable();
            $grid->title('商品名称');
            $grid->column('category.name', '所属分类')->display(function ($value) {
                return $value ?: '-';
            });
            $grid->on_sale('已上架')->display(function ($value) {
                return $value ? '是' : '否';
            });
            $grid->price('价格');
            $grid->column('sku_logistics', 'SKU物流数据')->display(function () {
                $skus = collect($this->skus ?: []);
                if ($skus->isEmpty()) {
                    return '<span class="label label-default">无 SKU</span>';
                }

                return $skus->map(function ($sku) {
                    $title = e((string) data_get($sku, 'title', 'SKU'));
                    $itemType = (string) data_get($sku, 'item_type', '');
                    if ($itemType === 'cigarette') {
                        return '<div><strong>'.$title.'</strong>：香烟 / '.(int) data_get($sku, 'unit_sticks', 0).' 支</div>';
                    }
                    if ($itemType === 'tobacco_silk') {
                        return '<div><strong>'.$title.'</strong>：烟丝 / '.(int) data_get($sku, 'unit_weight', 0).' g</div>';
                    }
                    return '<div><strong>'.$title.'</strong>：<span style="color:#d9534f;">未录入</span></div>';
                })->implode('');
            });
            $grid->column('sku_logistics_missing', '缺失数')->display(function () {
                $skus = collect($this->skus ?: []);
                if ($skus->isEmpty()) {
                    return '<span class="label label-default">-</span>';
                }
                $missing = $skus->filter(function ($sku) {
                    $itemType = (string) data_get($sku, 'item_type', '');
                    if ($itemType === 'cigarette') {
                        return (int) data_get($sku, 'unit_sticks', 0) <= 0;
                    }
                    if ($itemType === 'tobacco_silk') {
                        return (int) data_get($sku, 'unit_weight', 0) <= 0;
                    }
                    return true;
                })->count();

                if ($missing > 0) {
                    return '<span class="label label-danger">'.$missing.' 个待补</span>';
                }
                return '<span class="label label-success">已完整</span>';
            });
            $grid->column('sku_logistics_quick', '快速录入')->display(function () {
                $skus = collect($this->skus ?: []);
                if ($skus->isEmpty()) {
                    return '-';
                }

                $quickUrl = admin_url('products/'.$this->id.'/quick-logistics');
                return $skus->map(function ($sku) use ($quickUrl) {
                    $skuId = (int) data_get($sku, 'id');
                    $type = (string) data_get($sku, 'item_type', '');
                    $sticks = (int) data_get($sku, 'unit_sticks', 0);
                    $weight = (int) data_get($sku, 'unit_weight', 0);
                    $title = e((string) data_get($sku, 'title', 'SKU'));
                    $sticksDisabled = $type === 'cigarette' ? '' : 'disabled';
                    $weightDisabled = $type === 'tobacco_silk' ? '' : 'disabled';

                    $html = '<div class="quick-sku-logistics-row" style="display:flex;gap:6px;align-items:center;margin-bottom:6px;">';
                    $html .= '<span style="min-width:90px;max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'.$title.'">'.$title.'</span>';
                    $html .= '<select class="form-control input-sm quick-sku-item-type" style="width:95px;" data-sku-id="'.$skuId.'">';
                    $html .= '<option value="">未选</option>';
                    $html .= '<option value="cigarette"'.($type === 'cigarette' ? ' selected' : '').'>香烟</option>';
                    $html .= '<option value="tobacco_silk"'.($type === 'tobacco_silk' ? ' selected' : '').'>烟丝</option>';
                    $html .= '</select>';
                    $html .= '<input type="number" min="0" class="form-control input-sm quick-sku-unit-sticks" style="width:78px;" placeholder="支数" value="'.$sticks.'" '.$sticksDisabled.'>';
                    $html .= '<input type="number" min="0" class="form-control input-sm quick-sku-unit-weight" style="width:86px;" placeholder="克重g" value="'.$weight.'" '.$weightDisabled.'>';
                    $html .= '<button type="button" class="btn btn-xs btn-primary btn-quick-sku-logistics-save" data-url="'.$quickUrl.'" data-sku-id="'.$skuId.'">保存</button>';
                    $html .= '</div>';

                    return $html;
                })->implode('');
            });
            $grid->column('dispatch_quick', '库存调整')->display(function () {
                $skus = collect($this->skus ?: []);
                $totalStock = (int) $skus->sum('stock');
                $quickUrl = admin_url('products/'.$this->id.'/quick-dispatch');

                return '<div class="dispatch-quick-editor" style="min-width:280px;display:flex;gap:6px;align-items:center;">'
                    .'<span style="font-size:11px;color:#888;">当前: <strong>'.$totalStock.'</strong></span>'
                    .'<input type="number" class="form-control input-sm quick-stock-delta" style="width:80px;" value="" placeholder="增量(正增负减)">'
                    .'<button type="button" class="btn btn-xs btn-primary btn-quick-stock-add" data-url="'.$quickUrl.'">添加库存</button>'
                    .'<span style="font-size:10px;color:#999;margin-left:6px;">(正数增加,负数扣减)</span>'
                    .'</div>';
            });
            $grid->rating('评分');
            $grid->sold_count('销量');
            $grid->review_count('评论数');

            $grid->filter(function ($filter) {
                $filter->disableIdFilter();
                $filter->equal('category_id', '所属分类')->select(
                    $this->categoryOptions()
                );
            });

            $grid->actions(function ($actions) {
                $actions->disableView();
            });
            $grid->tools(function ($tools) {
                $tools->append(view('admin.products._batch_set_category', [
                    'categories' => $this->categoryOptions(),
                ]));
            });

            Admin::script(<<<'JS'
$(document).off('click', '.btn-quick-stock-add').on('click', '.btn-quick-stock-add', function () {
    var $btn = $(this);
    var $editor = $btn.closest('.dispatch-quick-editor');
    var delta = parseInt($editor.find('.quick-stock-delta').val(), 10);

    if (isNaN(delta)) {
        alert('Please enter a valid number. Positive and negative values are supported.');
        return;
    }

    var url = $btn.data('url');
    $.post(url, {
        _token: LA.token,
        stock_delta: delta
    }, function (ret) {
        if (ret.status) {
            $.pjax.reload('#pjax-container');
            return;
        }
        alert(ret.message || '更新失败');
    }).fail(function () {
        alert('请求失败，请稍后重试');
    });
});

$(document).off('change', '.quick-sku-item-type').on('change', '.quick-sku-item-type', function () {
    var $row = $(this).closest('.quick-sku-logistics-row');
    var type = $(this).val();
    var $sticks = $row.find('.quick-sku-unit-sticks');
    var $weight = $row.find('.quick-sku-unit-weight');

    $sticks.prop('disabled', type !== 'cigarette');
    $weight.prop('disabled', type !== 'tobacco_silk');
});

$(document).off('click', '.btn-quick-sku-logistics-save').on('click', '.btn-quick-sku-logistics-save', function () {
    var $btn = $(this);
    var $row = $btn.closest('.quick-sku-logistics-row');
    var url = $btn.data('url');
    var payload = {
        _token: LA.token,
        sku_id: $btn.data('sku-id'),
        item_type: $row.find('.quick-sku-item-type').val(),
        unit_sticks: parseInt($row.find('.quick-sku-unit-sticks').val(), 10) || 0,
        unit_weight: parseInt($row.find('.quick-sku-unit-weight').val(), 10) || 0
    };

    $.post(url, payload, function (ret) {
        if (ret.status) {
            $.pjax.reload('#pjax-container');
            return;
        }
        alert(ret.message || '保存失败');
    }).fail(function () {
        alert('请求失败，请稍后重试');
    });
});

$(document).off('click', '.btn-batch-set-category').on('click', '.btn-batch-set-category', function () {
    var categoryId = $('.batch-category-select').val();
    var ids = [];
    $('.grid-row-checkbox:checked').each(function () {
        ids.push($(this).val());
    });

    if (!ids.length) {
        alert('Please select at least one product to update.');
        return;
    }

    if (!categoryId) {
        alert('Please choose a target category.');
        return;
    }

    $.post('/admin/products/batch-set-category', {
        _token: LA.token,
        ids: ids,
        category_id: categoryId
    }, function (ret) {
        if (ret.status) {
            $.pjax.reload('#pjax-container');
            return;
        }
        alert(ret.message || 'Batch update failed');
    });
});
JS
            );
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        // 创建一个表单
        $controller = $this;

        return Admin::form(Product::class, function (Form $form) use ($controller) {
            $form->tools(function (Form\Tools $tools) {
                $tools->disableView();
                $tools->disableDelete();
            });

            $siteModeForProduct = $controller->resolveProductSiteMode($form->model());

            $form->hidden('image_original');
            $form->hidden('image_crop_meta');
            $form->hidden('image_crop_payload');
            $form->ignore(['image_crop_payload', 'skus']);

            // 创建一个输入框，第一个参数 title 是模型的字段名，第二个参数是该字段描述
            $form->text('title', '商品名称')->rules('required');
            $form->select('category_id', '所属分类')
                ->options($this->categoryOptions($siteModeForProduct))
                ->help('请为商品指定一个分类，前台导航筛选依赖此字段');
            // 创建一个选择图片的框
            $form->image('image', '封面图片')->rules('required|image')->help('上传后会弹出裁切器，支持缩放与拖拽，保存后前台使用裁切结果图。');
            // 创建一个富文本编辑器
            $form->editor('description', '商品描述')->rules('required');
            // 创建一组单选框
            $form->radio('on_sale', '上架')->options(['1' => '是', '0' => '否'])->default('0');
            // 直接添加一对多的关联模型
            $form->hasMany('skus', function (Form\NestedForm $form) {
                $form->text('title', 'SKU 名称')->rules('required');
                $form->text('description', 'SKU 描述')->rules('required');
                $form->text('price', '单价')->rules('required|numeric|min:0.01');
                $form->text('stock', '剩余库存')->rules('required|integer|min:0');
                $form->select('item_type', '物流品类')->options([
                    'cigarette' => '香烟',
                    'tobacco_silk' => '烟丝',
                ])->default(null)->help('选择后将联动显示对应录入字段');
                $form->number('unit_sticks', '每包支数')->min(0)->default(0)->help('仅香烟有效，例如 20');
                $form->number('unit_weight', '每包克重(g)')->min(0)->default(0)->help('仅烟丝有效，例如 50');
            });
            Admin::script(<<<'JS'
(function () {
    function markHasManyButtonsNoPjax() {
        $('.has-many-skus .add, .has-many-skus .remove').attr('data-no-pjax', '1');
    }

    function updateSkuLogisticsFields($scope) {
        var $type = $scope.find('select[name$="[item_type]"]');
        if (!$type.length) {
            return;
        }

        var value = $type.val() || '';
        var $sticks = $scope.find('.field_unit_sticks');
        var $weight = $scope.find('.field_unit_weight');

        $sticks.hide();
        $weight.hide();

        if (value === 'cigarette') {
            $sticks.show();
            return;
        }
        if (value === 'tobacco_silk') {
            $weight.show();
        }
    }

    function syncAllSkuRows() {
        $('.has-many-skus-form').each(function () {
            updateSkuLogisticsFields($(this));
        });
    }

    $(document).off('change.sku-logistics-link', 'select[name$="[item_type]"]')
        .on('change.sku-logistics-link', 'select[name$="[item_type]"]', function () {
            updateSkuLogisticsFields($(this).closest('.has-many-skus-form'));
        });

    $(document).off('click.sku-logistics-add', '.has-many-skus .add')
        .on('click.sku-logistics-add', '.has-many-skus .add', function () {
            setTimeout(function () {
                syncAllSkuRows();
                markHasManyButtonsNoPjax();
            }, 50);
        });

    $(document).off('click.sku-logistics-remove', '.has-many-skus .remove')
        .on('click.sku-logistics-remove', '.has-many-skus .remove', function () {
            setTimeout(function () {
                syncAllSkuRows();
                markHasManyButtonsNoPjax();
            }, 50);
        });

    $(function () {
        syncAllSkuRows();
        markHasManyButtonsNoPjax();
    });
})();
JS
            );
            Admin::script(<<<'JS'
(function () {
    var panelId = '#product-image-live-preview';
    var modalId = '#product-image-crop-modal';
    var fileInputSelector = '.field_image input[type="file"], input[type="file"][name="image"], input[type="file"][name$="[image]"]';
    var cropper = null;
    var currentAspectRatio = NaN;

    function applyDefaultCropBox() {
        if (!cropper) {
            return;
        }

        var containerData = cropper.getContainerData();
        var imageData = cropper.getImageData();
        if (!containerData || !imageData || !imageData.width || !imageData.height) {
            return;
        }

        if (Number.isFinite(currentAspectRatio) && currentAspectRatio > 0) {
            var side = Math.min(imageData.width, imageData.height) * 0.9;
            cropper.setCropBoxData({
                width: side,
                height: side,
                left: imageData.left + (imageData.width - side) / 2,
                top: imageData.top + (imageData.height - side) / 2,
            });
            return;
        }

        // Keep the crop box close to the visible image area for freeform crops.
        var targetWidth = Math.max(120, imageData.width * 0.92);
        var targetHeight = Math.max(160, imageData.height * 0.92);
        targetWidth = Math.min(targetWidth, containerData.width * 0.95);
        targetHeight = Math.min(targetHeight, containerData.height * 0.95);

        cropper.setCropBoxData({
            width: targetWidth,
            height: targetHeight,
            left: imageData.left + (imageData.width - targetWidth) / 2,
            top: imageData.top + (imageData.height - targetHeight) / 2,
        });
    }

            function getPayloadInput() {
        return $('input[name="image_crop_payload"]');
    }

            function getMetaInput() {
                return $('input[name="image_crop_meta"]');
            }

    function findExistingImageSource() {
        var selectors = [
            '.field_image .file-preview img',
            '.field_image .thumbnail img',
            '.file-preview img',
            '.thumbnail img'
        ];

        for (var i = 0; i < selectors.length; i++) {
            var src = $(selectors[i]).first().attr('src');
            if (src) {
                return src;
            }
        }

        var value = $('input[name="image"][type="hidden"]').val();
        if (!value) {
            return '';
        }

        if (/^(https?:)?\/\//i.test(value) || value.indexOf('/') === 0) {
            return value;
        }

        return '/' + value.replace(/^\/+/, '');
    }

    function ensurePreviewPanel() {
        if ($(panelId).length) {
            return;
        }

        var panelHtml = ''
            + '<div id="product-image-live-preview" class="box box-default" style="margin-top:8px;">'
            + '  <div class="box-header with-border"><h3 class="box-title">图片即时预览</h3></div>'
            + '  <div class="box-body" style="display:flex;gap:24px;flex-wrap:wrap;">'
            + '    <div>'
            + '      <div style="font-size:12px;color:#777;margin-bottom:8px;">列表卡片（1:1）</div>'
            + '      <div style="position:relative;width:220px;aspect-ratio:1/1;overflow:hidden;background:#ffffff;">'
            + '        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;overflow:hidden;">'
            + '          <img class="js-preview-list" src="" style="display:none;width:100%;height:100%;object-fit:contain;">'
            + '        </div>'
            + '      </div>'
            + '    </div>'
            + '    <div>'
            + '      <div style="font-size:12px;color:#777;margin-bottom:8px;">详情主图（1:1）</div>'
            + '      <div style="position:relative;width:320px;border:1px solid #eeeeee;border-radius:2px;background:#ffffff;overflow:hidden;aspect-ratio:1/1;display:flex;align-items:center;justify-content:center;">'
            + '        <img class="js-preview-detail" src="" style="display:none;max-width:100%;max-height:100%;object-fit:contain;padding:16px;">'
            + '      </div>'
            + '    </div>'
            + '  </div>'
            + '</div>';

        var $formGroup = $(fileInputSelector).first().closest('.form-group');
        if ($formGroup.length) {
            $formGroup.after(panelHtml);
        }
    }

    function updatePreview(src) {
        if (!src) {
            return;
        }
        ensurePreviewPanel();
        $(panelId + ' .js-preview-list').attr('src', src).css('display', 'block');
        $(panelId + ' .js-preview-detail').attr('src', src).css('display', 'block');
    }

    function ensureModal() {
        if ($(modalId).length) {
            return;
        }

        var modalHtml = ''
            + '<div class="modal fade" id="product-image-crop-modal" tabindex="-1" role="dialog">'
            + '  <div class="modal-dialog modal-lg" role="document">'
            + '    <div class="modal-content">'
            + '      <div class="modal-header">'
            + '        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>'
            + '        <h4 class="modal-title">图片裁切与缩放</h4>'
            + '      </div>'
            + '      <div class="modal-body">'
            + '        <p style="color:#888;">默认自由比例裁切，拖拽可移动画面，滚轮可缩放。</p>'
            + '        <div style="margin-bottom:10px;display:flex;gap:8px;flex-wrap:wrap;">'
            + '          <button type="button" class="btn btn-default btn-sm" id="js-crop-ratio-free">自由比例</button>'
            + '          <button type="button" class="btn btn-default btn-sm" id="js-crop-ratio-square">1:1 方图</button>'
            + '          <button type="button" class="btn btn-default btn-sm" id="js-crop-zoom-in">放大</button>'
            + '          <button type="button" class="btn btn-default btn-sm" id="js-crop-zoom-out">缩小</button>'
            + '          <button type="button" class="btn btn-default btn-sm" id="js-crop-reset">重置</button>'
            + '        </div>'
            + '        <div style="display:flex;gap:16px;flex-wrap:wrap;">'
            + '          <div style="flex:1 1 460px;min-width:320px;">'
            + '            <div style="width:100%;height:420px;border:1px solid #e5e5e5;background:#f9f9f9;display:flex;align-items:center;justify-content:center;overflow:hidden;">'
            + '              <img id="product-image-crop-target" src="" style="max-width:100%;">'
            + '            </div>'
            + '          </div>'
            + '          <div style="width:220px;">'
            + '            <div style="font-size:12px;color:#777;margin-bottom:8px;">裁切预览</div>'
            + '            <div class="js-cropper-preview" style="width:200px;height:200px;border:1px solid #e5e5e5;overflow:hidden;background:#fff;"></div>'
            + '          </div>'
            + '        </div>'
            + '      </div>'
            + '      <div class="modal-footer">'
            + '        <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>'
            + '        <button type="button" class="btn btn-primary" id="js-apply-product-image-crop">应用裁切</button>'
            + '      </div>'
            + '    </div>'
            + '  </div>'
            + '</div>';

        $('body').append(modalHtml);

        $(modalId).on('hidden.bs.modal', function () {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            $('#product-image-crop-target').attr('src', '');
        });
    }

    function loadCropperAssets(callback, onError) {
        if (typeof window.Cropper !== 'undefined') {
            callback();
            return;
        }

        if (!document.getElementById('cropper-css-cdn')) {
            var css = document.createElement('link');
            css.id = 'cropper-css-cdn';
            css.rel = 'stylesheet';
            css.href = 'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css';
            document.head.appendChild(css);
        }

        var scriptTag = document.getElementById('cropper-js-cdn');
        if (scriptTag) {
            scriptTag.addEventListener('load', callback);
            return;
        }

        scriptTag = document.createElement('script');
        scriptTag.id = 'cropper-js-cdn';
        scriptTag.src = 'https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.js';
        scriptTag.onload = callback;
        scriptTag.onerror = function () {
            if (typeof onError === 'function') {
                onError();
            }
        };
        document.body.appendChild(scriptTag);

        setTimeout(function () {
            if (typeof window.Cropper === 'undefined' && typeof onError === 'function') {
                onError();
            }
        }, 5000);
    }

    function openCropper(dataUrl) {
        ensureModal();
        var $image = $('#product-image-crop-target');
        $image.attr('src', dataUrl);

        $(modalId).modal('show');

        if (cropper) {
            cropper.destroy();
            cropper = null;
        }

        if (typeof window.Cropper === 'undefined') {
            return;
        }

        currentAspectRatio = NaN;
        cropper = new window.Cropper($image[0], {
            aspectRatio: currentAspectRatio,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 0.95,
            responsive: true,
            background: false,
            preview: '.js-cropper-preview',
            movable: true,
            zoomable: true,
            scalable: false,
            rotatable: true,
            ready: function () {
                applyDefaultCropBox();
            },
        });
    }

    function bindCropControls() {
        $(document).off('click', '#js-crop-ratio-free').on('click', '#js-crop-ratio-free', function () {
            if (!cropper) {
                return;
            }
            currentAspectRatio = NaN;
            cropper.setAspectRatio(NaN);
            applyDefaultCropBox();
        });

        $(document).off('click', '#js-crop-ratio-square').on('click', '#js-crop-ratio-square', function () {
            if (!cropper) {
                return;
            }
            currentAspectRatio = 1;
            cropper.setAspectRatio(1);
            applyDefaultCropBox();
        });

        $(document).off('click', '#js-crop-zoom-in').on('click', '#js-crop-zoom-in', function () {
            if (cropper) {
                cropper.zoom(0.1);
            }
        });

        $(document).off('click', '#js-crop-zoom-out').on('click', '#js-crop-zoom-out', function () {
            if (cropper) {
                cropper.zoom(-0.1);
            }
        });

        $(document).off('click', '#js-crop-reset').on('click', '#js-crop-reset', function () {
            if (cropper) {
                cropper.reset();
            }
        });
    }

    function bindCropApply() {
        $(document).off('click', '#js-apply-product-image-crop').on('click', '#js-apply-product-image-crop', function () {
            if (!cropper) {
                return;
            }

            var canvas = cropper.getCroppedCanvas({
                maxWidth: 1200,
                maxHeight: 1200,
                fillColor: '#ffffff',
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });

            if (!canvas) {
                return;
            }

            var croppedDataUrl = canvas.toDataURL('image/jpeg', 0.86);
            var $fileInput = $(fileInputSelector).first();
            var cropData = cropper.getData(true);
            var meta = {
                x: cropData.x,
                y: cropData.y,
                width: cropData.width,
                height: cropData.height,
                rotate: cropData.rotate,
                scaleX: cropData.scaleX,
                scaleY: cropData.scaleY,
                aspectRatio: Number.isFinite(currentAspectRatio) ? currentAspectRatio : 'free',
                exportWidth: 1200,
                exportHeight: 1200
            };

            // Avoid submitting a huge base64 string into admin_operation_log.
            var $payloadInput = getPayloadInput();
            if ($payloadInput.length) {
                $payloadInput.val('');
            }

            var $metaInput = getMetaInput();
            if ($metaInput.length) {
                $metaInput.val(JSON.stringify(meta));
            }

            if ($fileInput.length && window.DataTransfer && canvas.toBlob) {
                canvas.toBlob(function (blob) {
                    if (!blob) {
                        return;
                    }
                    var file = new File([blob], 'cropped-image.jpg', { type: 'image/jpeg' });
                    var dt = new DataTransfer();
                    dt.items.add(file);
                    $fileInput[0].files = dt.files;
                }, 'image/jpeg', 0.86);
            }

            updatePreview(croppedDataUrl);
            $(modalId).modal('hide');
        });
    }

    function bindFileInput() {
        $(document).off('change', fileInputSelector).on('change', fileInputSelector, function (e) {
            var files = e.target.files || [];
            if (!files.length) {
                return;
            }

            var file = files[0];
            if (!file || !/^image\//i.test(file.type)) {
                return;
            }

            var reader = new FileReader();
            reader.onload = function (evt) {
                var source = evt && evt.target ? evt.target.result : '';
                if (!source) {
                    return;
                }

                // Refresh the preview immediately after file selection.
                updatePreview(source);

                // Open the crop modal right away so the action feels responsive.
                openCropper(source);

                loadCropperAssets(function () {
                    openCropper(source);
                }, function () {
                    if (window.toastr && toastr.warning) {
                        toastr.warning('Cropper failed to load. The original preview is still available.');
                    } else {
                        alert('Cropper failed to load. The original preview is still available.');
                    }
                });
            };
            reader.readAsDataURL(file);
        });

        $(document).off('change.bs.fileinput', '.field_image .fileinput').on('change.bs.fileinput', '.field_image .fileinput', function () {
            setTimeout(function () {
                var source = findExistingImageSource();
                if (source) {
                    updatePreview(source);
                }
            }, 50);
        });
    }

    function renderInitialPreview() {
        ensurePreviewPanel();
        var existing = findExistingImageSource();
        if (existing) {
            updatePreview(existing);
        }
    }

    ensurePreviewPanel();
    bindFileInput();
    bindCropApply();
    bindCropControls();
    renderInitialPreview();
})();
JS
            );

            // 定义事件回调，当模型即将保存时会触发这个回调
            $form->saving(function (Form $form) use ($controller) {
                $uploadedImage = request()->file('image');
                if (is_array($uploadedImage)) {
                    $uploadedImage = reset($uploadedImage);
                }

                if ($uploadedImage instanceof UploadedFile && $uploadedImage->isValid()) {
                    // Persist file explicitly so frontend image path is never lost when form inputs miss file keys.
                    $storedImagePath = $uploadedImage->store('images', 'public');
                    $form->input('image', $storedImagePath);
                    request()->request->set('image', $storedImagePath);

                    $form->image_original = $storedImagePath;
                    request()->request->set('image_original', $storedImagePath);
                }

                $rawSkus = (array) request()->input('skus', []);
                Log::info('admin.products.saving', [
                    'product_id' => $form->model()->id,
                    'sku_rows' => count($rawSkus),
                    'sku_keys' => array_keys($rawSkus),
                ]);

                request()->request->set('image_crop_payload', '[omitted]');

                $metaRaw = trim($controller->normalizeStringValue($form->input('image_crop_meta', '')));
                if ($metaRaw !== '') {
                    $meta = json_decode($metaRaw, true);
                    if (is_array($meta)) {
                        $form->image_crop_meta = json_encode($meta, JSON_UNESCAPED_UNICODE);
                    }
                }

                $imageOriginal = $controller->normalizeStringValue($form->input('image_original', ''));
                if ($imageOriginal === '') {
                    $imageOriginal = $controller->normalizeStringValue($form->input('image', ''));
                }
                if ($imageOriginal === '') {
                    $imageOriginal = $controller->normalizeStringValue($form->model()->image);
                }

                if ($imageOriginal !== '') {
                    $form->image_original = $imageOriginal;
                }

                $form->model()->price = collect($rawSkus)->where(Form::REMOVE_FLAG_NAME, 0)->min('price') ?: 0;
            });

            $form->saved(function (Form $form) use ($controller) {
                $rawSkus = (array) request()->input('skus', []);
                Log::info('admin.products.saved', [
                    'product_id' => $form->model()->id,
                    'sku_rows' => count($rawSkus),
                    'sku_keys' => array_keys($rawSkus),
                ]);
                $controller->syncProductSkus($form->model(), $rawSkus);
            });
        });
    }

    public function batchSetCategory(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []));
        $categoryId = (int) $request->input('category_id');

        if (!$ids) {
            return ['status' => false, 'message' => '请选择商品'];
        }

        $categoryExists = Category::query()->where('id', $categoryId)->exists();
        if (!$categoryExists) {
            return ['status' => false, 'message' => '目标分类不存在'];
        }

        Product::query()->whereIn('id', $ids)->update(['category_id' => $categoryId]);

        return ['status' => true, 'message' => '批量更新成功'];
    }

    public function quickUpdateDispatch(Request $request, $id)
    {
        $stockDelta = (int) $request->input('stock_delta', null);
        $limitQty = (int) $request->input('limit_qty', null);

        $product = Product::query()->with('skus:id,product_id')->find($id);
        if (!$product) {
            return ['status' => false, 'message' => '商品不存在'];
        }

        if ($product->skus->isEmpty()) {
            return ['status' => false, 'message' => '该商品暂无 SKU，无法调整库存'];
        }

        // 处理库存增量
        if ($stockDelta !== null) {
            // 使用 increment 进行原子操作，避免并发竞态条件
            ProductSku::query()->where('product_id', $product->id)->increment('stock', $stockDelta);
        }

        // 处理限量配置（直接设置值，而不是增量）
        if ($limitQty !== null) {
            if ($limitQty < 0) {
                return ['status' => false, 'message' => '限量必须是大于等于 0 的整数'];
            }
            ProductSku::query()->where('product_id', $product->id)->update(['limit_qty' => $limitQty]);
        }

        return ['status' => true, 'message' => '调整成功'];
    }

    public function quickUpdateLogistics(Request $request, $id)
    {
        $product = Product::query()->with('skus:id,product_id')->find($id);
        if (!$product) {
            return ['status' => false, 'message' => '商品不存在'];
        }

        $skuId = (int) $request->input('sku_id', 0);
        if ($skuId <= 0) {
            return ['status' => false, 'message' => 'SKU 参数错误'];
        }

        $sku = ProductSku::query()->where('product_id', $product->id)->find($skuId);
        if (!$sku) {
            return ['status' => false, 'message' => 'SKU 不存在或不属于当前商品'];
        }

        $itemType = (string) $request->input('item_type', '');
        $unitSticks = max(0, (int) $request->input('unit_sticks', 0));
        $unitWeight = max(0, (int) $request->input('unit_weight', 0));

        if ($itemType !== '' && !in_array($itemType, ['cigarette', 'tobacco_silk'], true)) {
            return ['status' => false, 'message' => '物流品类不合法'];
        }

        if ($itemType === 'cigarette') {
            $sku->update([
                'item_type' => 'cigarette',
                'unit_sticks' => $unitSticks,
                'unit_weight' => 0,
            ]);
            return ['status' => true, 'message' => '香烟物流数据已保存'];
        }

        if ($itemType === 'tobacco_silk') {
            $sku->update([
                'item_type' => 'tobacco_silk',
                'unit_sticks' => 0,
                'unit_weight' => $unitWeight,
            ]);
            return ['status' => true, 'message' => '烟丝物流数据已保存'];
        }

        $sku->update([
            'item_type' => null,
            'unit_sticks' => 0,
            'unit_weight' => 0,
        ]);

        return ['status' => true, 'message' => '已清空物流数据'];
    }

    protected function resolveProductSiteMode($product)
    {
        if ($product && Schema::hasColumn('products', 'is_from_native_procurement')) {
            return (bool) $product->is_from_native_procurement ? Category::SITE_MODE_B : Category::SITE_MODE_A;
        }

        $requestSiteMode = strtoupper((string) request('site_mode', site_mode()));

        return $requestSiteMode === Category::SITE_MODE_B ? Category::SITE_MODE_B : Category::SITE_MODE_A;
    }

    protected function normalizeStringValue($value)
    {
        while (is_array($value) || is_object($value)) {
            if (is_array($value)) {
                if (empty($value)) {
                    return '';
                }

                $value = reset($value);
                continue;
            }

            $value = (array) $value;
            if (empty($value)) {
                return '';
            }

            $value = reset($value);
        }

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (!is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    protected function syncProductSkus(Product $product, array $skusData)
    {
        $hasItemTypeColumn = Schema::hasColumn('product_skus', 'item_type');
        $hasUnitSticksColumn = Schema::hasColumn('product_skus', 'unit_sticks');
        $hasUnitWeightColumn = Schema::hasColumn('product_skus', 'unit_weight');

        Log::info('admin.products.sync_skus.start', [
            'product_id' => $product->id,
            'sku_rows' => count($skusData),
            'sku_keys' => array_keys($skusData),
        ]);

        $submittedIds = [];

        foreach ($skusData as $row) {
            if (!is_array($row)) {
                continue;
            }

            if ((int) data_get($row, Form::REMOVE_FLAG_NAME, 0) === 1) {
                continue;
            }

            $payload = [
                'title' => $this->normalizeStringValue(data_get($row, 'title', '')),
                'description' => $this->normalizeStringValue(data_get($row, 'description', '')),
                'price' => $this->normalizeStringValue(data_get($row, 'price', '')),
                'stock' => max(0, (int) data_get($row, 'stock', 0)),
                'limit_qty' => max(0, (int) data_get($row, 'limit_qty', 0)),
            ];

            if ($hasItemTypeColumn) {
                $itemType = $this->normalizeStringValue(data_get($row, 'item_type', ''));
                $payload['item_type'] = $itemType === '' ? null : $itemType;
            }

            if ($hasUnitSticksColumn) {
                $payload['unit_sticks'] = max(0, (int) data_get($row, 'unit_sticks', 0));
            }

            if ($hasUnitWeightColumn) {
                $payload['unit_weight'] = max(0, (int) data_get($row, 'unit_weight', 0));
            }

            $hasMeaningfulValue = $payload['title'] !== ''
                || $payload['description'] !== ''
                || $payload['price'] !== ''
                || $payload['stock'] > 0
                || $payload['limit_qty'] > 0;

            if ($hasItemTypeColumn && array_key_exists('item_type', $payload)) {
                $hasMeaningfulValue = $hasMeaningfulValue || $payload['item_type'] !== null;
            }

            if ($hasUnitSticksColumn && array_key_exists('unit_sticks', $payload)) {
                $hasMeaningfulValue = $hasMeaningfulValue || $payload['unit_sticks'] > 0;
            }

            if ($hasUnitWeightColumn && array_key_exists('unit_weight', $payload)) {
                $hasMeaningfulValue = $hasMeaningfulValue || $payload['unit_weight'] > 0;
            }

            if (!$hasMeaningfulValue) {
                continue;
            }

            $skuId = (int) data_get($row, 'id', 0);

            if ($skuId > 0) {
                $sku = $product->skus()->whereKey($skuId)->first();
                if ($sku) {
                    $sku->fill($payload);
                    $sku->save();
                    $submittedIds[] = $sku->id;
                    continue;
                }
            }

            $created = $product->skus()->create($payload);
            $submittedIds[] = $created->id;
        }

        if (!empty($submittedIds)) {
            $product->skus()->whereNotIn('id', $submittedIds)->delete();
            Log::info('admin.products.sync_skus.finish', [
                'product_id' => $product->id,
                'kept_ids' => $submittedIds,
                'mode' => 'keep_submitted',
            ]);
            return;
        }

        $product->skus()->delete();
        Log::info('admin.products.sync_skus.finish', [
            'product_id' => $product->id,
            'kept_ids' => [],
            'mode' => 'delete_all',
        ]);
    }

    protected function categoryOptions($siteMode = null)
    {
        $query = Category::query()
            ->with('parent:id,name')
            ->orderByRaw('COALESCE(parent_id, 0) asc')
            ->orderBy('id');

        if (Schema::hasColumn('categories', 'site_mode')) {
            $siteMode = strtoupper((string) $siteMode);
            $siteMode = $siteMode === Category::SITE_MODE_B ? Category::SITE_MODE_B : Category::SITE_MODE_A;
            $query->where('site_mode', $siteMode);
        }

        $categories = $query->get(['id', 'name', 'parent_id']);

        $options = [];
        foreach ($categories as $category) {
            $label = $category->name;
            if ($category->parent_id && $category->parent) {
                $label = $category->parent->name . ' / ' . $category->name;
            }
            $options[$category->id] = $label;
        }

        return $options;
    }

}
