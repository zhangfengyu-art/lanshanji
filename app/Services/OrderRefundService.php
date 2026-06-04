<?php

namespace App\Services;

use App\Exceptions\InternalException;
use App\Exceptions\InvalidRequestException;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;

class OrderRefundService
{
    /** @var OrderFulfillmentService */
    protected $fulfillment;

    public function __construct(OrderFulfillmentService $fulfillment)
    {
        $this->fulfillment = $fulfillment;
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

        return $this->fulfillment->resolveStage($order) === OrderFulfillmentService::STAGE_S1;
    }

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

        return !$this->canSelfInstantRefund($order);
    }

    public function refundFeedbackUrl(Order $order)
    {
        $stageLabel = $order->fulfillment_stage_label;

        return route('support.feedbacks.create', [
            'order_no' => $order->no,
            'question_type' => 'refund',
            'message' => "订单号：{$order->no}\n当前履约阶段：{$stageLabel}\n\n请协助处理取消/退款事宜：\n",
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

            return $this->executePaymentRefund($locked->fresh());
        });
    }

    public function executePaymentRefund(Order $order)
    {
        switch ($order->payment_method) {
            case 'wechat':
                $refundNo = Order::getAvailableRefundNo();
                app('wechat_pay')->refund([
                    'out_trade_no' => $order->no,
                    'total_fee' => $order->getPaymentAmountCny() * 100,
                    'refund_fee' => $order->getPaymentAmountCny() * 100,
                    'out_refund_no' => $refundNo,
                    'notify_url' => route('payment.wechat.refund_notify'),
                ]);
                $order->update([
                    'refund_no' => $refundNo,
                    'refund_status' => Order::REFUND_STATUS_PROCESSING,
                ]);

                return $order->fresh();

            case 'alipay':
                $refundNo = Order::getAvailableRefundNo();
                $ret = app('alipay')->refund([
                    'out_trade_no' => $order->no,
                    'refund_amount' => $order->getPaymentAmountCny(),
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
    }

    protected function notifyRefundSuccess(Order $order)
    {
        if ($order->user && $order->refund_status === Order::REFUND_STATUS_SUCCESS) {
            $order->user->notify(new \App\Notifications\OrderRefundedNotification($order, true));
        }
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
}
