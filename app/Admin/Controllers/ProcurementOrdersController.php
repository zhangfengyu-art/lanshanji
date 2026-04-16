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
            $content->description('审核并维护 C2C 求购需求内容');
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
    public function review($id)
    {
        $order = ProcurementOrder::query()->findOrFail((int) $id);
        
        // 如果已审核，不能重复审核
        if ((int) $order->review_status !== ProcurementOrder::REVIEW_STATUS_PENDING) {
            admin_toastr('该求购单已审核，无法再次审核', 'warning');
            return redirect()->back();
        }

        return Admin::content(function (Content $content) use ($order) {
            $content->header('审核原生求购订单');
            $content->description('ID: ' . $order->id . ' | 单号: ' . ($order->no ?: '-'));
            $content->body($this->renderReviewForm($order));
        });
    }

    public function submitReview($id)
    {
        $order = ProcurementOrder::query()->findOrFail((int) $id);
        
        if ((int) $order->review_status !== ProcurementOrder::REVIEW_STATUS_PENDING) {
            return response()->json(['message' => '该求购单已审核'], 422);
        }

        $approved = (int) request()->input('approved', 0) === 1;
        $comment = trim((string) request()->input('comment', ''));

        $order->update([
            'review_status' => $approved ? ProcurementOrder::REVIEW_STATUS_APPROVED : ProcurementOrder::REVIEW_STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_comment' => $comment,
        ]);

        $status = $approved ? '已通过' : '已拒绝';
        admin_toastr('审核完成：' . $status, 'success');

        return redirect()->route('admin.procurement_orders.index');
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
            $grid->proxy_status('代购状态')->display(function ($value) {
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
            $grid->review_status('审核状态')->display(function ($value) {
                $text = ProcurementOrder::$reviewStatusMap[$value] ?? '未知';
                $classMap = [
                    ProcurementOrder::REVIEW_STATUS_PENDING => 'danger',
                    ProcurementOrder::REVIEW_STATUS_APPROVED => 'success',
                    ProcurementOrder::REVIEW_STATUS_REJECTED => 'default',
                ];
                $class = $classMap[$value] ?? 'default';
                return '<span class="label label-' . $class . '">' . $text . '</span>';
            });
            $grid->created_at('创建时间')->sortable();

            $grid->filter(function (Grid\Filter $filter) {
                $filter->disableIdFilter();
                $filter->like('order_no', '单号');
                $filter->like('buyer_nickname', '求购者');
                $filter->equal('proxy_status', '代购状态')->select(ProcurementOrder::$statusMap);
                $filter->equal('review_status', '审核状态')->select(ProcurementOrder::$reviewStatusMap);
                $filter->between('budget_amount', '预算金额');
                $filter->between('created_at', '创建时间')->datetime();
            });

            $grid->actions(function ($actions) {
                $actions->disableDelete();

                $orderId = (int) $actions->getKey();
                $reviewStatus = (int) data_get($actions->row, 'review_status');
                $currentStatus = (int) data_get($actions->row, 'proxy_status');
                
                // 如果未审核，添加审核按钮
                if ($reviewStatus === ProcurementOrder::REVIEW_STATUS_PENDING) {
                    $url = admin_url('procurement-orders/' . $orderId . '/review');
                    $actions->append('<a href="' . $url . '" class="btn btn-xs btn-info" style="margin-left:6px;">审核</a>');
                }
                
                // 如果已通过审核且未接单，添加一键接单按钮
                if ($reviewStatus === ProcurementOrder::REVIEW_STATUS_APPROVED && $currentStatus !== ProcurementOrder::STATUS_ACCEPTED) {
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
            $form->text('order_no', '关联单号')->help('可选，用于关联主订单 no');
            $form->text('item_name', '商品名称')->rules('required|string|max:255');
            $form->image('item_image', '商品图片')
                ->move('references')
                ->uniqueName()
                ->removable()
                ->help('上传路径: storage/app/public/references');
            $form->text('buyer_nickname', '求购者昵称')->rules('required|string|max:60');
            $form->currency('budget_amount', '预算金额')->symbol('JPY ¥')->rules('required|numeric|min:0.01');
            $form->select('proxy_status', '状态')->options(ProcurementOrder::$statusMap)->default(ProcurementOrder::STATUS_PENDING);
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

    protected function renderReviewForm($order)
    {
        $html = '<div class="box box-primary">';
        $html .= '<div class="box-header with-border"><h3 class="box-title">求购信息预览</h3></div>';
        $html .= '<div class="box-body">';
        $html .= '<div class="row">';
        $html .= '<div class="col-md-4" style="text-align:center;">';
        if ($order->item_image) {
            $html .= '<img src="' . asset('storage/' . $order->item_image) . '" style="max-width:100%; max-height:300px; border-radius:4px;">';
        } else {
            $html .= '<p style="color:#999;">暂无图片</p>';
        }
        $html .= '</div>';
        $html .= '<div class="col-md-8">';
        $html .= '<table class="table table-striped">';
        $html .= '<tr><td style="width:120px;">商品名称</td><td>' . e($order->item_name) . '</td></tr>';
        $html .= '<tr><td>求购者</td><td>' . e($order->buyer_nickname) . '</td></tr>';
        $html .= '<tr><td>预算金额</td><td>JPY ¥' . number_format($order->budget_amount, 2, '.', '') . '</td></tr>';
        $html .= '<tr><td>求购描述</td><td><pre style="height:100px; overflow:auto; background:#f9f9f9; padding:8px; border-radius:3px;">' . e($order->order_narrative) . '</pre></td></tr>';
        $html .= '<tr><td>创建时间</td><td>' . $order->created_at->format('Y-m-d H:i:s') . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '<div class="box box-warning">';
        $html .= '<div class="box-header with-border"><h3 class="box-title">审核</h3></div>';
        $html .= '<form method="POST" action="' . admin_url('procurement-orders/' . $order->id . '/submit-review') . '" class="form-horizontal">';
        $html .= csrf_field();
        $html .= '<div class="box-body">';
        $html .= '<div class="form-group">';
        $html .= '<label class="col-sm-2 control-label">审核结果 <span style="color:red;">*</span></label>';
        $html .= '<div class="col-sm-6">';
        $html .= '<label style="margin-right:20px;"><input type="radio" name="approved" value="1"> 通过审核，允许上线到 B 站</label><br>';
        $html .= '<label><input type="radio" name="approved" value="0"> 拒绝审核，该求购单无法上线</label>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="form-group">';
        $html .= '<label class="col-sm-2 control-label">备注</label>';
        $html .= '<div class="col-sm-6">';
        $html .= '<textarea name="comment" class="form-control" rows="4" placeholder="审核备注（可选）"></textarea>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="box-footer">';
        $html .= '<button type="submit" class="btn btn-primary">提交审核</button>';
        $html .= '<a href="' . admin_url('procurement-orders') . '" class="btn btn-default" style="margin-left:10px;">取消</a>';
        $html .= '</div>';
        $html .= '</form>';
        $html .= '</div>';

        return $html;
    }
}
