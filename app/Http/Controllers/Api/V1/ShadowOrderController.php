<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ShadowOrder;
use App\Services\ShadowReferenceLibraryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShadowOrderController extends Controller
{
    public function store(Request $request)
    {
        $this->validate($request, [
            'merchant_id' => ['required', 'string', 'max:64'],
            'order_no' => ['required', 'string', 'max:64'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'channel' => ['nullable', 'string', 'in:wechat,alipay'],
            'source_site' => ['nullable', 'string', 'max:16'],
            'return_path' => ['nullable', 'string', 'max:255'],
            'ts' => ['required', 'integer', 'min:1'],
            'nonce' => ['required', 'string', 'max:128'],
            'body_sha256' => ['required', 'string', 'size:64'],
            'sign' => ['required', 'string', 'max:255'],
        ]);

        $sourceSite = strtoupper((string) $request->input('source_site', 'A'));
        $sourceOrderNo = (string) $request->input('order_no');
        $merchantId = (string) $request->input('merchant_id');
        $amountMinor = (int) $request->input('amount_minor');
        $amount = number_format($amountMinor / 100, 2, '.', '');
        $currency = strtoupper((string) $request->input('currency', 'JPY'));
        $channel = (string) $request->input('channel', 'alipay');
        $returnPath = (string) $request->input('return_path', '/orders');
        $timestamp = (int) $request->input('ts');
        $nonce = (string) $request->input('nonce');
        $bodySha256 = (string) $request->input('body_sha256');
        $sign = (string) $request->input('sign');
        $referenceItem = app(ShadowReferenceLibraryService::class)->pickByAmount($amount);

        list($shadowOrder, $created) = DB::transaction(function () use (
            $sourceSite,
            $sourceOrderNo,
            $merchantId,
            $amountMinor,
            $amount,
            $currency,
            $channel,
            $returnPath,
            $timestamp,
            $nonce,
            $bodySha256,
            $sign,
            $referenceItem,
            $request
        ) {
            $existing = ShadowOrder::query()
                ->where('source_site', $sourceSite)
                ->where('source_order_no', $sourceOrderNo)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return [$existing, false];
            }

            $shadowOrder = ShadowOrder::query()->create([
                'shadow_no' => $this->generateShadowNo(),
                'source_site' => $sourceSite,
                'source_order_no' => $sourceOrderNo,
                'merchant_id' => $merchantId,
                'amount_minor' => $amountMinor,
                'amount' => $amount,
                'currency' => $currency,
                'channel' => $channel,
                'return_path' => $returnPath,
                'status' => 'pending',
                'signature_hash' => hash('sha256', $sign),
                'meta' => [
                    'site_mode' => site_mode(),
                    'ip' => $request->ip(),
                    'ua' => (string) $request->userAgent(),
                    'timestamp' => $timestamp,
                    'nonce' => $nonce,
                    'body_sha256' => $bodySha256,
                    'reference_item' => $referenceItem,
                    'pricing_snapshot' => data_get($referenceItem, 'pricing', []),
                ],
            ]);

            return [$shadowOrder, true];
        });

        return response()->json([
            'id' => $shadowOrder->id,
            'shadow_no' => $shadowOrder->shadow_no,
            'source_site' => $shadowOrder->source_site,
            'source_order_no' => $shadowOrder->source_order_no,
            'amount_minor' => (int) $shadowOrder->amount_minor,
            'amount' => number_format((float) $shadowOrder->amount, 2, '.', ''),
            'currency' => (string) data_get($shadowOrder, 'currency', 'JPY'),
            'status' => $shadowOrder->status,
            'item_name' => (string) data_get($referenceItem, 'item_name', ''),
            'item_image' => (string) data_get($referenceItem, 'image_url', ''),
            'order_narrative' => (string) data_get($referenceItem, 'narrative', ''),
            'reference_strategy' => (string) data_get($referenceItem, 'strategy', ''),
        ], $created ? 201 : 200);
    }

    protected function generateShadowNo()
    {
        return 'SO' . date('YmdHis') . substr((string) mt_rand(100000, 999999), -6);
    }
}
