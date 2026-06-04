<?php

namespace App\Admin\Controllers;

use App\Models\ProcurementOrder;
use Encore\Admin\Controllers\ModelForm;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;

class ProcurementOrdersController extends Controller
{
    use ModelForm;

    public function index()
    {
        return Admin::content(function (Content $content) {
            $content->header('代购需求管理');
            $content->description('审核并维护互助代购大厅中的求购需求');
            $content->body($this->grid());
        });
    }

    public function create()
    {
        return Admin::content(function (Content $content) {
            $content->header('新建代购需求');
            $content->body($this->form());
        });
    }

    public function edit($id)
    {
        return Admin::content(function (Content $content) use ($id) {
            $content->header('编辑代购需求');
            $content->body($this->form()->edit($id));
        });
    }

    public function quickAccept($id)
    {
        $order = ProcurementOrder::query()->findOrFail((int) $id);
        if ((int) $order->proxy_status !== ProcurementOrder::STATUS_ACCEPTED) {
            $order->update(['proxy_status' => ProcurementOrder::STATUS_ACCEPTED]);
        }

        admin_toastr('已将需求状态更新为“已接单”', 'success');

        return redirect()->back();
    }

    protected function grid()
    {
        return Admin::grid(ProcurementOrder::class, function (Grid $grid) {
            $quickStatus = $this->getQuickStatusFromRequest();

            $grid->model()->orderBy('created_at', 'desc');
            if ($quickStatus !== null) {
                $grid->model()->where('proxy_status', $quickStatus);
            }

            $grid->column('order_no', '单号')->display(function ($value) {
                return $value ?: '-';
            });
            $grid->buyer_nickname('求购者');
            $grid->budget_amount('预算')->display(function ($value) {
                return 'JPY ¥' . number_format((float) $value, 2, '.', '');
            })->sortable();
            $grid->proxy_status('状态')->display(function ($value) {
                $text = ProcurementOrder::$statusMap[$value] ?? '未知';
                $classMap = [
                    ProcurementOrder::STATUS_PENDING => 'default',
                    ProcurementOrder::STATUS_ACCEPTED => 'warning',
                    ProcurementOrder::STATUS_SOURCING => 'info',
                    ProcurementOrder::STATUS_SHIPPED => 'success',
                ];
                $class = $classMap[$value] ?? 'default';
                return '<span class="label label-' . $class . '">' . $text . '</span>';
            });
            $grid->created_at('创建时间')->sortable();

            $grid->filter(function (Grid\Filter $filter) {
                $filter->disableIdFilter();
                $filter->like('order_no', '单号');
                $filter->like('buyer_nickname', '求购者');
                $filter->equal('proxy_status', '状态')->select(ProcurementOrder::$statusMap);
                $filter->between('budget_amount', '预算金额');
                $filter->between('created_at', '创建时间')->datetime();
            });

            $grid->actions(function ($actions) {
                $actions->disableDelete();

                $orderId = (int) $actions->getKey();
                $currentStatus = (int) data_get($actions->row, 'proxy_status');
                if ($currentStatus !== ProcurementOrder::STATUS_ACCEPTED) {
                    $url = admin_url('procurement-orders/' . $orderId . '/quick-accept');
                    $actions->append('<a href="' . $url . '" class="btn btn-xs btn-warning" style="margin-left:6px;">一键接单</a>');
                }
            });

            $grid->tools(function ($tools) use ($quickStatus) {
                $tools->append($this->renderStatusQuickTools($quickStatus));
                $tools->batch(function ($batch) {
                    $batch->disableDelete();
                });
            });
        });
    }

    protected function form()
    {
        return Admin::form(ProcurementOrder::class, function (Form $form) {
            $form->display('id', 'ID');
            $form->display('no', '需求编号');
            $form->text('order_no', '关联订单号')->help('选填，用于关联主订单编号 no');
            $form->text('item_name', '商品名称')->rules('required|string|max:255');
            $form->image('item_image', '商品图片')
                ->move('references')
                ->uniqueName()
                ->removable()
                ->help('上传目录：storage/app/public/references');
            $form->text('buyer_nickname', '求购者昵称')->rules('required|string|max:60');
            $form->currency('budget_amount', '预算金额')->symbol('JPY ¥')->rules('required|numeric|min:0.01');
            $form->select('proxy_status', '需求状态')->options(ProcurementOrder::$statusMap)->default(ProcurementOrder::STATUS_PENDING);
            $form->textarea('order_narrative', '求购描述')->rules('nullable|string|max:2000');
            $form->textarea('extra', '扩展数据(JSON)')->help('可选，留空时保持原值')->rows(4);
            $form->display('created_at', '创建时间');
            $form->display('updated_at', '更新时间');

            $form->saving(function (Form $form) {
                $extra = $form->extra;
                if (is_string($extra) && trim($extra) !== '') {
                    $decoded = json_decode($extra, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \InvalidArgumentException('扩展数据必须是合法 JSON');
                    }
                    $form->extra = $decoded;
                } elseif (trim((string) $extra) === '') {
                    unset($form->extra);
                }
            });
        });
    }

    protected function getQuickStatusFromRequest()
    {
        $status = request()->query('status_quick');
        $allowed = [
            ProcurementOrder::STATUS_PENDING,
            ProcurementOrder::STATUS_ACCEPTED,
            ProcurementOrder::STATUS_SOURCING,
            ProcurementOrder::STATUS_SHIPPED,
        ];

        if ($status === null || $status === '') {
            return null;
        }

        return in_array((int) $status, $allowed, true) ? (int) $status : null;
    }

    protected function renderStatusQuickTools($activeStatus)
    {
        $buttons = [
            '全部' => null,
            '等待接单' => ProcurementOrder::STATUS_PENDING,
            '已接单' => ProcurementOrder::STATUS_ACCEPTED,
            '采购中' => ProcurementOrder::STATUS_SOURCING,
            '已发货' => ProcurementOrder::STATUS_SHIPPED,
        ];

        $baseQuery = request()->query();
        unset($baseQuery['page']);

        $html = '<div class="btn-group" style="margin-right:10px;">';
        foreach ($buttons as $label => $status) {
            $query = $baseQuery;
            if ($status === null) {
                unset($query['status_quick']);
            } else {
                $query['status_quick'] = $status;
            }

            $url = url()->current() . (empty($query) ? '' : '?' . http_build_query($query));
            $isActive = ($status === null && $activeStatus === null) || ($status !== null && (int) $activeStatus === (int) $status);
            $class = $isActive ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-default';
            $html .= '<a class="' . $class . '" href="' . $url . '">' . $label . '</a>';
        }
        $html .= '</div>';

        return $html;
    }
}
