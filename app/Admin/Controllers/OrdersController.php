<?php

namespace App\Admin\Controllers;

use App\Exceptions\InternalException;
use App\Models\Order;
use App\Services\AdminOrderPdfExport;
use App\Services\OrderAdminExportService;
use App\Services\OrderStockPrepExportService;
use App\Services\ShippingModeService;
use App\Services\OrderFulfillmentPhotoService;
use App\Services\OrderFulfillmentService;
use App\Services\ImageJpegConverter;
use Illuminate\Http\Request;
use App\Exceptions\InvalidRequestException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
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

            if (is_site_mode_a() && $order->paid_at) {
                Admin::script(view('admin.orders._fulfillment_photo_upload_script')->render());
            }
        });
    }

    public function export(Request $request)
    {
        $scope = (string) $request->query('scope', 'all');
        $legacyScopes = [
            's1_s2' => 'pending',
            's1_pending' => 'pending',
        ];
        $scope = $legacyScopes[$scope] ?? $scope;
        $scopeOptions = OrderAdminExportService::scopeOptions();
        if (!array_key_exists($scope, $scopeOptions)) {
            $scope = 'all';
        }

        $fulfillment = app(OrderFulfillmentService::class);
        $scopeLabel = $scopeOptions[$scope];

        return AdminOrderPdfExport::download(
            OrderAdminExportService::pdfFilename($scope),
            OrderAdminExportService::headers(),
            function ($emitRow) use ($scope, $fulfillment) {
                OrderAdminExportService::exportRowsWithProducer($scope, $fulfillment, $emitRow);
            },
            OrderAdminExportService::pdfExportOptions($scopeLabel)
        );
    }

    public function exportStockPrep(Request $request)
    {
        $scope = (string) $request->query('scope', 'pending');
        $legacyScopes = [
            'pending_fulfillment' => 'history_total',
            's1' => 'pending',
            's2' => 'pending',
            's3' => 'pending',
            's1_s2' => 'pending',
        ];
        $scope = $legacyScopes[$scope] ?? $scope;
        $scopeOptions = OrderStockPrepExportService::scopeOptions();
        if (!array_key_exists($scope, $scopeOptions)) {
            $scope = 'pending';
        }

        $fulfillment = app(OrderFulfillmentService::class);
        $scopeLabel = $scopeOptions[$scope];

        return AdminOrderPdfExport::download(
            OrderStockPrepExportService::pdfFilename($scope),
            OrderStockPrepExportService::headers(),
            function ($emitRow) use ($scope, $fulfillment) {
                OrderStockPrepExportService::exportRowsWithProducer($scope, $fulfillment, $emitRow);
            },
            OrderStockPrepExportService::pdfExportOptions($scope, $scopeLabel)
        );
    }

    public function showFulfillmentPhoto(Order $order, Request $request, OrderFulfillmentPhotoService $photos)
    {
        if (!$order->hasFulfillmentPhoto()) {
            abort(404, '实拍照片尚未上传');
        }

        $path = $order->fulfillment_photo;
        $disk = Storage::disk('private');
        $maxEdge = max(0, (int) $request->query('max_edge', 0));

        if ($maxEdge > 0) {
            $maxEdge = min(480, $maxEdge);
            $thumbPath = $photos->thumbCachePath($path, $maxEdge);
            $sourcePath = $disk->path($path);

            if (!is_file($thumbPath) || filemtime($thumbPath) < filemtime($sourcePath)) {
                $converter = app(ImageJpegConverter::class);
                if (!$converter->saveResizedJpeg($sourcePath, $thumbPath, $maxEdge, 80)) {
                    return $disk->response($path, 'fulfillment-'.$order->no.'.jpg', [
                        'Content-Type' => $disk->mimeType($path) ?: 'image/jpeg',
                    ]);
                }
            }

            return response()->file($thumbPath, [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'private, max-age=86400',
            ]);
        }

        return $disk->response($path, 'fulfillment-'.$order->no.'.jpg', [
            'Content-Type' => $disk->mimeType($path) ?: 'image/jpeg',
        ]);
    }

    public function uploadFulfillmentPhoto(Order $order, Request $request, OrderFulfillmentPhotoService $photos)
    {
        $successMessage = '实拍照片已上传；订单已进入备货/打包阶段（S3），用户不可再自助改址。';

        try {
            $this->validate($request, [
                'photo' => ['required', 'file', 'max:'.OrderFulfillmentPhotoService::MAX_UPLOAD_KB],
            ], [], [
                'photo' => '实拍照片',
            ]);

            $photos->store($order, $request->file('photo'));
        } catch (ValidationException $e) {
            return $this->respondFulfillmentPhotoUpload($request, false, collect($e->errors())->flatten()->first() ?: '上传校验失败');
        } catch (InvalidRequestException $e) {
            return $this->respondFulfillmentPhotoUpload($request, false, $e->getMessage());
        } catch (\Throwable $e) {
            \Log::error('实拍图上传失败', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return $this->respondFulfillmentPhotoUpload($request, false, '上传失败：'.$e->getMessage());
        }

        return $this->respondFulfillmentPhotoUpload($request, true, $successMessage, $order->fresh());
    }

    protected function respondFulfillmentPhotoUpload(Request $request, $ok, $message, Order $order = null)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $payload = [
                'status' => (bool) $ok,
                'message' => (string) $message,
            ];

            if ($ok && $order) {
                $payload['photo_url'] = route('admin.orders.fulfillment_photo', [
                    'order' => $order->id,
                    'max_edge' => 96,
                ]);
                $payload['photo_full_url'] = route('admin.orders.fulfillment_photo', ['order' => $order->id]);
                $payload['has_photo'] = $order->hasFulfillmentPhoto();
            }

            return response()->json($payload, $ok ? 200 : 422);
        }

        if ($ok) {
            return redirect()
                ->back()
                ->with('success', admin_flash_success($message));
        }

        return redirect()
            ->back()
            ->with('error', admin_flash_error($message));
    }

    public function startProcessing(Order $order, OrderFulfillmentService $fulfillment)
    {
        return $this->handleFulfillmentAction($order, function () use ($order, $fulfillment) {
            $fulfillment->startProcessing($order);
        }, '订单已标记开始处理，用户不可再自助改址。');
    }

    public function lockOrder(Order $order, OrderFulfillmentService $fulfillment)
    {
        return $this->handleFulfillmentAction($order, function () use ($order, $fulfillment) {
            $fulfillment->enterStockPrep($order);
        }, '订单已进入备货/打包（S3），用户不可再自助改址。');
    }

    public function revertToPending(Order $order, OrderFulfillmentService $fulfillment)
    {
        return $this->handleFulfillmentAction($order, function () use ($order, $fulfillment) {
            $fulfillment->revertToPending($order);
        }, '订单已恢复为未受理状态，用户可继续自助改址。');
    }

    public function unlockOrder(Order $order, OrderFulfillmentService $fulfillment)
    {
        return $this->handleFulfillmentAction($order, function () use ($order, $fulfillment) {
            $fulfillment->revertFromStockPrep($order);
        }, '订单已退回上一履约阶段。');
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

    public function deleteFulfillmentPhoto(Order $order, OrderFulfillmentPhotoService $photos)
    {
        $extra = $order->extra ?: [];
        $path = trim((string) data_get($extra, 'fulfillment_photo', ''));
        if ($path !== '') {
            $photos->deleteStoredVariants($path);
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

        $expressCompany = trim((string) data_get($data, 'express_company', ''));
        $expressNo = trim((string) data_get($data, 'express_no', ''));

        return $this->applyOrderShipment(
            $order,
            $shipStatus,
            $expressCompany,
            $expressNo,
            '物流信息已更新，用户端将同步显示最新状态'
        );
    }

    public function quickShip(Order $order, Request $request)
    {
        try {
            if (!$order->paid_at) {
                throw new InvalidRequestException('该订单未付款');
            }
            if ($order->refund_status === Order::REFUND_STATUS_SUCCESS) {
                throw new InvalidRequestException('该订单已退款，无法发货');
            }

            $this->validate($request, [
                'express_no' => ['required', 'string', 'max:255'],
                'express_company' => ['nullable', 'string', 'max:255'],
            ], [], [
                'express_no' => is_site_mode_b() ? '转寄单号' : '物流单号',
                'express_company' => is_site_mode_b() ? '代购人' : '物流公司',
            ]);

            $expressNo = trim((string) $request->input('express_no', ''));
            if ($expressNo === '') {
                throw new InvalidRequestException(is_site_mode_b() ? '请填写转寄单号' : '请填写物流单号');
            }

            $expressCompany = trim((string) $request->input('express_company', ''));
            if ($expressCompany === '') {
                $expressCompany = $this->defaultExpressCarrierForOrder($order);
            }

            if (is_site_mode_a()) {
                $allowed = site_express_carrier_options();
                if (!in_array($expressCompany, $allowed, true)) {
                    $expressCompany = site_express_default_carrier();
                }
            }

            return $this->applyOrderShipment(
                $order,
                Order::SHIP_STATUS_DELIVERED,
                $expressCompany,
                $expressNo,
                '物流单号已保存，订单已标记为已发货'
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        } catch (InvalidRequestException $e) {
            return redirect()->back()->withInput()->with('error', admin_flash_error($e->getMessage()));
        } catch (\Throwable $e) {
            \Log::error('快捷发货失败', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->withInput()->with('error', admin_flash_error('发货失败：'.$e->getMessage()));
        }
    }

    protected function applyOrderShipment(Order $order, $shipStatus, $expressCompany, $expressNo, $successMessage)
    {
        $payload = ['ship_status' => $shipStatus];
        if (in_array($shipStatus, [Order::SHIP_STATUS_DELIVERED, Order::SHIP_STATUS_RECEIVED], true)) {
            $payload['ship_data'] = [
                'express_company' => $expressCompany,
                'express_no' => $expressNo,
            ];
        } else {
            $payload['ship_data'] = null;
        }

        $wasPending = $order->ship_status === Order::SHIP_STATUS_PENDING;
        $order->update($payload);
        $order->refresh();

        $fulfillment = app(OrderFulfillmentService::class);
        if (in_array($shipStatus, [Order::SHIP_STATUS_DELIVERED, Order::SHIP_STATUS_RECEIVED], true)) {
            $fulfillment->syncShippedStage($order);
        } else {
            $fulfillment->syncUnshippedStage($order);
        }
        $order->refresh();

        if ($shipStatus === Order::SHIP_STATUS_DELIVERED
            && $wasPending
            && $order->user
            && is_site_mode_a()) {
            try {
                $order->user->notify(new \App\Notifications\OrderShippedNotification($order));
            } catch (\Throwable $e) {
                \Log::warning('发货通知邮件发送失败', [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return redirect()
            ->back()
            ->with('success', admin_flash_success($successMessage));
    }

    protected function defaultExpressCarrierForOrder(Order $order)
    {
        $mode = trim((string) data_get($order->extra, 'fee_details.shipping_mode', data_get($order->extra, 'shipping_mode', '')));
        $options = site_express_carrier_options();

        if ($mode === ShippingModeService::MODE_EMS) {
            foreach ($options as $option) {
                if (stripos($option, 'EMS') !== false) {
                    return $option;
                }
            }
        }

        return site_express_default_carrier();
    }

    protected function grid()
    {
        $controller = $this;

        return Admin::grid(Order::class, function (Grid $grid) use ($controller) {
            // 只展示已支付的订单，并且默认按支付时间倒序排序
            $grid->model()->with('user')->whereNotNull('paid_at')->orderBy('paid_at', 'desc');

            $orderNo = trim((string) request()->query('order_no', ''));
            if ($orderNo !== '') {
                $like = '%'.$orderNo.'%';
                $grid->model()->where(function ($query) use ($like) {
                    $query->where('no', 'like', $like)
                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(address, '$.contact_name')) LIKE ?", [$like]);
                });
            }

            $fulfillmentStage = strtoupper(trim((string) request()->query('fulfillment_stage', '')));
            if (is_site_mode_a() && $fulfillmentStage !== '') {
                app(OrderFulfillmentService::class)->applyStageFilter($grid->model(), $fulfillmentStage);
            }

            $grid->no('订单流水号');
            $grid->column('buyer_real_name', '买家姓名')->display(function () {
                $name = trim((string) data_get($this->address, 'contact_name', ''));
                $label = $name !== '' ? e($name) : '—';
                if (!$this->user_id) {
                    return $label;
                }

                $url = route('admin.users.show', ['id' => $this->user_id]);

                return '<a href="'.e($url).'">'.$label.'</a>';
            });
            $grid->total_amount('订单实付')->sortable();
            $grid->column('ems_summary', 'EMS/烟草')->display(function () {
                $fee = (array) data_get($this->extra, 'fee_details', []);
                $tobacco = (array) data_get($this->extra, 'tobacco_summary', []);
                $mode = data_get($fee, 'shipping_mode', data_get($this->extra, 'shipping_mode', ''));
                $parts = [];
                if ($mode) {
                    $modeLabel = \App\Services\ShippingModeService::options()[$mode] ?? $mode;
                    if ($modeLabel === 'EMS 自缴税') {
                        $modeLabel = 'EMS';
                    }
                    $parts[] = $modeLabel;
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
            if (is_site_mode_a()) {
                $grid->column('fulfillment_photo_cell', '实拍图')->display(function () {
                    return view('admin.orders._fulfillment_photo_cell', ['order' => $this])->render();
                });
                $grid->column('fulfillment_stage', '履约阶段')->display(function () {
                    $presentation = app(OrderFulfillmentService::class)->adminGridStagePresentation($this);

                    return '<strong style="color:'.e($presentation['color']).';font-weight:700;">'
                        .e($presentation['label'])
                        .'</strong>';
                });
            }
            $grid->paid_at('支付时间')->sortable();
            $grid->payment_method('支付方式')->display(function ($value) {
                if ($value === 'wechat') {
                    return '微信支付';
                }
                if ($value === 'alipay') {
                    return '支付宝';
                }

                return $value ?: '—';
            });
            $grid->column('quick_ship_cell', '物流单号')->display(function () {
                return view('admin.orders._quick_ship_cell', ['order' => $this])->render();
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
                    ]));
                }
                if (is_site_mode_a()) {
                    $tools->append(view('admin.orders._batch_tools'));
                }
            });

            if (is_site_mode_a()) {
                Admin::script(view('admin.partials._batch_helper_script')->render());
                Admin::script(view('admin.orders._batch_tools_script')->render());
                Admin::script(view('admin.orders._fulfillment_photo_upload_script')->render());
                Admin::script(view('admin.orders._quick_ship_script')->render());
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
            .'<input type="text" class="form-control" name="order_no" value="'.e($orderNo).'" placeholder="搜索订单流水号或买家姓名">'
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
            return $batch->batchEnterStockPrep(
                $this->batchIds($request, '请先勾选订单'),
                app(OrderFulfillmentService::class)
            );
        });
    }

    public function batchUnlockOrders(Request $request, \App\Services\OrderBatchService $batch)
    {
        return $this->batchTry(function () use ($request, $batch) {
            return $batch->batchRevertFromStockPrep(
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
