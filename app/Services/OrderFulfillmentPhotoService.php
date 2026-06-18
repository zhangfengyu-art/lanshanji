<?php

namespace App\Services;

use App\Exceptions\InvalidRequestException;
use App\Models\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderFulfillmentPhotoService
{
    public function store(Order $order, UploadedFile $file): void
    {
        if (!$order->paid_at) {
            throw new InvalidRequestException('仅已支付订单可上传实拍图');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }

        $filename = Str::random(32).'.'.$extension;
        $path = $file->storeAs('orders/fulfillment/'.$order->id, $filename, 'private');

        $extra = $order->extra ?: [];
        $oldPath = trim((string) data_get($extra, 'fulfillment_photo', ''));
        if ($oldPath !== '' && $oldPath !== $path && Storage::disk('private')->exists($oldPath)) {
            Storage::disk('private')->delete($oldPath);
        }

        $extra['fulfillment_photo'] = $path;
        $extra['fulfillment_photo_uploaded_at'] = now()->toDateTimeString();
        $order->update(['extra' => $extra]);
        $order->refresh();

        $fulfillment = app(OrderFulfillmentService::class);
        $stage = $fulfillment->resolveStage($order);
        if ($stage === OrderFulfillmentService::STAGE_S1) {
            $fulfillment->enterStockPrep($order);
        }
    }
}
