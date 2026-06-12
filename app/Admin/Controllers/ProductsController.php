<?php

namespace App\Admin\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSku;
use App\Services\HeatedTobaccoClassificationService;
use App\Services\OrderTobaccoLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;

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
            $grid->model()->with(['category', 'skus'])
                ->orderBy('sort_order')
                ->orderBy('id');
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
            $grid->column('sort_order', '排序')->editable()->sortable();
            $grid->title('商品名称');
            $grid->column('category.name', '所属分类')->display(function ($value) {
                return $value ?: '-';
            });
            $grid->column('shipping_mode', '寄送模式')->display(function ($value) {
                $resolved = $value ?: optional($this->category)->default_shipping_mode;

                return Product::shippingModeOptions()[$resolved] ?? 'EMS 自缴税';
            });
            $grid->column('tobacco_type', '烟草分类')->display(function ($value) {
                return Product::tobaccoTypeOptions()[$value] ?? '—';
            });
            $grid->column('unit_weight_grams', '单位重量(g)');
            $grid->column('unit_sticks', '支数/包')->display(function ($sticks) {
                return \App\Services\OrderTobaccoLimitService::countsTowardStickLimit($this->tobacco_type)
                    ? (int) $sticks
                    : '—';
            });
            $grid->on_sale('已上架')->display(function ($value) {
                return $value ? '是' : '否';
            });
            $grid->price('价格');
            $grid->column('sale_status', '销售状态')->display(function ($status) {
                $labels = [
                    ProductSku::STATUS_ACTIVE => '<span class="label label-success">正常购买</span>',
                    ProductSku::STATUS_LIMITED => '<span class="label label-warning">限购</span>',
                    ProductSku::STATUS_DEPLETED => '<span class="label label-default">售罄</span>',
                ];

                return $labels[$status] ?? e((string) $status);
            });
            $grid->column('purchase_limit', '限购数量')->display(function ($limit) {
                if ($this->sale_status !== ProductSku::STATUS_LIMITED) {
                    return '—';
                }

                $limit = (int) $limit;

                return $limit > 0 ? $limit.' 件/单' : '—';
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
                $actions->disableDelete();
            });
            $grid->tools(function ($tools) {
                // 禁用批量删除按钮
                $tools->batch(function ($batch) {
                    $batch->disableDelete();
                });

                $tools->append(view('admin.products._batch_tools', [
                    'categories' => $this->categoryOptions(),
                ]));
                $tools->append(
                    '<a class="btn btn-sm btn-default" href="'.route('admin.products.import_template').'" style="margin-left:6px;">'
                    .'<i class="fa fa-download"></i> 导入模板</a>'
                );
                $tools->append(view('admin.products._import_csv'));
            });

            Admin::script(view('admin.products._batch_tools_script')->render());
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
        return Admin::form(Product::class, function (Form $form) {
            // 裁切辅助字段（非数据库列，勿用 $form->hidden 注册为模型属性）
            $form->html(
                '<input type="hidden" name="_image_crop_meta" value="">'
                .'<input type="hidden" name="_image_crop_payload" value="">',
                ''
            );

            // 创建一个输入框，第一个参数 title 是模型的字段名，第二个参数是该字段描述
            $form->text('title', '商品名称')->rules('required');
            $form->number('sort_order', '排序权重')
                ->default(0)
                ->rules('required|integer|min:0')
                ->help('数字越小越靠前；控制前台商品列表默认顺序，与 ID 无关');
            $form->select('category_id', '所属分类')
                ->options($this->categoryOptions())
                ->help('请为商品选择归属分类，前台导航与筛选依赖此字段');
            $form->select('shipping_mode', '寄送模式')
                ->options(['' => '继承分类默认'] + Product::shippingModeOptions())
                ->help('留空则使用所属分类的默认寄送模式；含税包邮商品报价已含运费与税费，结算不再加收 EMS');
            $form->select('tobacco_type', '烟草分类（物流）')
                ->options(Product::tobaccoTypeOptions())
                ->rules('required')
                ->default(OrderTobaccoLimitService::TYPE_CIGARETTE)
                ->help('新品加热烟请选「加热烟」并填每包支数；旧品若一直标「香烟」可不改，仍计入 400 支。选加热烟分类后保存时会按分类名自动建议本项。');
            $form->number('unit_weight_grams', '单位重量（克）')
                ->min(1)
                ->default(0)
                ->rules('required|integer|min:1')
                ->help('香烟：每盒/每包重量；手卷烟丝：每包重量');
            $form->number('unit_sticks', '每包/盒支数（仅香烟）')
                ->min(1)
                ->default(0)
                ->help('烟草分类为「香烟」或「加热烟」时必填，计入单笔 400 支限额（二者合计）');
            // 创建一个选择图片的框
            $form->image('image', '商品主图')->rules('required|image')->help('上传后会弹出裁切器，支持缩放和拖拽；保存后前台使用裁切后的图片。');
            // 创建一个富文本编辑器
            $form->editor('description', '商品描述')->rules('required');
            // 创建一组单选框
            $form->radio('on_sale', '上架状态')->options(['1' => '已上架', '0' => '未上架'])->default('0');
            $form->select('sale_status', '销售状态')
                ->options(Product::saleStatusOptions())
                ->default(ProductSku::STATUS_ACTIVE)
                ->rules('required')
                ->help('正常购买：用户可下单；限购：需填写下方限购数量；售罄：前台不可购买');
            $form->number('purchase_limit', '限购数量（件/单）')
                ->min(1)
                ->help('仅当销售状态为「限购」时生效');
            // 直接添加一对多的关联模型
            $form->hasMany('skus', 'SKU 规格', function (Form\NestedForm $form) {
                $form->text('title', '规格名称')->rules('required');
                $form->text('description', '规格说明')->rules('required');
                $form->text('price', '销售单价')->rules('required|numeric|min:0.01');
            })->help('前台展示价 = 各规格「销售单价」中的最低价，保存后自动同步');
            $heatedCategoryPatternsJson = json_encode(
                config('heated_tobacco_classification.category_name_patterns', []),
                JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
            );
            Admin::script(
                'window.__heatedCategoryPatterns = '.$heatedCategoryPatternsJson
                .'; if (window.AdminProductForm) { AdminProductForm.init(); }'
            );

            $form->saving(function (Form $form) {
                request()->request->remove('_image_crop_payload');
                request()->request->remove('_image_crop_meta');

                if (\App\Services\OrderTobaccoLimitService::countsTowardStickLimit($form->tobacco_type)) {
                    if ((int) $form->unit_sticks < 1) {
                        throw new \Exception('烟草分类为「香烟」或「加热烟」时，请填写每包/盒支数（至少 1 支）');
                    }
                } else {
                    $form->unit_sticks = null;
                }

                if ($form->shipping_mode === '') {
                    $form->shipping_mode = null;
                }

                if ((int) $form->unit_weight_grams < 1) {
                    throw new \Exception('请填写单位重量（克）');
                }

                $productModel = $form->model();
                if (!$form->tobacco_type && $productModel instanceof Product) {
                    $suggested = app(HeatedTobaccoClassificationService::class)
                        ->suggestedTobaccoType($productModel);
                    if ($suggested) {
                        $form->tobacco_type = $suggested;
                    }
                }

                if ($form->sale_status === ProductSku::STATUS_LIMITED) {
                    if ((int) $form->purchase_limit < 1) {
                        throw new \Exception('销售状态为「限购」时，请填写限购数量（至少 1 件）');
                    }
                } else {
                    $form->purchase_limit = null;
                }
            });

            // SKU 在 saving 之后才写入，展示价必须在保存完成后按 SKU 最低价同步
            $form->saved(function (Form $form) {
                $product = $form->model()->fresh(['skus']);
                $minPrice = $product->skus->min('price');

                if ($minPrice === null || (float) $minPrice <= 0) {
                    return;
                }

                if ((float) $product->price !== (float) $minPrice) {
                    $product->forceFill(['price' => $minPrice])->save();
                }
            });
        });
    }

    protected function categoryOptions()
    {
        $categories = Category::query()
            ->with('parent:id,name')
            ->orderByRaw('COALESCE(parent_id, 0) asc')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'parent_id']);

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

    public function downloadImportTemplate()
    {
        $headers = [
            'id',
            'title',
            'category_id',
            'shipping_mode',
            'tobacco_type',
            'unit_weight_grams',
            'unit_sticks',
            'on_sale',
            'sale_status',
            'purchase_limit',
        ];
        $sample = [
            '',
            '示例商品',
            '1',
            'ems_self_tax',
            'cigarette',
            '200',
            '20',
            '1',
            'ACTIVE',
            '',
        ];

        $lines = [implode(',', $headers), implode(',', $sample)];
        $csv = "\xEF\xBB\xBF".implode("\n", $lines);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="products_import_template.csv"',
        ]);
    }

    public function importCsv(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        if (!$handle) {
            return redirect()->back()->withErrors(['file' => '无法读取 CSV 文件']);
        }

        $header = null;
        $updated = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map(function ($col) {
                    return trim((string) $col);
                }, $row);
                continue;
            }

            if (count(array_filter($row)) === 0) {
                continue;
            }

            $data = [];
            foreach ($header as $i => $col) {
                $data[$col] = isset($row[$i]) ? trim((string) $row[$i]) : '';
            }

            $id = (int) data_get($data, 'id', 0);
            if ($id < 1) {
                $skipped++;
                continue;
            }

            $product = Product::query()->find($id);
            if (!$product) {
                $skipped++;
                continue;
            }

            $payload = [];
            foreach (['shipping_mode', 'tobacco_type', 'unit_weight_grams', 'unit_sticks', 'sale_status'] as $field) {
                if (array_key_exists($field, $data) && $data[$field] !== '') {
                    $payload[$field] = $data[$field];
                }
            }

            if (isset($payload['unit_weight_grams'])) {
                $payload['unit_weight_grams'] = (int) $payload['unit_weight_grams'];
            }
            if (isset($payload['unit_sticks'])) {
                $payload['unit_sticks'] = (int) $payload['unit_sticks'] ?: null;
            }
            if (isset($payload['shipping_mode']) && !array_key_exists($payload['shipping_mode'], Product::shippingModeOptions())) {
                unset($payload['shipping_mode']);
            }
            if (isset($payload['tobacco_type']) && !array_key_exists($payload['tobacco_type'], Product::tobaccoTypeOptions())) {
                unset($payload['tobacco_type']);
            }

            if (!empty($payload)) {
                $product->update($payload);
                $updated++;
            }
        }

        fclose($handle);

        return redirect()
            ->to('/admin/products')
            ->with('success', 'CSV 导入完成：更新 '.$updated.' 条，跳过 '.$skipped.' 条。');
    }

}
