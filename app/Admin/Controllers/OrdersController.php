<?php

namespace App\Admin\Controllers;

use App\Exceptions\InternalException;
use App\Models\Order;
use App\Services\AdminCsvExport;
use App\Services\OrderAdminExportService;
use App\Services\OrderFulfillmentService;
use Illuminate\Http\Request;
use App\Exceptions\InvalidRequestException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;
use App\Http\Requests\Admin\ExecuteOrderRefundRequest;

class OrdersController extends Controller
{
    use ModelForm;

    public function index()
    {
        return Admin::content(function (Content $content) {
            $content->header('订单列表');
            $content->body($this->grid());
        });
    }

    public function show(Order $order)
    {
        $order->load(['user', 'items.product', 'items.productSku']);

        $refundPreview = is_site_mode_a()
            ? app(\App\Services\OrderRefundPolicyService::class)->previewAdminRefund($order)
            : null;
        $refundReasons = config('order_refund.admin_reasons', []);

        return Admin::content(function (Content $content) use ($order, $refundPreview, $refundReasons) {
            $content->header('查看订单');
            // body 方法可以接受 Laravel 的视图作为参数
            $content->body(view('admin.orders.show', [
                'order' => $order,
                'refundPreview' => $refundPreview,
                'refundReasons' => $refundReasons,
            ]));
        });
    }

    public function export(Request $request)
    {
        $scope = (string) $request->query('scope', 'all');
        $options = OrderAdminExportService::scopeOptions();
        if (!array_key_exists($scope, $options)) {
            $scope = 'all';
        }

        $rows = [];
        $fulfillment = app(OrderFulfillmentService::class);
        OrderAdminExportService::buildQuery($scope)->chunk(200, function ($orders) use (&$rows, $scope, $fulfillment) {
            foreach ($orders as $order) {
                if ($scope === 's1_pending' && $fulfillment->resolveStage($order) !== OrderFulfillmentService::STAGE_S1) {
                    continue;
                }
                $rows[] = OrderAdminExportService::row($order);
            }
        });

        return AdminCsvExport::download(
            OrderAdminExportService::filename($scope),
            OrderAdminExportService::headers(),
            $rows
        );
    }

    public function showFulfillmentPhoto(Order $order)
    {
        if (!$order->hasFulfillmentPhoto()) {
            abort(404, '实拍照片尚未上传');
        }

        $path = $order->fulfillment_photo;
        $disk = Storage::disk('private');

        return $disk->response($path, 'fulfillment-'.$order->no.'.jpg', [
            'Content-Type' => $disk->mimeType($path) ?: 'image/jpeg',
        ]);
    }

    public function uploadFulfillmentPhoto(Order $order, Request $request)
    {
        if (!$order->paid_at) {
            throw new InvalidRequestException('仅已支付订单可上传实拍图');
        }

        $this->validate($request, [
            'photo' => ['required', 'image', 'max:10240'],
        ], [], [
            'photo' => '实拍照片',
        ]);

        $file = $request->file('photo');
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }
        $filename = Str::random(32).'.'.$extension;
        $path = $file->storeAs('orders/fulfillment/'.$order->id, $filename, 'private');

        $extra = $order->extra ?: [];
        $oldPath = trim((string) data_get($extra, 'fulfillment_photo', ''));
        if ($oldPath !== '' && $oldPath !== $path && Storage::disk('private')->exists($oldPath)) {
            Storage::disk('private')->delete($oldPath);
        }
        $extra['fulfillment_photo'] = $path;
        $extra['fulfillment_photo_uploaded_at'] = now()->toDateTimeString();

        if (!data_get($extra, 'locked_at')) {
            $extra['locked_at'] = now()->toDateTimeString();
        }

        $order->update(['extra' => $extra]);

