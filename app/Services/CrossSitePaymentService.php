<?php

namespace App\Services;

use App\Exceptions\InvalidRequestException;
use App\Models\Order;
use Illuminate\Http\Request;

class CrossSitePaymentService
{
    const ESCROW_SESSION_KEY = 'cross_site_payment_escrow';

    public function shouldRedirectToSiteB()
    {
        return !is_site_mode_b();
    }

    public function buildSignedPayUrl(Order $order, $method)
    {
        $method = $this->normalizeMethod($method);
        $expires = $this->resolveExpiresAt($order);
        $signature = $this->sign($order, $method, $expires);

        $query = http_build_query([
            'method' => $method,
            'expires' => $expires,
            'signature' => $signature,
        ]);

        return site_b_url('payment/cross/'.$order->id.'?'.$query);
    }

    public function verify(Request $request, Order $order, $method = null)
    {
        $method = $this->normalizeMethod($method ?: $request->query('method'));
        $expires = (int) $request->query('expires');
        $signature = (string) $request->query('signature');

        if ($expires < time()) {
            return false;
        }

        if ($signature === '') {
            return false;
        }

        $expected = $this->sign($order, $method, $expires);

        return hash_equals($expected, $signature);
    }

    protected function normalizeMethod($method)
    {
        $method = strtolower((string) $method);

        if (!in_array($method, ['alipay', 'wechat'], true)) {
            throw new \InvalidArgumentException('Unsupported payment method.');
        }

        return $method;
    }

    protected function resolveExpiresAt(Order $order)
    {
        $orderExpires = $order->getAllocationExpiresAt()->timestamp;
        $maxWindow = now()->addMinutes(30)->timestamp;

        return min($orderExpires, $maxWindow);
    }

    protected function sign(Order $order, $method, $expires)
    {
        $payload = implode('|', [
            (int) $order->id,
            (int) $order->user_id,
            $method,
            (int) $expires,
        ]);

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    /**
     * A 站用户经签名入口进入 B 站支付后，限制其只能在支付路由内活动。
     */
    public function markEscrow(Order $order)
    {
        $expiresAt = min(
            $order->getAllocationExpiresAt()->timestamp,
            now()->addMinutes(30)->timestamp
        );

        session([
            self::ESCROW_SESSION_KEY => [
                'order_id' => (int) $order->id,
                'user_id' => (int) $order->user_id,
                'expires_at' => (int) $expiresAt,
            ],
        ]);
    }

    public function clearEscrow()
    {
        session()->forget(self::ESCROW_SESSION_KEY);
    }

    public function getEscrow()
    {
        $data = session(self::ESCROW_SESSION_KEY);
        if (!is_array($data)) {
            return null;
        }

        if ((int) ($data['expires_at'] ?? 0) < time()) {
            $this->clearEscrow();

            return null;
        }

        return $data;
    }

    public function hasActiveEscrow()
    {
        return $this->getEscrow() !== null;
    }

    public function escrowOrderId()
    {
        $escrow = $this->getEscrow();

        return $escrow ? (int) $escrow['order_id'] : 0;
    }

    public function isAllowedRouteDuringEscrow(Request $request)
    {
        $routeName = optional($request->route())->getName();
        $allowedNames = [
            'payment.cross',
            'payment.wechat',
            'payment.alipay',
            'payment.alipay.launch',
            'payment.wechat.qr',
            'payment.alipay.return',
            'payment.alipay.notify',
            'payment.wechat.notify',
            'payment.wechat.refund_notify',
            'payment.cross_refund',
        ];

        if ($routeName && in_array($routeName, $allowedNames, true)) {
            return true;
        }

        $path = ltrim((string) $request->path(), '/');

        if (preg_match('#^payment/cross/\d+#', $path)) {
            return true;
        }

        if (preg_match('#^payment/\d+/(wechat|alipay)(/|$)#', $path)) {
            return true;
        }

        if ($path === 'payment/alipay/return') {
            return true;
        }

        if ($request->isMethod('POST') && preg_match('#^payment/(alipay|wechat)/(notify|refund_notify)$#', $path)) {
            return true;
        }

        if ($request->isMethod('POST') && preg_match('#^payment/cross-refund/\d+#', $path)) {
            return true;
        }

        return false;
    }

    public function siteAReturnUrl($orderId = null)
    {
        $orderId = (int) ($orderId ?: $this->escrowOrderId());

        return $orderId > 0 ? site_a_url('orders/'.$orderId) : site_a_url();
    }

    /**
     * A 站订单在 B 站收银台收款，退款也需由 B 站调支付渠道。
     */
    public function shouldDelegateRefundToSiteB()
    {
        return $this->shouldRedirectToSiteB();
    }

    public function signRefund(Order $order, $refundNo, $payCny, $refundCny, $expires)
    {
        $payload = implode('|', [
            (int) $order->id,
            (int) $order->user_id,
            (string) $refundNo,
            (int) round((float) $payCny * 100),
            (int) round((float) $refundCny * 100),
            (int) $expires,
        ]);

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    public function verifyRefundRequest(Request $request, Order $order)
    {
        $expires = (int) $request->input('expires');
        $signature = (string) $request->input('signature');
        $refundNo = trim((string) $request->input('refund_no'));

        if ($expires < time() || $signature === '' || $refundNo === '') {
            return false;
        }

        $payCny = (float) $request->input('pay_cny');
        $refundCny = (float) $request->input('refund_cny');
        $expected = $this->signRefund($order, $refundNo, $payCny, $refundCny, $expires);

        return hash_equals($expected, $signature);
    }

    public function delegateRefundToSiteB(Order $order, $refundNo, $payCny, $refundCny)
    {
        $expires = now()->addMinutes(10)->timestamp;
        $payload = [
            'refund_no' => $refundNo,
            'pay_cny' => $payCny,
            'refund_cny' => $refundCny,
            'expires' => $expires,
            'signature' => $this->signRefund($order, $refundNo, $payCny, $refundCny, $expires),
        ];

        $url = site_b_url('payment/cross-refund/'.$order->id);

        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => 45,
                'http_errors' => false,
                'verify' => true,
            ]);
            $response = $client->post($url, [
                'form_params' => $payload,
                'headers' => [
                    'Accept' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('跨站退款请求失败', [
                'order_id' => $order->id,
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            throw new InvalidRequestException('无法连接支付收银台发起退款，请稍后重试或通过客户反馈联系本站。');
        }

        $status = (int) $response->getStatusCode();
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        if (!is_array($data)) {
            $data = [];
        }

        if ($status >= 400) {
            $message = trim((string) ($data['message'] ?? $data['msg'] ?? ''));
            if ($message === '') {
                $message = '支付收银台退款失败，请稍后重试或通过客户反馈联系本站。';
            }

            throw new InvalidRequestException($message);
        }

        return $data;
    }
}
