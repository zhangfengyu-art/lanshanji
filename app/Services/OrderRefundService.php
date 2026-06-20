<?php

namespace App\Services;

use App\Exceptions\InternalException;
use App\Exceptions\InvalidRequestException;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use App\Services\ExchangeRateService;

class OrderRefundService
{
    /** @var OrderFulfillmentService */
    protected $fulfillment;

    /** @var OrderRefundPolicyService */
    protected $policy;

    /** @var CrossSitePaymentService */
    protected $crossSitePayment;

    public function __construct(
        OrderFulfillmentService $fulfillment,
        OrderRefundPolicyService $policy,
        CrossSitePaymentService $crossSitePayment
    ) {
        $this->fulfillment = $fulfillment;
        $this->policy = $policy;
        $this->crossSitePayment = $crossSitePayment;
    }

    public function hasProcessingStarted(Order $order)
    {
        if (trim((string) data_get($order->extra, 'processing_started_at', '')) !== '') {
            return true;
        }

        // 已上传实拍图视为开始处理（兼容历史订单）
        return trim((string) data_get($order->extra, 'fulfillment_photo', '')) !== '';
    }

    public function canSelfInstantRefund(Order $order)
    {
        if (!is_site_mode_a()) {
            return false;
        }

        if (!$order->paid_at || $order->closed) {
            return false;
        }

        if ($order->refund_status !== Order::REFUND_STATUS_PENDING) {
            return false;
        }

        if ($this->hasProcessingStarted($order)) {
            return false;
        }

        return $this->fulfillment->resolveStage($order) === OrderFulfillmentService::STAGE_S1;
    }

    /**
     * 后台标记「开始处理」后：隐藏自助退款，仅展示客户反馈入口。
     */
    public function shouldUseRefundFeedback(Order $order)
    {
        if (!is_site_mode_a()) {
            return false;
        }

        if (!$order->paid_at || $order->closed) {
            return false;
        }

        if ($order->refund_status !== Order::REFUND_STATUS_PENDING) {
            return false;
        }

        return $this->hasProcessingStarted($order);
    }

    public function refundFeedbackUrl(Order $order)
    {
        return $this->customerFeedbackUrl($order);
    }

    public function customerFeedbackUrl(Order $order)
    {
        $stageLabel = $order->fulfillment_stage_label;

        return route('support.feedbacks.create', [
            'order_no' => $order->no,
            'message' => "订单号：{$order->no}\n当前履约阶段：{$stageLabel}\n\n请描述您的问题：\n",
        ]);
    }

    public function instantRefundBlockedMessage(User $user)
    {
        $cfg = config('order_refund.instant');
        $windowHours = (int) data_get($cfg, 'window_hours', 24);
        $maxPerWindow = (int) data_get($cfg, 'max_per_window', 3);
        $minIntervalMinutes = (int) data_get($cfg, 'min_interval_minutes', 5);

        if ($maxPerWindow < 1) {
            return null;
        }

        $since = now()->subHours($windowHours);
        $recent = $this->recentInstantRefunds($user->id, $since);

        if ($recent->count() >= $maxPerWindow) {
            return "您在最近 {$windowHours} 小时内已使用 {$maxPerWindow} 次自助秒退额度，请稍后再试或通过客户反馈联系本站。";
        }

        if ($minIntervalMinutes > 0) {
            $latest = $recent->sortByDesc(function (Order $order) {
                return data_get($order->extra, 'instant_refund_at', '');
            })->first();

            if ($latest) {
                $lastAt = Carbon::parse((string) data_get($latest->extra, 'instant_refund_at'));
                $nextAllowed = $lastAt->copy()->addMinutes($minIntervalMinutes);
                if ($nextAllowed->isFuture()) {
                    $wait = (int) ceil($nextAllowed->diffInSeconds(now()) / 60);

                    return "距上次自助秒退未满 {$minIntervalMinutes} 分钟，请约 {$wait} 分钟后再试。";
                }
            }
        }

        return null;
    }

    public function remainingInstantRefundsInWindow(User $user)
    {
        $cfg = config('order_refund.instant');
        $windowHours = (int) data_get($cfg, 'window_hours', 24);
        $maxPerWindow = (int) data_get($cfg, 'max_per_window', 3);
        $used = $this->recentInstantRefunds($user->id, now()->subHours($windowHours))->count();

        return max(0, $maxPerWindow - $used);
    }