        return redirect()
            ->back()
            ->with('success', '实拍照片已上传；订单已进入备货/打包阶段（S3），用户不可再自助改址。');
    }

    public function startProcessing(Order $order, OrderFulfillmentService $fulfillment)
    {
        $fulfillment->startProcessing($order);

        return redirect()
            ->back()
            ->with('success', '订单已开始处理（S2），用户不可再自助改址。');
    }

    public function lockOrder(Order $order, OrderFulfillmentService $fulfillment)
    {
        $fulfillment->lockOrder($order);

        return redirect()
            ->back()
            ->with('success', '订单已锁定（S3），用户不可再自助改址。');
    }

    public function unlockOrder(Order $order, OrderFulfillmentService $fulfillment)
    {
        if ($order->hasFulfillmentPhoto()) {
            throw new InvalidRequestException('已上传履约照片，不可解除锁定。');
        }

        $fulfillment->unlockOrder($order);

        return redirect()
            ->back()
            ->with('success', '已解除锁定；若未开始处理则回到待处理（S1），否则为处理中（S2）。');
    }

    public function deleteFulfillmentPhoto(Order $order)
    {
        $extra = $order->extra ?: [];
        $path = trim((string) data_get($extra, 'fulfillment_photo', ''));
        if ($path !== '' && Storage::disk('private')->exists($path)) {
            Storage::disk('private')->delete($path);
        }
        unset($extra['fulfillment_photo'], $extra['fulfillment_photo_uploaded_at']);
        $order->update(['extra' => $extra]);

        return redirect()
            ->back()
            ->with('success', '实拍照片已删除');
    }

    public function showShoppingReceipt(Order $order)
    {
        if (!$order->hasShoppingReceipt()) {
            abort(404, '购物凭据尚未上传');
        }

        $path = trim((string) $order->shopping_receipt);
        $disk = Storage::disk('private');

        return $disk->response($path, 'receipt-'.$order->no.'.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
        ]);
    }

    public function uploadShoppingReceipt(Order $order, Request $request)
    {
        if (!$order->paid_at) {
            throw new InvalidRequestException('仅已支付订单可上传购物凭据');
        }

        $this->validate($request, [
            'receipt' => ['required', 'file', 'max:15360', 'mimes:pdf,jpeg,jpg,png'],
        ], [], [
            'receipt' => '购物凭据',
        ]);

        $file = $request->file('receipt');
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $extension = 'pdf';
        }
        $filename = Str::random(32).'.'.$extension;
        $path = $file->storeAs('orders/receipts/'.$order->id, $filename, 'private');

        $extra = $order->extra ?: [];
        $oldPath = trim((string) data_get($extra, 'shopping_receipt', ''));
        if ($oldPath !== '' && $oldPath !== $path && Storage::disk('private')->exists($oldPath)) {
            Storage::disk('private')->delete($oldPath);
        }
        $extra['shopping_receipt'] = $path;
        $extra['shopping_receipt_uploaded_at'] = now()->toDateTimeString();
        $order->update(['extra' => $extra]);

        return redirect()
            ->back()
            ->with('success', '购物凭据已上传，用户可在订单详情下载。');
    }

    public function deleteShoppingReceipt(Order $order)
    {
        $extra = $order->extra ?: [];
        $path = trim((string) data_get($extra, 'shopping_receipt', ''));
        if ($path !== '' && Storage::disk('private')->exists($path)) {
            Storage::disk('private')->delete($path);
        }
        unset($extra['shopping_receipt'], $extra['shopping_receipt_uploaded_at']);
        $order->update(['extra' => $extra]);

        return redirect()
            ->back()
            ->with('success', '购物凭据已删除');
    }

    public function ship(Order $order, Request $request)
    {
        if (!$order->paid_at) {
            throw new InvalidRequestException('该订单未付款');
        }
        if ($order->refund_status === Order::REFUND_STATUS_SUCCESS) {
            throw new InvalidRequestException('该订单已退款，无法更新物流');
        }

        $shipStatus = $request->input('ship_status', Order::SHIP_STATUS_DELIVERED);
        $rules = [
            'ship_status' => ['required', 'in:'.implode(',', [
                Order::SHIP_STATUS_PENDING,
                Order::SHIP_STATUS_DELIVERED,
                Order::SHIP_STATUS_RECEIVED,
            ])],
        ];
        if (in_array($shipStatus, [Order::SHIP_STATUS_DELIVERED, Order::SHIP_STATUS_RECEIVED], true)) {
            if (is_site_mode_a()) {
                $rules['express_company'] = ['required', 'string', 'in:'.implode(',', site_express_carrier_options())];
            } else {
                $rules['express_company'] = ['required', 'string', 'max:255'];
            }
            $rules['express_no'] = ['required', 'string', 'max:255'];
        } else {
            $rules['express_company'] = ['nullable', 'string', 'max:255'];
            $rules['express_no'] = ['nullable', 'string', 'max:255'];
        }

        $data = $this->validate($request, $rules, [], [
            'ship_status' => '发货状态',
            'express_company' => is_site_mode_b() ? '代购人' : '物流公司',
            'express_no' => is_site_mode_b() ? '转寄单号' : '物流单号',
        ]);

        $payload = ['ship_status' => $shipStatus];
        if (in_array($shipStatus, [Order::SHIP_STATUS_DELIVERED, Order::SHIP_STATUS_RECEIVED], true)) {
            $payload['ship_data'] = [
                'express_company' => trim($data['express_company']),
                'express_no' => trim($data['express_no']),
            ];
        } else {
            $payload['ship_data'] = null;
        }

        $wasPending = $order->ship_status === Order::SHIP_STATUS_PENDING;
        $order->update($payload);
        $order->refresh();

        if ($shipStatus === Order::SHIP_STATUS_DELIVERED
            && $wasPending
            && $order->user
            && is_site_mode_a()) {
            $order->user->notify(new \App\Notifications\OrderShippedNotification($order));
        }

        return redirect()
            ->back()
            ->with('success', '物流信息已更新，用户端将同步显示最新状态');
    }

    protected function grid()
    {
        return Admin::grid(Order::class, function (Grid $grid) {
            // 只展示已支付的订单，并且默认按支付时间倒序排序
            $grid->model()->with('user')->whereNotNull('paid_at')->orderBy('paid_at', 'desc');

            $grid->no('订单流水号');
            $grid->column('buyer_label', '买家昵称')->display(function () {
                return $this->buyer_label;
            });
            $grid->total_amount('订单实付金额')->sortable();
            $grid->column('ems_summary', 'EMS/烟草')->display(function () {
                $fee = (array) data_get($this->extra, 'fee_details', []);
                $tobacco = (array) data_get($this->extra, 'tobacco_summary', []);
                $mode = data_get($fee, 'shipping_mode', data_get($this->extra, 'shipping_mode', ''));
                $parts = [];
                if ($mode) {
                    $parts[] = \App\Services\ShippingModeService::options()[$mode] ?? $mode;
                }
                if ($w = data_get($fee, 'ems_weight_grams')) {
                    $parts[] = $w.'g';
                }
                if ($s = data_get($tobacco, 'total_cigarette_sticks')) {
                    $parts[] = $s.'支';
                }
                if ($r = data_get($tobacco, 'total_rolling_tobacco_grams')) {
                    $parts[] = round($r / 1000, 2).'kg烟丝';
                }

                return $parts ? implode(' / ', $parts) : '—';
            });
            $grid->paid_at('支付时间')->sortable();
            $grid->ship_status('发货状态')->display(function($value) {
                return Order::$shipStatusMap[$value];
            });
            $grid->refund_status('退款状态')->display(function($value) {
                return Order::$refundStatusMap[$value];
            });
            $grid->disableCreateButton();
            $grid->disableExport();

            $grid->actions(function ($actions) {
                $actions->disableDelete();
                $actions->disableEdit();
                $actions->disableView();
                $actions->prepend(
                    '<a href="'.route('admin.orders.show', ['order' => $actions->getKey()]).'" class="btn btn-xs btn-primary" style="margin-right:4px;">'
                    .'<i class="fa fa-eye"></i> 查看</a>'
                );
            });

            $grid->tools(function ($tools) {
                $tools->batch(function ($batch) {
                    $batch->disableDelete();
                });
                $tools->append(view('admin.partials.export_dropdown', [
                    'exportBaseUrl' => route('admin.orders.export'),
                    'scopeOptions' => OrderAdminExportService::scopeOptions(),
                    'dropdownLabel' => '导出订单',
                ]));
                $tools->append(
                    '<button type="button" class="btn btn-sm btn-warning btn-batch-orders-start-processing" style="margin-left:6px;">'
                    .'<i class="fa fa-play"></i> 批量开始处理</button>'
                );
            });

            Admin::script(<<<'JS'
$(document).off('click', '.btn-batch-orders-start-processing').on('click', '.btn-batch-orders-start-processing', function () {
    var ids = [];
    $('.grid-row-checkbox').each(function () {
        var $cb = $(this);
        if (!$cb.prop('checked') && !$cb.parent().hasClass('checked')) {
            return;
        }
        var id = $cb.data('id');
        if (id) {
            ids.push(id);
        }
    });
    if (!ids.length) {
        alert('请先勾选订单');
        return;
    }
    if (!confirm('将选中且处于「待处理 S1」的订单批量标记为开始处理（S2），继续？')) {
        return;
    }
    $.post('{{ admin_url('orders/batch/start-processing') }}', {
        _token: LA.token,
        ids: ids
    }, function (ret) {
        if (ret.status) {
            $.pjax.reload('#pjax-container');
            alert(ret.message || '已完成');
            return;
        }
        alert(ret.message || '操作失败');
    });
});
JS
            );
        });
    }

    public function batchStartProcessing(Request $request, \App\Services\OrderBatchService $batch)
    {
        try {
            $result = $batch->batchStartProcessing(
                (array) $request->input('ids', []),
                app(OrderFulfillmentService::class)
            );

            return response()->json(array_merge(['status' => true], $result));
        } catch (InvalidRequestException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function markLogisticsWarehouse(Order $order, OrderFulfillmentService $fulfillment)
    {
        $fulfillment->markPackageAtLogisticsWarehouse($order);

        return redirect()
            ->back()
            ->with('success', '已标记：包裹已送往物流仓库。此后原则上不可取消订单。');
    }

    public function executeRefund(Order $order, ExecuteOrderRefundRequest $request)
    {
        if (!is_site_mode_a()) {
            throw new InvalidRequestException('当前站点不支持此退款操作。');
        }

        $adminId = optional(\Encore\Admin\Facades\Admin::user())->id;
        app(\App\Services\OrderRefundService::class)->executeAdminRefund(
            $order,
            $request->only([
                'reason_code',
                'reason_note',
                'supplier_cannot_supply',
                's4_special_approval',
                's4_refund_ratio',
            ]),
            $adminId
        );

        return redirect()
            ->back()
            ->with('success', '退款请求已提交，请刷新查看退款状态。');
    }

    public function handleRefund(Order $order, ExecuteOrderRefundRequest $request)
    {
        return $this->executeRefund($order, $request);
    }
}
