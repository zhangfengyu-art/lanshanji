<?php

namespace App\Admin\Controllers;

use App\Exceptions\InternalException;
use App\Models\Order;
use App\Services\AdminOrderZipExport;
use App\Services\OrderAdminExportService;
use App\Services\OrderStockPrepExportService;
use App\Services\OrderFulfillmentService;
use Illuminate\Http\Request;
use App\Exceptions\InvalidRequestException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;
use App\Http\Requests\Admin\ExecuteOrderRefundRequest;
use App\Admin\Concerns\RespondsWithAdminBatchJson;

class OrdersController extends Controller
{
    use ModelForm;
    use RespondsWithAdminBatchJson;

    protected function redirectToOrderShow(Order $order, $successMessage = null, $errorMessage = null)
    {
        $redirect = redirect()->route('admin.orders.show', ['order' => $order->id]);

        if ($successMessage !== null && $successMessage !== '') {
            $redirect->with('success', admin_flash_success($successMessage));
        }

        if ($errorMessage !== null && $errorMessage !== '') {
            $redirect->with('error', admin_flash_error($errorMessage));
        }

        return $redirect;
    }

    protected function handleFulfillmentAction(Order $order, callable $action, $successMessage)
    {
        try {
            $action();

            return $this->redirectToOrderShow($order->fresh(), $successMessage);
        } catch (InvalidRequestException $e) {
            return $this->redirectToOrderShow($order, null, $e->getMessage());
        } catch (\Throwable $e) {
            \Log::error('订单履约操作失败', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->redirectToOrderShow($order, null, '操作失败：'.$e->getMessage());
        }
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
        $order->load(['user', 'items.product', 'items.productSku', 'couponCode']);

        try {
            $feeBreakdown = \App\Services\OrderFeeBreakdownPresenter::forOrder($order);
        } catch (\Throwable $e) {
            \Log::error('订单金额明细生成失败', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
            $feeBreakdown = [];
        }

        $refundPreview = null;
        if (is_site_mode_a()) {
            try {
                $refundPreview = app(\App\Services\OrderRefundPolicyService::class)->previewAdminRefund($order);
            } catch (\Throwable $e) {
                \Log::error('订单退款预览生成失败', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
        $refundReasons = config('order_refund.admin_reasons', []);

        return Admin::content(function (Content $content) use ($order, $refundPreview, $refundReasons, $feeBreakdown) {
            $content->header('查看订单');
            // body 方法可以接受 Laravel 的视图作为参数
            $content->body(view('admin.orders.show', [
                'order' => $order,
                'refundPreview' => $refundPreview,
                'refundReasons' => $refundReasons,
                'feeBreakdown' => $feeBreakdown,
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

        $fulfillment = app(OrderFulfillmentService::class);

        return AdminOrderZipExport::download(
            OrderAdminExportService::filename($scope),
            OrderAdminExportService::headers(),
            function ($emitRow) use ($scope, $fulfillment) {
                OrderAdminExportService::exportRowsWithProducer($scope, $fulfillment, $emitRow);
            },
            [
                'text_columns' => OrderAdminExportService::TEXT_COLUMN_INDEXES,
                'image_columns' => OrderAdminExportService::IMAGE_COLUMN_INDEXES,
            ]
        );
    }

    public function exportStockPrep(Request $request)
    {
        $scope = (string) $request->query('scope', 'all');
        $options = OrderStockPrepExportService::scopeOptions();
        if (!array_key_exists($scope, $options)) {
            $scope = 'all';
        }

        $fulfillment = app(OrderFulfillmentService::class);

        return AdminOrderZipExport::download(
            OrderStockPrepExportService::filename($scope),
            OrderStockPrepExportService::headers(),
            function ($emitRow) use ($scope, $fulfillment) {
                OrderStockPrepExportService::exportRowsWithProducer($scope, $fulfillment, $emitRow);
            },
            [
                'text_columns' => OrderStockPrepExportService::TEXT_COLUMN_INDEXES,
                'image_columns' => OrderStockPrepExportService::IMAGE_COLUMN_INDEXES,
                'html_basename' => '备货表.html',
                'footer_note' => '请解压本 ZIP 后，用 Excel 或 WPS 打开「备货表.html」。本表仅汇总香烟与加热烟按包采购数量，不含用户地址与身份信息；已退款成功订单不计入。',
            ]
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
            ->with('success', admin_flash_success('实拍照片已上传；订单已进入备货/打包阶段（S3），用户不可再自助改址。'));
    }

    public function startProcessing(Order $order, OrderFulfillmentService $fulfillment)
    {
        return $this->handleFulfillmentAction($order, function () use ($order, $fulfillment) {
            $fulfillment->startProcessing($order);
        }, '订单已开始处理（S2），用户不可再自助改址。');
    }

    public function lockOrder(Order $order, OrderFulfillmentService $fulfillment)
    {
        return $this->handleFulfillmentAction($order, function () use ($order, $fulfillment) {
            $fulfillment->lockOrder($order);
        }, '订单已锁定（S3），用户不可再自助改址。');
    }

    public function unlockOrder(Order $order, OrderFulfillmentService $fulfillment)
    {
        return $this->handleFulfillmentAction($order, function () use ($order, $fulfillment) {
            if ($order->hasFulfillmentPhoto()) {
                throw new InvalidRequestException('已上传履约照片，不可解除锁定。');
            }

            $fulfillment->unlockOrder($order);
        }, '已解除锁定；若未开始处理则回到待处理（S1），否则为处理中（S2）。');
    }

    public function markManualOfflineRefund(Order $order, Request $request)
    {
        try {
            if (!is_site_mode_a()) {
                throw new InvalidRequestException('当前站点不支持此操作。');
            }

            $this->validate($request, [
                'note' => ['nullable', 'string', 'max:500'],
            ], [], [
                'note' => '备注',
            ]);

            $adminId = optional(Admin::user())->id;
            app(\App\Services\OrderRefundService::class)->markManualOfflineRefunded(
                $order,
                (string) $request->input('note', ''),
                $adminId
            );

            return $this->redirectToOrderShow(
                $order->fresh(),
                '已标记为线下私退完结；未向支付渠道发起退款，订单已关闭。'
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.orders.show', ['order' => $order->id])
                ->withInput()
                ->withErrors($e->errors());
        } catch (InvalidRequestException $e) {
            return $this->redirectToOrderShow($order, null, $e->getMessage());
        } catch (\Throwable $e) {
            \Log::error('线下私退完结失败', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->redirectToOrderShow(
                $order,
                null,
                '操作失败：'.$e->getMessage()
            );
        }
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
            ->with('success', admin_flash_success('实拍照片已删除'));
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
            ->with('success', admin_flash_success('物流信息已更新，用户端将同步显示最新状态'));
    }

    protected function grid()
    {
        $controller = $this;

        return Admin::grid(Order::class, function (Grid $grid) use ($controller) {
            // 只展示已支付的订单，并且默认按支付时间倒序排序
            $grid->model()->with('user')->whereNotNull('paid_at')->orderBy('paid_at', 'desc');

            $orderNo = trim((string) request()->query('order_no', ''));
            if ($orderNo !== '') {
                $grid->model()->where('no', 'like', '%'.$orderNo.'%');
            }

            $fulfillmentStage = strtoupper(trim((string) request()->query('fulfillment_stage', '')));
            if (is_site_mode_a() && $fulfillmentStage !== '') {
                app(OrderFulfillmentService::class)->applyStageFilter($grid->model(), $fulfillmentStage);
            }

            $grid->no('订单流水号');
            $grid->column('buyer_label', '买家昵称')->display(function () {
                $label = e($this->buyer_label);
                if (!$this->user_id) {
                    return $label;
                }

                $url = route('admin.users.show', ['id' => $this->user_id]);

                return '<a href="'.e($url).'">'.$label.'</a>';
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
            if (is_site_mode_a()) {
                $grid->column('fulfillment_stage', '履约阶段')->display(function () {
                    $fulfillment = app(OrderFulfillmentService::class);
                    $stage = $fulfillment->resolveStage($this);
                    $label = $fulfillment->stageLabel($this);
                    $colors = [
                        OrderFulfillmentService::STAGE_S0 => 'default',
                        OrderFulfillmentService::STAGE_S1 => 'default',
                        OrderFulfillmentService::STAGE_S2 => 'warning',
                        OrderFulfillmentService::STAGE_S3 => 'info',
                        OrderFulfillmentService::STAGE_S4 => 'success',
                    ];
                    $color = $colors[$stage] ?? 'default';

                    return '<span class="label label-'.$color.'" style="font-size:12px;min-width:34px;display:inline-block;">'
                        .e($stage)
                        .'</span> <span style="font-size:12px;color:#475569;">'
                        .e($label)
                        .'</span>';
                });
            }
            $grid->payment_method('支付方式')->display(function ($value) {
                if ($value === 'wechat') {
                    return '微信支付';
                }
                if ($value === 'alipay') {
                    return '支付宝';
                }

                return $value ?: '—';
            });
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

            $grid->tools(function ($tools) use ($controller) {
                $orderNo = trim((string) request()->query('order_no', ''));
                $fulfillmentStage = strtoupper(trim((string) request()->query('fulfillment_stage', '')));
                $tools->append($controller->renderOrderSearchTool($orderNo, $fulfillmentStage));
                $tools->batch(function ($batch) {
                    $batch->disableDelete();
                });
                $tools->append(view('admin.partials.export_dropdown', [
                    'exportBaseUrl' => route('admin.orders.export'),
                    'scopeOptions' => OrderAdminExportService::scopeOptions(),
                    'dropdownLabel' => '导出订单',
                ]));
                if (is_site_mode_a()) {
                    $tools->append(view('admin.partials.export_dropdown', [
                        'exportBaseUrl' => route('admin.orders.export_stock_prep'),
                        'scopeOptions' => OrderStockPrepExportService::scopeOptions(),
                        'dropdownLabel' => '导出备货表',
                        'downloadHint' => '下载 ZIP，解压后用 Excel/WPS 打开「备货表.html」',
                    ]));
                }
                if (is_site_mode_a()) {
                    $tools->append(view('admin.orders._batch_tools'));
                }
            });

            if (is_site_mode_a()) {
                Admin::script(view('admin.partials._batch_helper_script')->render());
                Admin::script(view('admin.orders._batch_tools_script')->render());
            }
        });
    }

    protected function renderOrderSearchTool($orderNo = '', $fulfillmentStage = '')
    {
        $orderNo = trim((string) $orderNo);
        $fulfillmentStage = strtoupper(trim((string) $fulfillmentStage));

        $stageSelect = '';
        if (is_site_mode_a()) {
            $options = OrderFulfillmentService::stageFilterOptions();
            $stageSelect = '<select name="fulfillment_stage" class="form-control input-sm" style="width:132px;">';
            foreach ($options as $value => $label) {
                $selected = ((string) $value === $fulfillmentStage) ? ' selected' : '';
                $stageSelect .= '<option value="'.e($value).'"'.$selected.'>'.e($label).'</option>';
            }
            $stageSelect .= '</select>';
        }

        $hasFilter = $orderNo !== '' || $fulfillmentStage !== '';

        return '<form method="GET" action="" class="form-inline" style="display:inline-block;margin-right:10px;">'
            .$stageSelect
            .'<div class="input-group input-group-sm" style="width:280px;margin-left:6px;">'
            .'<input type="text" class="form-control" name="order_no" value="'.e($orderNo).'" placeholder="搜索订单流水号">'
            .'<span class="input-group-btn">'
            .'<button type="submit" class="btn btn-primary">筛选</button>'
            .'</span>'
            .'</div>'
            .'</form>'
            .($hasFilter ? '<a class="btn btn-sm btn-default" href="'.e(url()->current()).'">重置</a>' : '');
    }

    public function batchStartProcessing(Request $request, \App\Services\OrderBatchService $batch)
    {
        return $this->batchTry(function () use ($request, $batch) {
            return $batch->batchStartProcessing(
                $this->batchIds($request, '请先勾选订单'),
                app(OrderFulfillmentService::class)
            );
        });
    }

    public function batchLockOrders(Request $request, \App\Services\OrderBatchService $batch)
    {
        return $this->batchTry(function () use ($request, $batch) {
            return $batch->batchLockOrders(
                $this->batchIds($request, '请先勾选订单'),
                app(OrderFulfillmentService::class)
            );
        });
    }

    public function batchUnlockOrders(Request $request, \App\Services\OrderBatchService $batch)
    {
        return $this->batchTry(function () use ($request, $batch) {
            return $batch->batchUnlockOrders(
                $this->batchIds($request, '请先勾选订单'),
                app(OrderFulfillmentService::class)
            );
        });
    }

    public function batchMarkLogisticsWarehouse(Request $request, \App\Services\OrderBatchService $batch)
    {
        return $this->batchTry(function () use ($request, $batch) {
            return $batch->batchMarkLogisticsWarehouse(
                $this->batchIds($request, '请先勾选订单'),
                app(OrderFulfillmentService::class)
            );
        });
    }

    public function markLogisticsWarehouse(Order $order, OrderFulfillmentService $fulfillment)
    {
        return $this->handleFulfillmentAction($order, function () use ($order, $fulfillment) {
            $fulfillment->markPackageAtLogisticsWarehouse($order);
        }, '已标记：包裹已送往物流仓库。此后原则上不可取消订单。');
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
            ->with('success', admin_flash_success('退款请求已提交，请刷新查看退款状态。'));
    }

    public function handleRefund(Order $order, ExecuteOrderRefundRequest $request)
    {
        return $this->executeRefund($order, $request);
    }
}
