<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\OrderPaid;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentWebhookController extends Controller
{
    public function paid(Request $request)
    {
        $this->validate($request, [
            'merchant_id' => ['required', 'string', 'max:64'],
            'order_no' => ['required', 'string', 'max:64'],
            'payment_no' => ['required', 'string', 'max:128'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:paid'],
            'paid_at' => ['required', 'date'],
            'ts' => ['required', 'integer', 'min:1'],
            'nonce' => ['required', 'string', 'max:128'],
            'body_sha256' => ['required', 'string', 'size:64'],
            'sign' => ['required', 'string', 'max:255'],
        ]);

        $orderNo = (string) $request->input('order_no');
        $paymentNo = (string) $request->input('payment_no');
        $amountMinor = (int) $request->input('amount_minor');
        $paidAt = Carbon::parse($request->input('paid_at'));

        $result = DB::transaction(function () use ($orderNo, $paymentNo, $amountMinor, $paidAt) {
            $order = Order::query()->where('no', $orderNo)->lockForUpdate()->first();
            if (!$order) {
                return ['http' => 404, 'payload' => ['message' => 'order_not_found']];
            }

            $expectedAmountMinor = (int) round(((float) $order->total_amount) * 100);
            if ($expectedAmountMinor !== $amountMinor) {
                return ['http' => 422, 'payload' => ['message' => 'amount_mismatch']];
            }

            $usedByOther = Order::query()
                ->where('payment_no', $paymentNo)
                ->where('id', '<>', $order->id)
                ->exists();
            if ($usedByOther) {
                return ['http' => 409, 'payload' => ['message' => 'duplicate_payment_no']];
            }

            if ($order->paid_at) {
                if ((string) $order->payment_no === $paymentNo) {
                    return ['http' => 200, 'payload' => ['message' => 'ok', 'status' => 'already_paid']];
                }
                return ['http' => 409, 'payload' => ['message' => 'already_paid_with_other_payment_no']];
            }

            $order->update([
                'paid_at' => $paidAt,
                'payment_method' => 'relay_webhook',
                'payment_no' => $paymentNo,
            ]);

            event(new OrderPaid($order));

            return ['http' => 200, 'payload' => ['message' => 'ok', 'status' => 'paid']];
        });

        return response()->json($result['payload'], $result['http']);
    }
}
