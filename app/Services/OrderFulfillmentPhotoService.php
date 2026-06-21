<?php

namespace App\Services;

use App\Exceptions\InvalidRequestException;
use App\Models\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderFulfillmentPhotoService
{
    const MAX_UPLOAD_KB = 20480;

    const STORED_MAX_LONG_EDGE = 2000;

    const STORED_JPEG_QUALITY = 85;

    public function store(Order $order, UploadedFile $file): void
    {
        if (!$order->paid_at) {
            throw new InvalidRequestException('仅已支付订单可上传实拍图');
        }

        if (!$file->isValid()) {
            throw new InvalidRequestException($this->uploadErrorMessage($file));
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (in_array($extension, ['heic', 'heif'], true)) {
            throw new InvalidRequestException(
                '当前无法直接处理 iPhone HEIC 照片。请在 iPhone「设置 → 相机 → 格式」选「最兼容」，'
                . '或用 Safari 打开后台重新拍照上传。'
            );
        }

        $directory = 'orders/fulfillment/'.$order->id;
        $filename = Str::random(32).'.jpg';
        $relativePath = $directory.'/'.$filename;
        $absolutePath = Storage::disk('private')->path($relativePath);

        if (!is_dir(dirname($absolutePath)) && !@mkdir(dirname($absolutePath), 0755, true) && !is_dir(dirname($absolutePath))) {
            throw new InvalidRequestException('服务器存储目录不可用，请联系管理员。');
        }

        $converter = app(ImageJpegConverter::class);
        if (!$converter->saveResizedJpeg(
            $file->getRealPath(),
            $absolutePath,
            self::STORED_MAX_LONG_EDGE,
            self::STORED_JPEG_QUALITY
        )) {
            throw new InvalidRequestException(
                '无法识别该图片格式。请改用 JPG/PNG，或在 iPhone 相机设置中选「最兼容」后重试。'
            );
        }

        $extra = $order->extra ?: [];
        $oldPath = trim((string) data_get($extra, 'fulfillment_photo', ''));
        if ($oldPath !== '' && $oldPath !== $relativePath) {
            $this->deleteStoredVariants($oldPath);
        }

        $extra['fulfillment_photo'] = $relativePath;
        $extra['fulfillment_photo_uploaded_at'] = now()->toDateTimeString();
        $order->update(['extra' => $extra]);
        $order->refresh();

        $fulfillment = app(OrderFulfillmentService::class);
        $fulfillment->ensureProcessingStartedFromPhoto($order);
        $order->refresh();

        $stage = $fulfillment->resolveStage($order);
        if ($stage === OrderFulfillmentService::STAGE_S1) {
            $fulfillment->enterStockPrep($order);
        }
    }

    public function deleteStoredVariants($relativePath)
    {
        $disk = Storage::disk('private');
        if ($relativePath !== '' && $disk->exists($relativePath)) {
            $disk->delete($relativePath);
        }

        $fullPath = $disk->path($relativePath);
        $pattern = dirname($fullPath).'/.thumb-*';
        foreach (glob($pattern) ?: [] as $thumbPath) {
            if (is_file($thumbPath)) {
                @unlink($thumbPath);
            }
        }
    }

    public function thumbCachePath($relativePath, $maxEdge)
    {
        $fullPath = Storage::disk('private')->path($relativePath);
        $base = pathinfo($fullPath, PATHINFO_FILENAME);

        return dirname($fullPath).'/.thumb-'.$maxEdge.'-'.$base.'.jpg';
    }

    protected function uploadErrorMessage(UploadedFile $file)
    {
        switch ($file->getError()) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return '照片太大，请换一张较小的图片或在相机里调低分辨率后重试。';
            case UPLOAD_ERR_PARTIAL:
                return '照片上传不完整，请检查网络后重试。';
            case UPLOAD_ERR_NO_FILE:
                return '未选择照片，请重新选择后再上传。';
            default:
                return '照片上传失败（错误码 '.$file->getError().'），请刷新页面后重试。';
        }
    }
}
