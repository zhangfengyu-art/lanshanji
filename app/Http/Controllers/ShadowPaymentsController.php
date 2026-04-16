<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidRequestException;
use App\Models\ShadowOrder;
use Endroid\QrCode\QrCode;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Yansongda\Pay\Gateways\Wechat\Support as WechatSupport;

class ShadowPaymentsController extends Controller
{
    public function payByAlipay($shadowNo)
    {
        if (!is_site_mode_b()) {
            throw new InvalidRequestException('中继支付仅在 B 站可用');
        }

        $shadowOrder = ShadowOrder::query()->where('shadow_no', (string) $shadowNo)->first();
        if (!$shadowOrder) {
            throw new InvalidRequestException('中继订单不存在');
        }

        $providedSig = (string) request()->query('sig', '');
        if ($providedSig === '' || !hash_equals((string) $shadowOrder->signature_hash, hash('sha256', $providedSig))) {
            throw new InvalidRequestException('中继签名无效');
        }

        if ((string) $shadowOrder->status === 'paid' || $shadowOrder->paid_at) {
            throw new InvalidRequestException('该中继订单已支付');
        }

        $response = app('alipay')->web([
            'out_trade_no' => $shadowOrder->shadow_no,
            'total_amount' => number_format((float) $shadowOrder->amount, 2, '.', ''),
            'subject' => '跨站中继支付 ' . (string) $shadowOrder->source_order_no,
            'timeout_express' => '30m',
        ]);

        return $this->withNoReferrerPolicy($response);
    }

    public function payByWechat($shadowNo)
    {
        if (!is_site_mode_b()) {
            throw new InvalidRequestException('中继支付仅在 B 站可用');
        }

        $shadowOrder = ShadowOrder::query()->where('shadow_no', (string) $shadowNo)->first();
        if (!$shadowOrder) {
            throw new InvalidRequestException('中继订单不存在');
        }

        $providedSig = (string) request()->query('sig', '');
        if ($providedSig === '' || !hash_equals((string) $shadowOrder->signature_hash, hash('sha256', $providedSig))) {
            throw new InvalidRequestException('中继签名无效');
        }

        if ((string) $shadowOrder->status === 'paid' || $shadowOrder->paid_at) {
            throw new InvalidRequestException('该中继订单已支付');
        }

        $this->forceDisableWechatSslVerifyForLocal();

        try {
            $wechatOrder = app('wechat_pay')->scan([
                'out_trade_no' => $shadowOrder->shadow_no,
                'total_fee' => (int) $shadowOrder->amount_minor,
                'body' => '跨站中继支付 ' . (string) $shadowOrder->source_order_no,
                'time_expire' => now()->addMinutes(30)->format('YmdHis'),
            ]);
        } catch (RequestException $e) {
            if (strpos((string) $e->getMessage(), 'cURL error 60') !== false) {
                throw new InvalidRequestException('微信支付网关证书校验失败（本地环境）');
            }
            throw new InvalidRequestException('微信支付通道暂不可用，请稍后重试');
        }

        $qrCode = new QrCode($wechatOrder->code_url);

        return response($qrCode->writeString(), 200, [
            'Content-Type' => $qrCode->getContentType(),
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    protected function withNoReferrerPolicy($response)
    {
        if (is_object($response)) {
            if (method_exists($response, 'header')) {
                $response->header('Referrer-Policy', 'no-referrer');
                return $response;
            }
            if (method_exists($response, 'withHeaders')) {
                return $response->withHeaders(['Referrer-Policy' => 'no-referrer']);
            }
            if (isset($response->headers) && method_exists($response->headers, 'set')) {
                $response->headers->set('Referrer-Policy', 'no-referrer');
                return $response;
            }
        }

        return response($response)->header('Referrer-Policy', 'no-referrer');
    }

    protected function forceDisableWechatSslVerifyForLocal()
    {
        if (!app()->environment('local')) {
            return;
        }

        try {
            $reflection = new ReflectionClass(WechatSupport::class);
            $instanceProperty = $reflection->getProperty('instance');
            $instanceProperty->setAccessible(true);

            $patchedSupport = new class extends WechatSupport {
                public function __construct()
                {
                }

                protected function getBaseOptions()
                {
                    $options = parent::getBaseOptions();
                    $options['verify'] = false;

                    return $options;
                }
            };

            $instanceProperty->setValue(null, $patchedSupport);
        } catch (\Throwable $e) {
            Log::warning('Failed to inject local verify=false into Wechat SDK support client', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
