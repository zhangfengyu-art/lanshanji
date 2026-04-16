<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class PaymentReturnController extends Controller
{
    public function show(Request $request)
    {
        $order = $this->resolveOrder($request);
        $ticketPayload = (array) $request->attributes->get('payment_return_ticket_payload', []);

        return view('payments.return', [
            'order' => $order,
            'ticketPayload' => $ticketPayload,
            'isPaid' => (bool) $order->paid_at,
            'pollUrl' => route('payment.return.status', ['ticket' => (string) $request->input('ticket')]),
            'orderUrl' => route('orders.show', ['order' => $order->id]),
            'orderSnapshot' => $this->buildSnapshot($order),
        ]);
    }

    public function status(Request $request)
    {
        $order = $this->resolveOrder($request);
        $snapshot = $this->buildSnapshot($order);

        return response()->json([
            'paid' => (bool) $order->paid_at,
            'display_status' => $order->display_status,
            'paid_at' => optional($order->paid_at)->toDateTimeString(),
            'paid_at_display' => (string) data_get($snapshot, 'paid_at_display', ''),
            'payment_method' => (string) $order->payment_method,
            'payment_no' => (string) $order->payment_no,
            'order_no' => (string) $order->no,
            'order_url' => route('orders.show', ['order' => $order->id]),
            'snapshot' => $snapshot,
        ]);
    }

    protected function resolveOrder(Request $request)
    {
        $order = $request->attributes->get('payment_return_order');
        if ($order instanceof Order) {
            return $order->loadMissing(['items.productSku', 'user']);
        }

        abort(404);
    }

    protected function buildSnapshot(Order $order)
    {
        $address = (array) $order->address;
        $paidAt = optional($order->paid_at)->toDateTimeString();
        $webhookSyncedAt = optional($order->updated_at)->toDateTimeString();

        $fullAddress = trim(implode(' ', array_filter([
            (string) data_get($address, 'province', ''),
            (string) data_get($address, 'city', ''),
            (string) data_get($address, 'district', ''),
            (string) data_get($address, 'address', ''),
        ])));

        $trackingNo = trim((string) data_get($order, 'tracking_no', ''));

        return [
            'order_no' => (string) $order->no,
            'created_at' => optional($order->created_at)->toDateTimeString(),
            'paid_at' => $paidAt,
            'webhook_synced_at' => $webhookSyncedAt,
            'paid_at_display' => $paidAt ?: ('Webhook同步于 ' . ($webhookSyncedAt ?: '处理中')),
            'contact_name' => (string) data_get($address, 'contact_name', ''),
            'contact_phone' => $this->maskPhone((string) data_get($address, 'contact_phone', '')),
            'id_card' => $this->maskIdCard((string) data_get($address, 'id_card', '')),
            'address' => $fullAddress,
            'zip' => (string) data_get($address, 'zip', ''),
            'fulfillment_photo' => (string) data_get($order, 'fulfillment_photo', ''),
            'has_fulfillment_photo' => trim((string) data_get($order, 'fulfillment_photo', '')) !== '',
            'fulfillment_photo_url' => trim((string) data_get($order, 'fulfillment_photo', '')) !== '' ? route('order.photo.fulfillment', ['order_no' => $order->no]) : '',
            'tracking_no' => $trackingNo,
            'jp_post_tracking_url' => $trackingNo !== ''
                ? 'https://trackings.post.japanpost.jp/services/srv/search/direct?reqCodeNo1=' . rawurlencode($trackingNo)
                : '',
        ];
    }

    protected function maskPhone($phone)
    {
        $phone = preg_replace('/\s+/', '', $phone);
        if ($phone === '') {
            return '待补充';
        }

        if (strlen($phone) < 7) {
            return $phone;
        }

        return substr($phone, 0, 3) . '****' . substr($phone, -4);
    }

    protected function maskIdCard($idCard)
    {
        $idCard = preg_replace('/\s+/', '', $idCard);
        if ($idCard === '') {
            return '待补充';
        }

        if (strlen($idCard) < 8) {
            return $idCard;
        }

        return substr($idCard, 0, 4) . str_repeat('*', max(4, strlen($idCard) - 8)) . substr($idCard, -4);
    }
}