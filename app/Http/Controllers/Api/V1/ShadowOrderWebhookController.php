<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\OrderPaid;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ShadowOrderWebhookController extends Controller
{
    public function paid(Request $request)
    {
        $this->validate($request, [
            'order_no' => ['required', 'string', 'max:64'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['required', 'date'],
            'status' => ['required', 'in:paid'],
            'timestamp' => ['required', 'integer', 'min:1'],
            'sign' => ['required', 'string', 'max:255'],
        ]);

        $orderNo = (string) $request->input('order_no');
        $totalAmount = number_format((float) $request->input('total_amount'), 2, '.', '');
        $paidAt = Carbon::parse($request->input('paid_at'))->toDateTimeString();
        $status = (string) $request->input('status');
        $timestamp = (int) $request->input('timestamp');
        $sign = (string) $request->input('sign');

        if (!$this->isTimestampValid($timestamp)) {
            return response()->json([
                'message' => 'timestamp expired',
            ], 403);
        }

        if (!$this->verifySign($orderNo, $totalAmount, $paidAt, $status, $timestamp, $sign)) {
            return response()->json([
                'message' => 'invalid sign',
            ], 403);
        }

        $order = Order::query()->where('no', $orderNo)->first();
        if (!$order) {
            return response()->json([
                'message' => 'order not found',
            ], 404);
        }

        if ($order->paid_at) {
            return response()->json([
                'message' => 'ok',
                'status' => 'already_paid',
            ]);
        }

        $order->update([
            'paid_at' => Carbon::parse($paidAt),
            'payment_method' => 'shadow_webhook',
            'payment_no' => $orderNo,
        ]);

        event(new OrderPaid($order));

        return response()->json([
            'message' => 'ok',
            'status' => 'paid',
        ]);
    }

    protected function verifySign($orderNo, $totalAmount, $paidAt, $status, $timestamp, $providedSign)
    {
        $secret = (string) config('site.shadow_order_sign_secret', '');
        if ($secret === '') {
            return false;
        }

        $payload = implode('|', [$orderNo, $totalAmount, $paidAt, $status, $timestamp]);
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $providedSign);
    }

    protected function isTimestampValid($timestamp)
    {
        $now = time();
        $ttl = (int) config('site.shadow_order_sign_ttl', 300);
        $futureSkew = (int) config('site.shadow_order_sign_future_skew', 60);

        if ($timestamp > ($now + $futureSkew)) {
            return false;
        }

        return ($now - $timestamp) <= $ttl;
    }
}
