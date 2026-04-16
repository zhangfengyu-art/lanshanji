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

class NotifySiteAPaidJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 7;

    public $timeout = 15;

    protected $orderId;

    protected $backoffSeconds = [30, 120, 600, 1800, 7200, 21600, 86400];

    public function __construct($orderId)
    {
        $this->orderId = (int) $orderId;
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

        $merchantId = (string) config('app.name', 'site-b');
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $amountMinor = (int) round(((float) $order->total_amount) * 100);

        $payload = [
            'merchant_id' => $merchantId,
            'order_no' => (string) $order->no,
            'payment_no' => (string) ($order->payment_no ?: $order->no),
            'amount_minor' => $amountMinor,
            'status' => 'paid',
            'paid_at' => optional($order->paid_at)->toIso8601String(),
            'ts' => $timestamp,
            'nonce' => $nonce,
        ];

        $payload['body_sha256'] = hash('sha256', $this->canonicalJson($payload));
        $payload['sign'] = $this->signPayload($payload, $secret);

        $client = new Client([
            'timeout' => (int) config('site.shadow_order_webhook_timeout', 3),
        ]);

        try {
            $response = $client->post($webhookUrl, [
                'json' => $payload,
            ]);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                throw new \RuntimeException('Webhook response status is not success: ' . $response->getStatusCode());
            }
        } catch (\Throwable $e) {
            $attempt = (int) $this->attempts();
            $maxAttempts = (int) $this->tries;

            if ($attempt < $maxAttempts) {
                $delay = $this->backoffSeconds[min($attempt - 1, count($this->backoffSeconds) - 1)];
                $this->release($delay);
                return;
            }

            throw $e;
        }
    }

    protected function signPayload(array $payload, $secret)
    {
        $canonical = implode('|', [
            (string) data_get($payload, 'merchant_id', ''),
            (string) data_get($payload, 'order_no', ''),
            (string) data_get($payload, 'payment_no', ''),
            (string) data_get($payload, 'amount_minor', ''),
            (string) data_get($payload, 'status', ''),
            (string) data_get($payload, 'paid_at', ''),
            (string) data_get($payload, 'ts', ''),
            (string) data_get($payload, 'nonce', ''),
            (string) data_get($payload, 'body_sha256', ''),
        ]);

        return base64_encode(hash_hmac('sha256', $canonical, $secret, true));
    }

    protected function canonicalJson(array $payload)
    {
        ksort($payload);

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function failed(\Throwable $e)
    {
        Log::error('Notify Site A paid webhook permanently failed after retries', [
            'order_id' => $this->orderId,
            'error' => $e->getMessage(),
        ]);
    }
}
