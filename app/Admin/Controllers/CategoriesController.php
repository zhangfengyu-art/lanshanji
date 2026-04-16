<?php

namespace App\Admin\Controllers;

use App\Models\Category;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;
use Illuminate\Support\Facades\Schema;

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
            $activeSiteMode = strtoupper((string) request('site_mode', site_mode()));
            $activeSiteMode = $activeSiteMode === Category::SITE_MODE_B ? Category::SITE_MODE_B : Category::SITE_MODE_A;

            $grid->model()->with('parent')->withCount(['products', 'children'])->orderBy('id', 'desc');
            if (Schema::hasColumn('categories', 'site_mode')) {
                $grid->model()->where('site_mode', $activeSiteMode);
            }

            $grid->id('ID')->sortable();
            $grid->name('分类名称');
            if (Schema::hasColumn('categories', 'site_mode')) {
                $grid->column('site_mode', '站点')->display(function ($value) {
                    return $value === Category::SITE_MODE_B ? 'B站（代购）' : 'A站（香烟）';
                });
            }
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

            if (Schema::hasColumn('categories', 'site_mode')) {
                $grid->filter(function ($filter) {
                    $filter->equal('site_mode', '站点')->select(Category::siteModeOptions());
                });
            }

            $grid->actions(function ($actions) {
                $actions->disableView();
            });
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

            $activeSiteMode = strtoupper((string) request('site_mode', site_mode()));
            $activeSiteMode = $activeSiteMode === Category::SITE_MODE_B ? Category::SITE_MODE_B : Category::SITE_MODE_A;

            if (Schema::hasColumn('categories', 'site_mode')) {
                $form->select('site_mode', '站点')
                    ->options(Category::siteModeOptions())
                    ->default($activeSiteMode)
                    ->rules('required|in:A,B')
                    ->help('分类仅在对应站点可见。');
            }

            $defaultParentId = (int) request('parent_id', 0);
            $parentQuery = Category::query()->whereNull('parent_id');
            if (Schema::hasColumn('categories', 'site_mode')) {
                $parentQuery->where('site_mode', $activeSiteMode);
            }
            $parentOptions = $parentQuery->pluck('name', 'id')->toArray();
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

            $form->saving(function (Form $form) {
                if ((int) $form->parent_id === 0) {
                    $form->parent_id = null;
                }

                if (Schema::hasColumn('categories', 'site_mode')) {
                    $siteMode = strtoupper((string) $form->site_mode);
                    $form->site_mode = $siteMode === Category::SITE_MODE_B ? Category::SITE_MODE_B : Category::SITE_MODE_A;

                    if ($form->parent_id) {
                        $parent = Category::query()->find((int) $form->parent_id);
                        if ($parent && strtoupper((string) $parent->site_mode) !== strtoupper((string) $form->site_mode)) {
                            throw new \Exception('父级分类与当前分类站点不一致');
                        }
                    }
                }
            });
        });
    }
}
