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
        if (in_array($stage, [OrderFulfillmentService::STAGE_S1, OrderFulfillmentService::STAGE_S2], true)) {
            $fulfillment->enterStockPrep($order);
        }
    }

    /**
     * @param  UploadedFile[]  $files
     */
    public function batchImport(array $files): array
    {
        $success = 0;
        $failed = [];
        $skipped = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $originalName = (string) $file->getClientOriginalName();
            $orderNo = $this->guessOrderNoFromFilename($originalName);
            if ($orderNo === null || $orderNo === '') {
                $failed[] = $originalName.'：无法从文件名识别订单号';
                continue;
            }

            $order = Order::query()
                ->where('no', $orderNo)
                ->whereNotNull('paid_at')
                ->first();

            if (!$order) {
                $failed[] = $originalName.'：未找到已支付订单 '.$orderNo;
                continue;
            }

            if ($order->refund_status === Order::REFUND_STATUS_SUCCESS) {
                $skipped[] = $orderNo.'：已退款，已跳过';
                continue;
            }

            try {
                $this->store($order, $file);
                $success++;
            } catch (InvalidRequestException $e) {
                $failed[] = $originalName.'：'.$e->getMessage();
            } catch (\Throwable $e) {
                $failed[] = $originalName.'：上传失败';
            }
        }

        return compact('success', 'failed', 'skipped');
    }

    public function guessOrderNoFromFilename($filename): ?string
    {
        $base = trim((string) pathinfo((string) $filename, PATHINFO_FILENAME));
        if ($base === '') {
            return null;
        }

        if (preg_match('/(\d{15,})/', $base, $matches)) {
            return $matches[1];
        }

        return ctype_digit($base) ? $base : null;
    }
}
