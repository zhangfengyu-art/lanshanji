<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageUploadService
{
    /**
     * 将已保存的 WebP 主图转为 JPEG，便于旧版校验与部分环境兼容。
     */
    public function normalizeStoredImageToJpeg(Product $product): void
    {
        $path = (string) $product->image;
        if ($path === '' || !preg_match('/\.webp$/i', $path)) {
            return;
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($path)) {
            return;
        }

        if (!function_exists('imagecreatefromwebp') || !function_exists('imagejpeg')) {
            return;
        }

        $image = @imagecreatefromwebp($disk->path($path));
        if (!$image) {
            return;
        }

        if (function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $newPath = preg_replace('/\.webp$/i', '.jpg', $path);
        if ($newPath === $path) {
            $newPath = Str::replaceLast('.webp', '.jpg', $path);
        }

        $saved = @imagejpeg($image, $disk->path($newPath), 90);
        imagedestroy($image);

        if (!$saved) {
            return;
        }

        $disk->delete($path);
        $product->forceFill(['image' => $newPath])->save();
    }
}
