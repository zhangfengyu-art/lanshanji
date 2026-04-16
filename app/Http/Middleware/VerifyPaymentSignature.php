<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Predis\Connection\ConnectionException;

class VerifyPaymentSignature
{
    public function handle(Request $request, Closure $next)
    {
        $secret = (string) config('site.shadow_order_sign_secret', '');
        if ($secret === '') {
            return $this->forbidden();
        }

        $merchantId = (string) $request->input('merchant_id', '');
        $orderNo = (string) $request->input('order_no', '');
        $amountMinor = (string) $request->input('amount_minor', '');
        $timestamp = (string) $request->input('ts', '');
        $nonce = (string) $request->input('nonce', '');
        $bodySha256 = (string) $request->input('body_sha256', '');
        $providedSign = (string) $request->header('X-Sign', $request->input('sign', ''));

        if (
            $merchantId === '' ||
            $orderNo === '' ||
            $amountMinor === '' ||
            $timestamp === '' ||
            $nonce === '' ||
            $bodySha256 === '' ||
            $providedSign === ''
        ) {
            return $this->forbidden();
        }

        $allowedMerchants = (array) config('site.shadow_order_allowed_merchants', []);
        if (!empty($allowedMerchants) && !in_array($merchantId, $allowedMerchants, true)) {
            return $this->forbidden();
        }

        if (!$this->isTimestampValid((int) $timestamp)) {
            return $this->forbidden();
        }

        if (!$this->checkAndRememberNonce($merchantId, $nonce)) {
            return $this->forbidden();
        }

        $canonical = implode('|', [
            $merchantId,
            $orderNo,
            $amountMinor,
            $timestamp,
            $nonce,
            $bodySha256,
        ]);

        $expectedSign = base64_encode(hash_hmac('sha256', $canonical, $secret, true));
        if (!hash_equals($expectedSign, $providedSign)) {
            return $this->forbidden();
        }

        return $next($request);
    }

    protected function checkAndRememberNonce($merchantId, $nonce)
    {
        $keyPrefix = (string) config('site.shadow_order_nonce_prefix', 'shadow_order_nonce');
        $nonceTtl = (int) config('site.shadow_order_nonce_ttl', 300);
        $key = sprintf('%s:%s:%s', $keyPrefix, $merchantId, $nonce);

        try {
            $set = Redis::setnx($key, 1);
            if (!$set) {
                return false;
            }

            Redis::expire($key, $nonceTtl);

            return true;
        } catch (ConnectionException $e) {
            // Redis 连接故障时降级：跳过 nonce 唯一性校验，但仍执行签名校验。
            Log::warning('Skip shadow order nonce uniqueness check due to Redis connection failure', [
                'merchant_id' => $merchantId,
                'nonce' => $nonce,
                'error' => $e->getMessage(),
            ]);

            return true;
        } catch (\RedisException $e) {
            Log::warning('Skip shadow order nonce uniqueness check due to Redis extension failure', [
                'merchant_id' => $merchantId,
                'nonce' => $nonce,
                'error' => $e->getMessage(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Skip shadow order nonce uniqueness check due to unexpected Redis error', [
                'merchant_id' => $merchantId,
                'nonce' => $nonce,
                'error' => $e->getMessage(),
            ]);

            return true;
        }
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

    protected function forbidden()
    {
        return response()->json([
            'message' => 'Forbidden',
        ], 403);
    }
}
