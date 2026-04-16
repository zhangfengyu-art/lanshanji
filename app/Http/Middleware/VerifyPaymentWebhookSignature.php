<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class VerifyPaymentWebhookSignature
{
    public function handle(Request $request, Closure $next)
    {
        $secret = (string) config('site.shadow_order_sign_secret', '');
        if ($secret === '') {
            return $this->forbidden();
        }

        $merchantId = (string) $request->input('merchant_id', '');
        $orderNo = (string) $request->input('order_no', '');
        $paymentNo = (string) $request->input('payment_no', '');
        $amountMinor = (string) $request->input('amount_minor', '');
        $status = (string) $request->input('status', '');
        $paidAt = (string) $request->input('paid_at', '');
        $timestamp = (int) $request->input('ts', 0);
        $nonce = (string) $request->input('nonce', '');
        $bodySha256 = (string) $request->input('body_sha256', '');
        $providedSign = (string) $request->header('X-Sign', $request->input('sign', ''));

        if ($merchantId === '' || $orderNo === '' || $paymentNo === '' || $amountMinor === '' || $status === '' || $paidAt === '' || $timestamp <= 0 || $nonce === '' || $bodySha256 === '' || $providedSign === '') {
            return $this->forbidden();
        }

        if (!$this->isTimestampValid($timestamp)) {
            return $this->forbidden();
        }

        if (!$this->checkAndRememberNonce($merchantId, $nonce)) {
            return $this->forbidden();
        }

        $calculatedBodyHash = $this->computeBodyHash($request);
        if (!hash_equals($calculatedBodyHash, $bodySha256)) {
            return $this->forbidden();
        }

        $canonical = implode('|', [
            $merchantId,
            $orderNo,
            $paymentNo,
            $amountMinor,
            $status,
            $paidAt,
            (string) $timestamp,
            $nonce,
            $bodySha256,
        ]);

        $expected = base64_encode(hash_hmac('sha256', $canonical, $secret, true));
        if (!hash_equals($expected, $providedSign)) {
            return $this->forbidden();
        }

        return $next($request);
    }

    protected function isTimestampValid($timestamp)
    {
        $now = time();
        $ttl = (int) config('site.shadow_order_sign_ttl', 300);
        $futureSkew = (int) config('site.shadow_order_sign_future_skew', 300);

        if ($timestamp > ($now + $futureSkew)) {
            return false;
        }

        return ($now - $timestamp) <= $ttl;
    }

    protected function checkAndRememberNonce($merchantId, $nonce)
    {
        $keyPrefix = (string) config('site.shadow_order_nonce_prefix', 'shadow_order_nonce');
        $nonceTtl = (int) config('site.shadow_order_nonce_ttl', 300);
        $key = sprintf('%s:webhook:%s:%s', $keyPrefix, $merchantId, $nonce);

        try {
            $set = Redis::setnx($key, 1);
            if (!$set) {
                return false;
            }
            Redis::expire($key, $nonceTtl);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function computeBodyHash(Request $request)
    {
        $payload = $request->all();
        unset($payload['sign'], $payload['body_sha256']);
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    protected function forbidden()
    {
        return response()->json(['message' => 'Forbidden'], 403);
    }
}
