<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidRequestException;
use App\Models\Order;
use App\Models\ProcurementOrder;
use App\Services\CrossSitePaymentService;
use App\Services\ExchangeRateService;
use App\Services\ProcurementService;
use App\Events\OrderPaid;
use Carbon\Carbon;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    /** @var ProcurementService */
    protected $procurementService;

    /** @var CrossSitePaymentService */
    protected $crossSitePayment;

    /** @var ExchangeRateService */
    protected $exchangeRateService;

    public function __construct(
        ProcurementService $procurementService,
        CrossSitePaymentService $crossSitePayment,
        ExchangeRateService $exchangeRateService
    ) {
        $this->procurementService = $procurementService;
        $this->crossSitePayment = $crossSitePayment;
        $this->exchangeRateService = $exchangeRateService;
    }

    public function crossPay(Order $order, Request $request)
    {
        if (!$this->crossSitePayment->verify($request, $order)) {
            return view('pages.error', ['msg' => '支付链接无效或已过期，请返回 A 站订单页重新发起支付。']);
        }

        if ($order->paid_at || $order->closed || $order->isAllocationExpired()) {
            return view('pages.error', ['msg' => '订单状态不可支付，请返回订单页查看。']);
        }

        Auth::loginUsingId($order->user_id);
        $this->crossSitePayment->markEscrow($order);

        $method = strtolower((string) $request->query('method'));

        return redirect()->route(
            $method === 'wechat' ? 'payment.wechat' : 'payment.alipay',
            ['order' => $order->id]
        );
    }

    public function payByAlipay(Order $order, Request $request)
    {
        $this->authorize('own', $order);
        $this->ensureOrderPayable($order);

        if ($this->crossSitePayment->shouldRedirectToSiteB()) {
            return redirect()->away($this->crossSitePayment->buildSignedPayUrl($order, 'alipay'));
        }

        return $this->renderAlipayPage($order);
    }

    public function payByWechat(Order $order, Request $request)
    {
        $this->authorize('own', $order);
        $this->ensureOrderPayable($order);

        if ($this->crossSitePayment->shouldRedirectToSiteB()) {
            return redirect()->away($this->crossSitePayment->buildSignedPayUrl($order, 'wechat'));
        }

        return $this->renderWechatPage($order);
    }

    public function launchAlipay(Order $order, Request $request)
    {
        $this->authorize('own', $order);
        $this->ensureOrderPayable($order);

        if ($this->crossSitePayment->shouldRedirectToSiteB()) {
            return redirect()->away($this->crossSitePayment->buildSignedPayUrl($order, 'alipay'));
        }

        $quote = $this->preparePaymentQuote($order);
        $procurementOrder = $this->ensureProcurementOrderForPayment($order);
        $metadata = $this->buildEscrowPaymentMetadata($order, $procurementOrder);

        $response = app('alipay')->web([
            'out_trade_no' => $order->no,
            'total_amount' => $quote['payment_amount_cny'],
            'subject'      => $metadata['subject'],
            'timeout_express' => max(1, (int) ceil($order->getAllocationExpiresIn() / 60)).'m',
        ]);

        return $this->withNoReferrerPolicy($response);
    }

    public function wechatQrImage(Order $order, Request $request)
    {
        $this->authorize('own', $order);
        $this->ensureOrderPayable($order);

        $result = $this->createWechatPayQrCode($order);

        return response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function alipayReturn()
    {
        try {
            app('alipay')->verify();
        } catch (\Exception $e) {
            return view('pages.error', ['msg' => '数据不正确']);
        }

        $this->crossSitePayment->clearEscrow();

        return redirect()->away($this->crossSitePayment->siteAReturnUrl());
    }

    public function alipayNotify()
    {
        $data  = app('alipay')->verify();
        if (!in_array($data->trade_status, ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            return app('alipay')->success();
        }

        $order = Order::where('no', $data->out_trade_no)->first();
        if (!$order) {
            return 'fail';
        }
        if ($order->paid_at) {
            return app('alipay')->success();
        }

        $order->update([
            'paid_at'        => Carbon::now(),
            'payment_method' => 'alipay',
            'payment_no'     => $data->trade_no,
        ]);
        $this->syncProcurementOrderAfterPaid($order);
        $this->afterPaid($order);

        return app('alipay')->success();
    }

    public function wechatNotify()
    {
        $data  = app('wechat_pay')->verify();
        $order = Order::where('no', $data->out_trade_no)->first();
        if (!$order) {
            return 'fail';
        }
        if ($order->paid_at) {
            return app('wechat_pay')->success();
        }

        $order->update([
            'paid_at'        => Carbon::now(),
            'payment_method' => 'wechat',
            'payment_no'     => $data->transaction_id,
        ]);
        $this->syncProcurementOrderAfterPaid($order);
        $this->afterPaid($order);

        return app('wechat_pay')->success();
    }

    public function crossRefund(Order $order, Request $request)
    {
        if (!is_site_mode_b()) {
            abort(404);
        }

        if (!$this->crossSitePayment->verifyRefundRequest($request, $order)) {
            return response()->json(['message' => '退款请求无效或已过期'], 403);
        }

        if (!$order->paid_at) {
            return response()->json(['message' => '订单未支付，无法退款'], 422);
        }

        $refundNo = trim((string) $request->input('refund_no'));
        $payCny = round((float) $request->input('pay_cny'), 2);
        $refundCny = round((float) $request->input('refund_cny'), 2);

        try {
            $order = app(\App\Services\OrderRefundService::class)->executeGatewayRefund(
                $order->fresh(),
                $refundNo,
                $payCny,
                $refundCny
            );

            return response()->json([
                'message' => '退款请求已提交',
                'refund_status' => $order->refund_status,
                'refund_no' => $order->refund_no,
            ]);
        } catch (\App\Exceptions\InvalidRequestException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('跨站退款执行失败', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => '支付平台退款失败，请稍后重试'], 500);
        }
    }

    public function wechatRefundNotify(Request $request)
    {
        $failXml = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[FAIL]]></return_msg></xml>';
        $data = app('wechat_pay')->verify(null, true);

        if (!$order = Order::where('no', $data['out_trade_no'])->first()) {
            return $failXml;
        }

        if ($data['refund_status'] === 'SUCCESS') {
            $order->update([
                'refund_status' => Order::REFUND_STATUS_SUCCESS,
            ]);
            $order->refresh();
            app(\App\Services\OrderRefundService::class)->notifyRefundSuccessPublic($order);
        } else {
            $extra = $order->extra;
            $extra['refund_failed_code'] = $data['refund_status'];
            $order->update([
                'refund_status' => Order::REFUND_STATUS_FAILED,
                'extra' => $extra,
            ]);
        }

        return app('wechat_pay')->success();
    }

    protected function renderAlipayPage(Order $order)
    {
        $quote = $this->preparePaymentQuote($order);

        return view('payment.alipay_page', [
            'order' => $order,
            'payAmount' => $quote['payment_amount_cny'],
            'exchangeRate' => $quote['exchange_rate'],
            'amountJpy' => $quote['amount_jpy'],
            'launchUrl' => route('payment.alipay.launch', ['order' => $order->id]),
        ]);
    }

    protected function renderWechatPage(Order $order)
    {
        $quote = $this->preparePaymentQuote($order);
        $result = $this->createWechatPayQrCode($order);
        $qrBinary = $result->getString();

        return view('payment.wechat_qr_page', [
            'order' => $order,
            'payAmount' => $quote['payment_amount_cny'],
            'exchangeRate' => $quote['exchange_rate'],
            'amountJpy' => $quote['amount_jpy'],
            'qrImageDataUri' => 'data:'.$result->getMimeType().';base64,'.base64_encode($qrBinary),
            'qrImageUrl' => route('payment.wechat.qr', ['order' => $order->id]),
        ]);
    }

    protected function createWechatPayQrCode(Order $order)
    {
        $quote = $this->preparePaymentQuote($order);
        $procurementOrder = $this->ensureProcurementOrderForPayment($order);
        $metadata = $this->buildEscrowPaymentMetadata($order, $procurementOrder);

        $wechatOrder = app('wechat_pay')->scan([
            'out_trade_no' => $order->no,
            'total_fee'    => (int) round($quote['payment_amount_cny'] * 100),
            'body'         => $metadata['body'],
            'time_expire'  => $order->getAllocationExpiresAt()->format('YmdHis'),
        ]);

        return (new PngWriter())->write(
            QrCode::create($wechatOrder->code_url)->setSize(300)->setMargin(10)
        );
    }

    protected function preparePaymentQuote(Order $order)
    {
        return $this->exchangeRateService->snapshotQuoteOnOrder($order->fresh());
    }

    protected function afterPaid(Order $order)
    {
        $this->crossSitePayment->clearEscrow();
        event(new OrderPaid($order));
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

    protected function ensureProcurementOrderForPayment(Order $order)
    {
        $extra = $order->extra ?: [];
        $procurementOrder = null;

        if (isset($extra['procurement_order_id'])) {
            $procurementOrder = ProcurementOrder::query()->find($extra['procurement_order_id']);
        }

        if (!$procurementOrder) {
            $procurementOrder = $this->procurementService->createFromSettlement((float) $order->getAmountJpy());
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
}