    public function executeInstantRefund(Order $order, $reason = null)
    {
        return \DB::transaction(function () use ($order, $reason) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->find($order->id);

            if (!$this->canSelfInstantRefund($locked)) {
                throw new InvalidRequestException('当前订单不可自助秒退，请通过客户反馈联系本站。');
            }

            $user = $locked->user ?: User::query()->find($locked->user_id);
            if (!$user) {
                throw new InvalidRequestException('用户信息异常，无法退款。');
            }

            $blocked = $this->instantRefundBlockedMessage($user);
            if ($blocked !== null) {
                throw new InvalidRequestException($blocked);
            }

            $extra = $locked->extra ?: [];
            $extra['refund_reason'] = trim((string) $reason) !== '' ? trim((string) $reason) : '用户自助秒退';
            $extra['instant_refund_at'] = now()->toDateTimeString();
            $extra['instant_refund_full'] = true;

            $locked->update(['extra' => $extra]);

            return $this->executePaymentRefund($locked->fresh(), 1.0);
        });
    }

    public function executeAdminRefund(Order $order, array $input, $adminId = null)
    {
        return \DB::transaction(function () use ($order, $input, $adminId) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->find($order->id);
            if (!$locked) {
                throw new InvalidRequestException('订单不存在。');
            }

            if ($locked->refund_status === Order::REFUND_STATUS_APPLIED) {
                $extra = $locked->extra ?: [];
                unset($extra['refund_disagree_reason']);
                $locked->update(['extra' => $extra]);
            }

            $evaluation = $this->policy->evaluateAdminRefund($locked, $input, true);
            $ratio = (float) $evaluation['refund_ratio'];
            $reasonCode = (string) data_get($input, 'reason_code', '');
            $reasonNote = trim((string) data_get($input, 'reason_note', ''));
            $reasonLabel = $this->policy->reasonLabel($reasonCode);
            $reasonText = $reasonLabel;
            if ($reasonNote !== '') {
                $reasonText .= '：'.$reasonNote;
            }

            $extra = $locked->extra ?: [];
            $extra['refund_reason'] = $reasonText;
            $extra['refund_reason_code'] = $reasonCode;
            $extra['refund_reason_note'] = $reasonNote;
            $extra['admin_refund_at'] = now()->toDateTimeString();
            $extra['admin_refund_by'] = $adminId;
            $extra['admin_refund_ratio'] = $ratio;
            $extra['admin_refund_policy_hint'] = $evaluation['policy_hint'];
            if ((bool) data_get($input, 'supplier_cannot_supply', false)) {
                $extra['supplier_cannot_supply'] = true;
                $extra['supplier_cannot_supply_at'] = now()->toDateTimeString();
            }
            if ((bool) data_get($input, 's4_special_approval', false)) {
                $extra['s4_special_refund_approval'] = true;
                $extra['s4_special_refund_at'] = now()->toDateTimeString();
            }

            $locked->update(['extra' => $extra]);

            return $this->executePaymentRefund($locked->fresh(), $ratio);
        });
    }

    public function executePaymentRefund(Order $order, $refundRatio = 1.0)
    {
        if ((float) $order->getPaymentAmountCny() <= 0) {
            app(ExchangeRateService::class)->snapshotQuoteOnOrder($order->fresh());
            $order = $order->fresh();
        }

        $payCny = round((float) $order->getPaymentAmountCny(), 2);
        if ($payCny <= 0) {
            throw new InvalidRequestException('无法获取订单支付金额，请通过客户反馈联系本站处理退款。');
        }
        $ratio = round((float) $refundRatio, 4);
        if ($ratio <= 0) {
            throw new InvalidRequestException('退款比例无效。');
        }
        if ($ratio > 1) {
            $ratio = 1.0;
        }

        $refundCny = round($payCny * $ratio, 2);
        if ($refundCny <= 0) {
            throw new InvalidRequestException('退款金额无效。');
        }
        if ($refundCny > $payCny) {
            $refundCny = $payCny;
        }

        $extra = $order->extra ?: [];
        $extra['refund_amount_cny'] = $refundCny;
        $extra['refund_pay_amount_cny'] = $payCny;
        $extra['refund_ratio_applied'] = $ratio;
        $extra['cancellation_fee_cny'] = round($payCny - $refundCny, 2);
        $order->update(['extra' => $extra]);

        $refundNo = Order::getAvailableRefundNo();

        if ($this->crossSitePayment->shouldDelegateRefundToSiteB()) {
            $remote = $this->crossSitePayment->delegateRefundToSiteB($order, $refundNo, $payCny, $refundCny);
            $refundStatus = (string) ($remote['refund_status'] ?? Order::REFUND_STATUS_PROCESSING);

            $order->update([
                'refund_no' => (string) ($remote['refund_no'] ?? $refundNo),
                'refund_status' => $refundStatus,
            ]);

            $order = $order->fresh();
            if ($order->refund_status === Order::REFUND_STATUS_SUCCESS) {
                $this->notifyRefundSuccess($order);
            }

            return $order;
        }

        return $this->executeGatewayRefund($order, $refundNo, $payCny, $refundCny);
    }

    public function executeGatewayRefund(Order $order, $refundNo, $payCny, $refundCny)
    {
        try {
            switch ($order->payment_method) {
                case 'wechat':
                    app('wechat_pay')->refund([
                        'out_trade_no' => $order->no,
                        'total_fee' => (int) round($payCny * 100),
                        'refund_fee' => (int) round($refundCny * 100),
                        'out_refund_no' => $refundNo,
                        'notify_url' => route('payment.wechat.refund_notify'),
                    ]);
                    $order->update([
                        'refund_no' => $refundNo,
                        'refund_status' => Order::REFUND_STATUS_PROCESSING,
                    ]);

                    return $order->fresh();

                case 'alipay':
                    $ret = app('alipay')->refund([
                        'out_trade_no' => $order->no,
                        'refund_amount' => $refundCny,
                        'out_request_no' => $refundNo,
                    ]);
                    if ($ret->sub_code) {
                        $extra = $order->extra ?: [];
                        $extra['refund_failed_code'] = $ret->sub_code;
                        $order->update([
                            'refund_no' => $refundNo,
                            'refund_status' => Order::REFUND_STATUS_FAILED,
                            'extra' => $extra,
                        ]);

                        throw new InvalidRequestException('支付平台退款失败，请稍后重试或通过客户反馈联系本站。');
                    }

                    $order->update([
                        'refund_no' => $refundNo,
                        'refund_status' => Order::REFUND_STATUS_SUCCESS,
                    ]);

                    $order = $order->fresh();
                    $this->notifyRefundSuccess($order);

                    return $order;

                default:
                    throw new InternalException('未知订单支付方式：'.$order->payment_method);
            }
        } catch (InvalidRequestException $e) {
            throw $e;
        } catch (InternalException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error('支付渠道退款异常', [
                'order_id' => $order->id,
                'payment_method' => $order->payment_method,
                'message' => $e->getMessage(),
            ]);

            throw new InvalidRequestException('支付平台退款失败，请稍后重试或通过客户反馈联系本站。');
        }
    }

    protected function notifyRefundSuccess(Order $order)
    {
        if (!$order->user || $order->refund_status !== Order::REFUND_STATUS_SUCCESS) {
            return;
        }

        if (data_get($order->extra, 'manual_offline_refund')) {
            return;
        }

        try {
            $order->user->notify(new \App\Notifications\OrderRefundedNotification($order, true));
        } catch (\Throwable $e) {
            \Log::warning('退款通知邮件发送失败', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function notifyRefundSuccessPublic(Order $order)
    {
        $this->notifyRefundSuccess($order);
    }

    protected function recentInstantRefunds($userId, Carbon $since)
    {
        return Order::query()
            ->where('user_id', $userId)
            ->whereNotNull('paid_at')
            ->whereIn('refund_status', [
                Order::REFUND_STATUS_PROCESSING,
                Order::REFUND_STATUS_SUCCESS,
            ])
            ->orderByDesc('updated_at')
            ->get()
            ->filter(function (Order $order) use ($since) {
                $at = trim((string) data_get($order->extra, 'instant_refund_at', ''));
                if ($at === '') {
                    return false;
                }

                try {
                    return Carbon::parse($at)->gte($since);
                } catch (\Exception $e) {
                    return false;
                }
            });
    }

    /**
     * 管理员标记：已通过私聊等方式线下退款，不走支付渠道原路退回。
     */
    public function markManualOfflineRefunded(Order $order, $note = '', $adminId = null)
    {
        return \DB::transaction(function () use ($order, $note, $adminId) {
            /** @var Order|null $locked */
            $locked = Order::query()->lockForUpdate()->find($order->id);
            if (!$locked) {
                throw new InvalidRequestException('订单不存在。');
            }

            if (!$locked->paid_at) {
                throw new InvalidRequestException('仅已支付订单可标记线下私退完结。');
            }

            if (data_get($locked->extra, 'manual_offline_refund')) {
                throw new InvalidRequestException('该订单已标记为线下私退完结。');
            }

            if ($locked->refund_status === Order::REFUND_STATUS_SUCCESS) {
                throw new InvalidRequestException('订单已退款成功，无需重复操作。');
            }

            if ($locked->refund_status === Order::REFUND_STATUS_PROCESSING) {
                throw new InvalidRequestException('订单正在平台退款处理中，请等待支付渠道结果后再标记，或联系技术处理。');
            }

            if ((float) $locked->getPaymentAmountCny() <= 0) {
                app(ExchangeRateService::class)->snapshotQuoteOnOrder($locked->fresh());
                $locked = $locked->fresh();
            }

            $payCny = round((float) $locked->getPaymentAmountCny(), 2);
            if ($payCny <= 0) {
                throw new InvalidRequestException('无法获取订单支付金额，请补充备注后仍无法处理时请通过客户反馈联系本站。');
            }

            $note = trim((string) $note);
            $reason = $note !== '' ? $note : '管理员标记：已通过私聊等方式线下退款完结';

            $extra = $this->fulfillment->normalizeExtraArray($locked);
            $extra['manual_offline_refund'] = true;
            $extra['manual_offline_refund_at'] = now()->toDateTimeString();
            $extra['manual_offline_refund_note'] = $note;
            $extra['manual_offline_refund_by'] = $adminId;
            $extra['refund_reason'] = $reason;
            $extra['refund_amount_cny'] = $payCny;
            $extra['refund_pay_amount_cny'] = $payCny;
            $extra['refund_ratio_applied'] = 1.0;
            $extra['cancellation_fee_cny'] = 0.0;

            $refundNo = Order::getAvailableRefundNo();

            $locked->update([
                'refund_status' => Order::REFUND_STATUS_SUCCESS,
                'refund_no' => $refundNo,
                'closed' => true,
                'extra' => $extra,
            ]);

            return $locked->fresh();
        });
    }
}
