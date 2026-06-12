<?php

namespace App\Admin\Controllers;

use App\Models\Category;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;

class CategoriesController extends Controller
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
            $content->header('分类管理');
            $content->body($this->grid());
        });
    }

    /**
     * Edit interface.
     *
     * @param int $id
     * @return Content
     */
    public function edit($id)
    {
        return Admin::content(function (Content $content) use ($id) {
            $content->header('编辑分类');
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
            $content->header('新增分类');
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
        return Admin::grid(Category::class, function (Grid $grid) {
            $grid->model()->with('parent')->withCount(['products', 'children']);
            if (db_has_column('categories', 'sort_order')) {
                $grid->model()->orderBy('sort_order')->orderBy('id');
            } else {
                $grid->model()->orderBy('id', 'desc');
            }

            $grid->id('ID')->sortable();
            if (db_has_column('categories', 'sort_order')) {
                $grid->column('sort_order', '排序')->editable()->sortable();
            }
            $grid->name('分类名称');
            $grid->column('parent_id', '父级分类')->display(function ($parentId) {
                if (!$parentId) {
                    return '根分类';
                }

                $parentName = Category::query()->where('id', (int) $parentId)->value('name');
                return $parentName ?: '父分类已删除';
            });
            $grid->column('children_count', '子分类数量');
            $grid->is_directory('目录')->display(function ($value) {
                return $value ? '是' : '否';
            });
            $grid->column('products_count', '直属商品数');
            $grid->column('aggregate_products_count', '聚合商品数')->display(function () {
                $category = Category::query()->find((int) $this->id);
                if (!$category) {
                    return 0;
                }

                return $category->aggregateProductsCount();
            });
            $grid->column('manage_products', '商品管理')->display(function () {
                $directUrl = '/admin/products?category_id=' . $this->id . '&category_mode=direct';
                $aggregateUrl = '/admin/products?category_id=' . $this->id . '&category_mode=aggregate';

                return '<a href="' . $directUrl . '">查看直属商品</a>'
                    . ' / '
                    . '<a href="' . $aggregateUrl . '">查看聚合商品</a>';
            });
            $grid->column('manage_children', '子分类管理')->display(function () {
                if ($this->parent_id) {
                    return '-';
                }
                $url = '/admin/categories/create?parent_id=' . $this->id;
                return '<a href="' . $url . '">新增子分类</a>';
            });
            $grid->created_at('创建时间');

            $grid->actions(function ($actions) {
                $actions->disableView();
            });

            $grid->tools(function ($tools) {
                $tools->append(view('admin.categories._batch_tools'));
                $tools->batch(function ($batch) {
                    $batch->disableDelete();
                });
            });

            Admin::script(view('admin.partials._batch_helper_script')->render());
            Admin::script(view('admin.categories._batch_tools_script')->render());
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Admin::form(Category::class, function (Form $form) {
            $form->display('id', 'ID');
            $form->text('name', '分类名称')->rules('required|max:50');
            if (db_has_column('categories', 'sort_order')) {
                $form->number('sort_order', '排序权重')
                    ->default(0)
                    ->rules('required|integer|min:0')
                    ->help('数字越小越靠前；控制顶部导航与分类列表顺序，与 ID 无关');
            }

            $defaultParentId = (int) request('parent_id', 0);
            $parentOptions = Category::query()
                ->whereNull('parent_id')
                ->pluck('name', 'id')
                ->toArray();
            if ($form->model()->id) {
                unset($parentOptions[$form->model()->id]);
            }

            $form->select('parent_id', '父级分类')
                ->options([0 => '作为根分类'] + $parentOptions)
                ->default($defaultParentId)
                ->help('根分类会展示在顶部导航；选择根分类后将作为其子分类显示在悬停菜单中');

            $form->switch('is_directory', '是否目录')->states([
                'on'  => ['value' => 1, 'text' => '是', 'color' => 'primary'],
                'off' => ['value' => 0, 'text' => '否', 'color' => 'default'],
            ])->default(0);

            $form->select('default_shipping_mode', '默认寄送模式')
                ->options(\App\Models\Product::shippingModeOptions())
                ->default(\App\Services\ShippingModeService::MODE_EMS)
                ->help('该分类下商品未单独指定寄送模式时使用；EMS 自缴税与含税包邮不可混单');

            $form->saving(function (Form $form) {
                if ((int) $form->parent_id === 0) {
                    $form->parent_id = null;
                }
            });
        });
    }
}
