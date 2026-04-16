<?php

namespace App\Admin\Controllers;

use App\Exceptions\InternalException;
use App\Models\Order;
use App\Services\OrderExportService;
use Illuminate\Http\Request;
use App\Exceptions\InvalidRequestException;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;
use App\Http\Requests\Admin\HandleRefundRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrdersController extends Controller
{
    use ModelForm;

    public function exportTodayOrders(Request $request, OrderExportService $exportService)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $data = $exportService->buildExportData($startDate, $endDate);

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('admin.orders.export', $data);
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'defaultFont' => 'IPA Gothic',
            'isRemoteEnabled' => true,
        ]);

        $fileName = $exportService->buildFileName($data['endAt']);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function index()
    {
        return Admin::content(function (Content $content) {
            $content->header('订单列表');
            $content->body($this->grid());
        });
    }

    public function show(Order $order)
    {
        return Admin::content(function (Content $content) use ($order) {
            $content->header('查看订单');
            // body 方法可以接受 Laravel 的视图作为参数
            $content->body(view('admin.orders.show', ['order' => $order]));
        });
    }

    public function updateFulfillment(Order $order, Request $request)
    {
        $this->validate($request, [
            'fulfillment_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ]);

        $data = [];

        if ($request->hasFile('fulfillment_photo')) {
            $data['fulfillment_photo'] = $this->storeFulfillmentPhoto($request->file('fulfillment_photo'), $order);

            if ($order->fulfillment_photo) {
                Storage::disk('private')->delete($order->fulfillment_photo);
            }
        }

        if (!empty($data)) {
            $order->update($data);
        }

        admin_toastr('履约信息已更新', 'success');

        return redirect()->route('admin.orders.show', ['order' => $order->id]);
    }

    public function ship(Order $order, Request $request)
    {
        // 判断当前订单是否已支付
        if (!$order->paid_at) {
            throw new InvalidRequestException('该订单未付款');
        }
        // 判断当前订单发货状态是否为未发货
        if ($order->ship_status !== Order::SHIP_STATUS_PENDING) {
            throw new InvalidRequestException('该订单已发货');
        }
        // Laravel 5.5 之后 validate 方法可以返回校验过的值
        $data = $this->validate($request, [
            'express_company' => ['required'],
            'express_no'      => ['required'],
        ], [], [
            'express_company' => '物流公司',
            'express_no'      => '物流单号',
        ]);
        // 将订单发货状态改为已发货，并存入物流信息
        $order->update([
            'ship_status' => Order::SHIP_STATUS_DELIVERED,
            // 我们在 Order 模型的 $casts 属性里指明了 ship_data 是一个数组
            // 因此这里可以直接把数组传过去
            'ship_data'   => $data,
            // 与履约录入字段保持一致，确保用户端订单列表可直接展示运单号
            'tracking_no' => $order->tracking_no ?: trim((string) $data['express_no']),
        ]);

        // 返回上一页
        return redirect()->back();
    }

    public function markAcceptance(Order $order, Request $request)
    {
        if (!$order->paid_at) {
            throw new InvalidRequestException('仅已支付订单可标注受理状态');
        }

        $status = (string) $request->input('status', '');
        if (!in_array($status, [Order::ACCEPTANCE_STATUS_PENDING, Order::ACCEPTANCE_STATUS_ACCEPTED], true)) {
            throw new InvalidRequestException('受理状态参数不正确');
        }

        $order->markAcceptance($status, optional(Admin::user())->id);

        return ['status' => true, 'message' => '受理状态已更新'];
    }

    public function batchMarkAcceptance(Request $request)
    {
        $ids = (array) $request->input('ids', []);
        $status = (string) $request->input('status', '');

        if (!in_array($status, [Order::ACCEPTANCE_STATUS_PENDING, Order::ACCEPTANCE_STATUS_ACCEPTED], true)) {
            throw new InvalidRequestException('受理状态参数不正确');
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            throw new InvalidRequestException('请选择要标注的订单');
        }

        $count = 0;
        $operatorId = optional(Admin::user())->id;
        $orders = Order::query()->whereIn('id', $ids)->whereNotNull('paid_at')->get();
        foreach ($orders as $order) {
            if ($order->markAcceptance($status, $operatorId)) {
                $count++;
            }
        }

        return ['status' => true, 'message' => '已更新 ' . $count . ' 条订单受理状态'];
    }

    protected function grid()
    {
        return Admin::grid(Order::class, function (Grid $grid) {
            // 只展示已支付的订单，并且默认按支付时间倒序排序
            $grid->model()->whereNotNull('paid_at')->orderBy('paid_at', 'desc');

            $grid->no('订单流水号');
            // 展示关联关系的字段时，使用 column 方法
            $grid->column('user.name', '买家');
            $grid->total_amount('总金额')->sortable();
            $grid->paid_at('支付时间')->sortable();
            $grid->ship_status('物流')->display(function($value) {
                return Order::$shipStatusMap[$value];
            });
            $grid->column('acceptance_status', '受理标注')->display(function () {
                $status = $this->acceptance_status;
                $map = Order::$acceptanceStatusMap;
                $text = data_get($map, $status, '未受理');
                $labelClass = $status === Order::ACCEPTANCE_STATUS_ACCEPTED ? 'label-success' : 'label-warning';
                return "<span class=\"label {$labelClass}\">{$text}</span>";
            });
            $grid->column('swap_item_count', '调换次数')->display(function () {
                $history = data_get($this->extra, 'swap_item_history', []);
                return is_array($history) ? count($history) : 0;
            });
            $grid->refund_status('退款状态')->display(function($value) {
                return Order::$refundStatusMap[$value];
            });

            $grid->filter(function($filter) {
                $filter->disableIdFilter();
                $filter->like('no', '订单流水号');
                $filter->equal('ship_status', '发货状态')->select(Order::$shipStatusMap);
                $filter->equal('refund_status', '退款状态')->select(Order::$refundStatusMap);
                $filter->where(function ($query) {
                    if ($this->input === Order::ACCEPTANCE_STATUS_ACCEPTED) {
                        $query->where('extra->acceptance->status', Order::ACCEPTANCE_STATUS_ACCEPTED);
                        return;
                    }

                    if ($this->input === Order::ACCEPTANCE_STATUS_PENDING) {
                        $query->where(function ($subQuery) {
                            $subQuery->where('extra->acceptance->status', Order::ACCEPTANCE_STATUS_PENDING)
                                ->orWhere(function ($fallbackQuery) {
                                    $fallbackQuery->whereNull('extra->acceptance->status')
                                        ->where('ship_status', Order::SHIP_STATUS_PENDING);
                                });
                        });
                    }
                }, '受理标注')->select(Order::$acceptanceStatusMap);
                $filter->where(function ($query) {
                    if ($this->input === 'yes') {
                        $query->whereNotNull('extra->swap_item_history.0');
                        return;
                    }

                    if ($this->input === 'no') {
                        $query->whereNull('extra->swap_item_history.0');
                    }
                }, '有调换记录')->select([
                    'yes' => '有',
                    'no' => '无',
                ]);
            });

            // 禁用创建按钮，后台不需要创建订单
            $grid->disableCreateButton();
            $grid->actions(function ($actions) {
                // 禁用删除和编辑按钮
                $actions->disableDelete();
                $actions->disableEdit();
            });
            $grid->tools(function ($tools) {
                $tools->append('<a href="'.route('admin.orders.export_today').'" class="btn btn-sm btn-primary" style="margin-right:8px;"><i class="fa fa-download"></i> 导出发货单</a>');
                                $tools->append('<button type="button" class="btn btn-sm btn-success" id="btn-batch-mark-accepted" style="margin-right:8px;"><i class="fa fa-check"></i> 批量标注已受理</button>');
                                $tools->append('<button type="button" class="btn btn-sm btn-warning" id="btn-batch-mark-pending" style="margin-right:8px;"><i class="fa fa-undo"></i> 批量标注未受理</button>');
                // 禁用批量删除按钮
                $tools->batch(function ($batch) {
                    $batch->disableDelete();
                });
            });

                        $batchUrl = route('admin.orders.batch_mark_acceptance');
                        Admin::script(<<<JS
(function () {
    function selectedOrderIds() {
        var ids = [];
        $('.grid-row-checkbox:checked').each(function () {
            var id = parseInt($(this).val(), 10);
            if (!isNaN(id)) ids.push(id);
        });
        return ids;
    }

    function batchMark(status) {
        var ids = selectedOrderIds();
        if (!ids.length) {
            swal('请先勾选订单', '', 'warning');
            return;
        }
        $.ajax({
            url: '{$batchUrl}',
            type: 'POST',
            data: JSON.stringify({ ids: ids, status: status, _token: LA.token }),
            contentType: 'application/json',
            success: function (res) {
                swal('操作成功', (res && res.message) ? res.message : '', 'success');
                setTimeout(function () { location.reload(); }, 600);
            },
            error: function (xhr) {
                var msg = '批量标注失败';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                swal(msg, '', 'error');
            }
        });
    }

    $(document).on('click', '#btn-batch-mark-accepted', function () {
        batchMark('accepted');
    });

    $(document).on('click', '#btn-batch-mark-pending', function () {
        batchMark('pending');
    });
})();
JS
                        );
        });
    }

    public function handleRefund(Order $order, HandleRefundRequest $request)
    {
        // 判断订单状态是否正确
        if ($order->refund_status !== Order::REFUND_STATUS_APPLIED) {
            throw new InvalidRequestException('订单状态不正确');
        }
        // 是否同意退款
        if ($request->input('agree')) {
            // 清空拒绝退款理
            $extra = $order->extra ?: [];
            unset($extra['refund_disagree_reason']);
            $order->update([
                'extra' => $extra,
            ]);
            // 调用退款逻辑
            $this->_refundOrder($order);
        } else {
            // 将拒绝退款理由放到订单的 extra 字段中
            $extra = $order->extra ?: [];
            $extra['refund_disagree_reason'] = $request->input('reason');
            // 将订单的退款状态改为未退款
            $order->update([
                'refund_status' => Order::REFUND_STATUS_PENDING,
                'extra'         => $extra,
            ]);
        }

        return $order;
    }

    protected function _refundOrder(Order $order)
    {
        // 判断该订单的支付方式
        switch ($order->payment_method) {
            case 'wechat':
                // 生成退款订单号
                $refundNo = Order::getAvailableRefundNo();
                app('wechat_pay')->refund([
                    'out_trade_no' => $order->no, // 之前的订单流水号
                    'total_fee' => $order->total_amount * 100, //原订单金额，单位分
                    'refund_fee' => $order->total_amount * 100, // 要退款的订单金额，单位分
                    'out_refund_no' => $refundNo, // 退款订单号
                    // 微信支付的退款结果并不是实时返回的，而是通过退款回调来通知，因此这里需要配上退款回调接口地址
                    'notify_url' => route('payment.wechat.refund_notify'),
                ]);
                // 将订单状态改成退款中
                $order->update([
                    'refund_no' => $refundNo,
                    'refund_status' => Order::REFUND_STATUS_PROCESSING,
                ]);
                break;
            case 'alipay':
                // 用我们刚刚写的方法来生成一个退款订单号
                $refundNo = Order::getAvailableRefundNo();
                // 调用支付宝支付实例的 refund 方法
                $ret = app('alipay')->refund([
                    'out_trade_no' => $order->no, // 之前的订单流水号
                    'refund_amount' => $order->total_amount, // 退款金额，单位元
                    'out_request_no' => $refundNo, // 退款订单号
                ]);
                // 根据支付宝的文档，如果返回值里有 sub_code 字段说明退款失败
                if ($ret->sub_code) {
                    // 将退款失败的保存存入 extra 字段
                    $extra = $order->extra;
                    $extra['refund_failed_code'] = $ret->sub_code;
                    // 将订单的退款状态标记为退款失败
                    $order->update([
                        'refund_no' => $refundNo,
                        'refund_status' => Order::REFUND_STATUS_FAILED,
                        'extra' => $extra,
                    ]);
                } else {
                    // 将订单的退款状态标记为退款成功并保存退款订单号
                    $order->update([
                        'refund_no' => $refundNo,
                        'refund_status' => Order::REFUND_STATUS_SUCCESS,
                    ]);
                }
                break;
            default:
                // 原则上不可能出现，这个只是为了代码健壮性
                throw new InternalException('未知订单支付方式：'.$order->payment_method);
                break;
        }
    }

    protected function storeFulfillmentPhoto($file, Order $order)
    {
        $ext = strtolower((string) $file->getClientOriginalExtension() ?: 'jpg');
        $fileName = sprintf(
            'orders/%s/%s-%s.%s',
            $order->no,
            date('YmdHis'),
            Str::random(16),
            $ext
        );

        Storage::disk('private')->putFileAs('orders/' . $order->no, $file, basename($fileName));

        return $fileName;
    }
}
