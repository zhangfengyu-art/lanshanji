<?php

namespace App\Http\Controllers;

use App\Jobs\NotifySiteAPaidByShadowOrderJob;
use App\Jobs\NotifySiteAPaidJob;
use App\Exceptions\InvalidRequestException;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\ProcurementOrder;
use App\Models\ShadowOrder;
use App\Services\ProcurementService;
use Carbon\Carbon;
use Endroid\QrCode\QrCode;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Events\OrderPaid;
use App\Services\PaymentReturnTicketService;

class PaymentController extends Controller
{
    /** @var ProcurementService */
    protected $procurementService;

    public function __construct(ProcurementService $procurementService)
    {
        $this->procurementService = $procurementService;
    }

    public function payByAlipay(Order $order, Request $request)
    {
        $this->authorize('own', $order);
        $this->ensureOrderPayable($order);

        // Site A 统一中继到 Site B 支付，不在 A 站本地直连支付宝网关。
        if (!is_site_mode_b()) {
            return $this->relayPaymentToSiteB($order, 'alipay');
        }

        $procurementOrder = $this->ensureProcurementOrderForPayment($order);
        $metadata = $this->buildEscrowPaymentMetadata($order, $procurementOrder);
        $subject = $metadata['subject'];

        $response = app('alipay')->web([
            'out_trade_no' => $order->no,
            'total_amount' => $order->total_amount,
            'subject'      => $subject,
            'timeout_express' => max(1, (int) ceil($order->getAllocationExpiresIn() / 60)).'m',
        ]);

        return $this->withNoReferrerPolicy($response);
    }

