<?php

namespace App\Jobs;

use App\Models\Order;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyShadowOrderPaidWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries;

    public $timeout = 15;

    protected $orderId;

    public function __construct($orderId)
    {
        $this->orderId = (int) $orderId;
        $this->tries = (int) config('site.shadow_order_webhook_max_tries', 8);
        $this->queue = (string) config('site.shadow_order_webhook_queue', 'default');
    }

    public function handle()
    {
        $webhookUrl = (string) config('site.shadow_order_receiver_url', config('site.shadow_order_paid_webhook', ''));
        if ($webhookUrl === '') {
            return;
        }

        $order = Order::query()->find($this->orderId);
        if (!$order || !$order->paid_at) {
            return;
        }

        $secret = (string) config('site.shadow_order_sign_secret', '');
        if ($secret === '') {
            throw new \RuntimeException('Shadow order sign secret is empty');
        }

        $timestamp = time();
        $payload = [
            'order_no' => $order->no,
            'total_amount' => number_format((float) $order->total_amount, 2, '.', ''),
            'paid_at' => optional($order->paid_at)->toDateTimeString(),
            'status' => 'paid',
            'site_mode' => site_mode(),
            'shadow_order_no' => data_get($order->extra, 'shadow_order_no'),
            'timestamp' => $timestamp,
        ];

        $signRaw = implode('|', [
            $payload['order_no'],
            $payload['total_amount'],
            $payload['paid_at'],
            $payload['status'],
            $payload['timestamp'],
        ]);
        $payload['sign'] = hash_hmac('sha256', $signRaw, $secret);

        $client = new Client([
            'timeout' => (int) config('site.shadow_order_webhook_timeout', 3),
        ]);

        $response = $client->post($webhookUrl, [
            'json' => $payload,
        ]);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new \RuntimeException('Webhook response status is not success: ' . $response->getStatusCode());
        }
    }

    public function failed(\Throwable $e)
    {
        Log::error('Shadow order paid webhook permanently failed after retries', [
            'order_id' => $this->orderId,
            'error' => $e->getMessage(),
        ]);
    }
}