    public function payByWechat(Order $order, Request $request)
    {
        $this->authorize('own', $order);
        $this->ensureOrderPayable($order);

        // Site A 统一中继到 Site B 支付，不在 A 站本地直连微信网关。
        if (!is_site_mode_b()) {
            return $this->relayPaymentToSiteB($order, 'wechat');
        }

        $procurementOrder = $this->ensureProcurementOrderForPayment($order);
        $metadata = $this->buildEscrowPaymentMetadata($order, $procurementOrder);
        $body = $metadata['body'];
        
        try {
            // 之前是直接返回，现在把返回值放到一个变量里
            $wechatOrder = app('wechat_pay')->scan([
                'out_trade_no' => $order->no,
                'total_fee'    => $order->total_amount * 100,
                'body'         => $body,
                'time_expire'  => $order->getAllocationExpiresAt()->format('YmdHis'),
            ]);
        } catch (RequestException $e) {
            if (strpos((string) $e->getMessage(), 'cURL error 60') !== false) {
                throw new InvalidRequestException('微信支付网关证书校验失败（本地环境）。请联系管理员检查 CA 证书或关闭本地 SSL 校验。');
            }
            throw new InvalidRequestException('微信支付通道暂不可用，请稍后重试');
        }
        // 把要转换的字符串作为 QrCode 的构造函数参数
        $qrCode = new QrCode($wechatOrder->code_url);

        // 将生成的二维码图片数据以字符串形式输出，并带上相应的响应类型
        return response($qrCode->writeString(), 200, [
            'Content-Type' => $qrCode->getContentType(),
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function alipayReturn()
    {
        try {
            $data = app('alipay')->verify();
        } catch (\Exception $e) {
            return view('pages.error', ['msg' => '数据不正确']);
        }

        if (is_site_mode_b()) {
            $orderNo = (string) data_get($data, 'out_trade_no', '');
            $order = Order::query()->where('no', $orderNo)->first();
            if (!$order) {
                return view('pages.error', ['msg' => '订单不存在']);
            }

            $this->authorize('own', $order);

            $ticket = app(PaymentReturnTicketService::class)->make([
                'order_no' => $order->no,
                'origin' => 'B',
                'nonce' => bin2hex(random_bytes(16)),
                'iat' => time(),
                'exp' => time() + (int) config('site.payment_return_sign_ttl', 300),
                'return_path' => '/payment/return',
            ]);

            $redirectUrl = (string) config('site.payment_return_redirect_url', route('payment.return', [], false));

            return redirect()->to($redirectUrl . (strpos($redirectUrl, '?') === false ? '?' : '&') . http_build_query([
                'ticket' => $ticket,
            ]));
        }

        return view('pages.success', ['msg' => '付款成功']);
    }

    public function alipayNotify()
    {
        $data = app('alipay')->verify();

        if (!in_array($data->trade_status, ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            return app('alipay')->success();
        }

        $outTradeNo = (string) data_get($data, 'out_trade_no', '');

        // B 站影子单支付回调（out_trade_no 为 shadow_no）。
        $shadowOrder = ShadowOrder::query()->where('shadow_no', $outTradeNo)->first();
        if ($shadowOrder) {
            if ($shadowOrder->paid_at) {
                return app('alipay')->success();
            }

            $meta = $shadowOrder->meta ?: [];
            $meta['payment_no'] = (string) data_get($data, 'trade_no', '');
            $meta['paid_channel'] = 'alipay';
            $meta['paid_payload'] = [
                'out_trade_no' => $outTradeNo,
                'trade_no' => (string) data_get($data, 'trade_no', ''),
                'trade_status' => (string) data_get($data, 'trade_status', ''),
            ];

            $shadowOrder->update([
                'status' => 'paid',
                'paid_at' => Carbon::now(),
                'meta' => $meta,
            ]);

            NotifySiteAPaidByShadowOrderJob::dispatch($shadowOrder->id);

            return app('alipay')->success();
        }

        $order = Order::where('no', $outTradeNo)->first();
        if (!$order) {
            return 'fail';
        }

        if ($order->paid_at) {
            return app('alipay')->success();
        }

        $order->update([
            'paid_at' => Carbon::now(),
            'payment_method' => 'alipay',
            'payment_no' => (string) data_get($data, 'trade_no', ''),
        ]);
        $this->syncProcurementOrderAfterPaid($order);
        $this->afterPaid($order);

        return app('alipay')->success();
    }

    public function wechatNotify()
    {
        // 校验回调参数是否正确
        $data  = app('wechat_pay')->verify();
        $outTradeNo = (string) data_get($data, 'out_trade_no', '');

        // B 站影子单支付回调（out_trade_no 为 shadow_no）。
        $shadowOrder = ShadowOrder::query()->where('shadow_no', $outTradeNo)->first();
        if ($shadowOrder) {
            if ($shadowOrder->paid_at) {
                return app('wechat_pay')->success();
            }

            $meta = $shadowOrder->meta ?: [];
            $meta['payment_no'] = (string) data_get($data, 'transaction_id');
            $meta['paid_channel'] = 'wechat';
            $meta['paid_payload'] = [
                'out_trade_no' => $outTradeNo,
                'transaction_id' => (string) data_get($data, 'transaction_id', ''),
            ];

            $shadowOrder->update([
                'status' => 'paid',
                'paid_at' => Carbon::now(),
                'meta' => $meta,
            ]);

            NotifySiteAPaidByShadowOrderJob::dispatch($shadowOrder->id);

            return app('wechat_pay')->success();
        }

        // 找到对应的订单
        $order = Order::where('no', $outTradeNo)->first();
        // 订单不存在则告知微信支付
        if (!$order) {
            return 'fail';
        }
        // 订单已支付
        if ($order->paid_at) {
            // 告知微信支付此订单已处理
            return app('wechat_pay')->success();
        }

        // 将订单标记为已支付
        $order->update([
            'paid_at'        => Carbon::now(),
            'payment_method' => 'wechat',
            'payment_no'     => $data->transaction_id,
        ]);
        $this->syncProcurementOrderAfterPaid($order);
        $this->afterPaid($order);

        return app('wechat_pay')->success();
    }

    protected function afterPaid(Order $order)
    {
        event(new OrderPaid($order));
        NotifySiteAPaidJob::dispatch($order->id);
    }

    protected function ensureOrderPayable(Order $order)
    {
        if ($order->paid_at || $order->closed) {
            throw new InvalidRequestException('订单状态不正确');
        }

        if ($order->isAllocationExpired()) {
            $order->closeAsAllocationVoided();
            throw new InvalidRequestException('订单支付已超时，本订单已关闭');
        }
    }

    public function wechatRefundNotify(Request $request)
    {
        // 给微信的失败响应
        $failXml = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[FAIL]]></return_msg></xml>';
        $data = app('wechat_pay')->verify(null, true);

        // 没有找到对应的订单，原则上不可能发生，保证代码健壮性
        if(!$order = Order::where('no', $data['out_trade_no'])->first()) {
            return $failXml;
        }

        if ($data['refund_status'] === 'SUCCESS') {
            // 退款成功，将订单退款状态改成退款成功
            $order->update([
                'refund_status' => Order::REFUND_STATUS_SUCCESS,
            ]);
        } else {
            // 退款失败，将具体状态存入 extra 字段，并表退款状态改成失败
            $extra = $order->extra;
            $extra['refund_failed_code'] = $data['refund_status'];
            $order->update([
                'refund_status' => Order::REFUND_STATUS_FAILED,
            ]);
        }

        return app('wechat_pay')->success();
    }

    protected function ensureProcurementOrderForPayment(Order $order)
    {
        $extra = $order->extra ?: [];
        $procurementOrder = null;

        if (isset($extra['procurement_order_id'])) {
            $procurementOrder = ProcurementOrder::query()->find($extra['procurement_order_id']);
        }

        if (!$procurementOrder) {
            $procurementOrder = $this->procurementService->createFromSettlement((float) $order->total_amount);
        }

        $extra['procurement_order_id'] = $procurementOrder->id;
        $extra['procurement_order_no'] = $procurementOrder->no;
        $extra['procurement_item_name'] = $procurementOrder->item_name;
        $order->update(['extra' => $extra]);

        $procExtra = $procurementOrder->extra ?: [];
        $procExtra['linked_order_no'] = $order->no;
        $procExtra['linked_order_id'] = $order->id;
        $paymentMetadata = $this->buildEscrowPaymentMetadata($order, $procurementOrder);
        $procExtra['payment_metadata'] = [
            'subject' => $paymentMetadata['subject'],
            'body' => $paymentMetadata['body'],
            'mapped_at' => now()->toDateTimeString(),
        ];
        if (!isset($procExtra['source'])) {
            $procExtra['source'] = 'settlement';
        }
        $procurementOrder->update([
            'order_no' => $order->no,
            'extra' => $procExtra,
        ]);

        return $procurementOrder;
    }

    protected function buildEscrowPaymentMetadata(Order $order, ProcurementOrder $procurementOrder)
    {
        $taskNo = (string) ($procurementOrder->no ?: $order->no);

        return [
            'subject' => sprintf('代购劳务任务单 #%s', $taskNo),
            'body' => sprintf('跨境代购委托资金存管 - %s', $taskNo),
        ];
    }

    protected function syncProcurementOrderAfterPaid(Order $order)
    {
        $extra = $order->extra ?: [];
        $procurementOrder = null;
        if (!empty($extra['procurement_order_id'])) {
            $procurementOrder = ProcurementOrder::query()->find($extra['procurement_order_id']);
        }
        if (!$procurementOrder) {
            $procurementOrder = ProcurementOrder::query()->where('order_no', $order->no)->latest('id')->first();
        }
        if (!$procurementOrder) {
            return;
        }

        $procExtra = $procurementOrder->extra ?: [];
        $procExtra['paid_at'] = (string) $order->paid_at;
        $procExtra['payment_method'] = $order->payment_method;
        $procExtra['payment_no'] = $order->payment_no;

        $procurementOrder->update([
            'order_no' => $order->no,
            'proxy_status' => ProcurementOrder::STATUS_ACCEPTED,
            'extra' => $procExtra,
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

    protected function relayPaymentToSiteB(Order $order, $channel)
    {
        if (!in_array($channel, ['wechat', 'alipay'], true)) {
            throw new InvalidRequestException('不支持的支付渠道');
        }

        $procurementOrder = $this->ensureProcurementOrderForPayment($order);
        $metadata = $this->buildEscrowPaymentMetadata($order, $procurementOrder);

        $receiverUrl = (string) config('site.shadow_order_create_url', '');
        if ($receiverUrl === '') {
            throw new InvalidRequestException('未配置 B 站中继入口');
        }

        $secret = (string) config('site.shadow_order_sign_secret', '');
        if ($secret === '') {
            throw new InvalidRequestException('未配置中继签名密钥');
        }

        $merchantId = (string) config('site.shadow_order_merchant_id', 'site-a');
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $amountMinor = (int) round(((float) $order->total_amount) * 100);

        $payload = [
            'merchant_id' => $merchantId,
            'order_no' => (string) $order->no,
            'amount_minor' => $amountMinor,
            'currency' => 'JPY',
            'channel' => $channel,
            'source_site' => 'A',
            'return_path' => '/orders/' . $order->id,
            'subject' => (string) data_get($metadata, 'subject', ''),
            'body' => (string) data_get($metadata, 'body', ''),
            'ts' => $timestamp,
            'nonce' => $nonce,
        ];

        $bodySha256 = $this->canonicalBodyHash($payload);
        $payload['body_sha256'] = $bodySha256;
        $sign = $this->signShadowOrder($payload, $secret);
        $payload['sign'] = $sign;

        $client = new Client([
            'timeout' => (int) config('site.shadow_order_create_timeout', 5),
            'http_errors' => false,
        ]);

        try {
            $response = $client->post($receiverUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'X-Sign' => $sign,
                ],
                'json' => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new InvalidRequestException('无法连接 B 站中继服务');
        }

        $status = (int) $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);
        if (!in_array($status, [200, 201], true) || !is_array($body) || empty($body['shadow_no'])) {
            throw new InvalidRequestException('B 站中继下单失败');
        }

        $shadowNo = (string) $body['shadow_no'];
        $extra = $order->extra ?: [];
        $extra['shadow_order_no'] = $shadowNo;
        $extra['shadow_relay_to'] = 'B';
        $extra['shadow_channel'] = $channel;
        $order->update(['extra' => $extra]);

        $redirectTemplate = (string) config('site.shadow_order_pay_url_template_' . $channel, '');
        if ($redirectTemplate === '') {
            $redirectTemplate = (string) config('site.shadow_order_pay_url_template', '');
        }
        if ($redirectTemplate === '') {
            throw new InvalidRequestException('未配置 B 站支付跳转地址');
        }

        $redirectUrl = str_replace('{shadow_no}', rawurlencode($shadowNo), $redirectTemplate);
        $redirectUrl = str_replace('{channel}', rawurlencode($channel), $redirectUrl);
        $separator = (strpos($redirectUrl, '?') === false) ? '?' : '&';
        $redirectUrl .= $separator . 'sig=' . rawurlencode($sign);

        return redirect()->away($redirectUrl);
    }

    protected function canonicalBodyHash(array $payload)
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    protected function signShadowOrder(array $payload, $secret)
    {
        $canonical = implode('|', [
            (string) data_get($payload, 'merchant_id', ''),
            (string) data_get($payload, 'order_no', ''),
            (string) data_get($payload, 'amount_minor', ''),
            (string) data_get($payload, 'ts', ''),
            (string) data_get($payload, 'nonce', ''),
            (string) data_get($payload, 'body_sha256', ''),
        ]);

        return base64_encode(hash_hmac('sha256', $canonical, $secret, true));
    }

}
