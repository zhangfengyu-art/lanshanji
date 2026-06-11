<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SiteFaviconService
{
    const KEY_HEADER_LOGO = 'header_logo';
    const KEY_FAVICON_A = 'favicon_a';
    const KEY_FAVICON_B = 'favicon_b';

    public function faviconKeyForMode($mode = null)
    {
        $mode = strtoupper((string) ($mode ?: site_mode()));

        return $mode === 'B' ? self::KEY_FAVICON_B : self::KEY_FAVICON_A;
    }

    public function storagePathForMode($mode = null)
    {
        $key = $this->faviconKeyForMode($mode);
        $path = trim((string) SiteSetting::query()->where('key', $key)->value('value'));

        if ($path !== '') {
            return ltrim(str_replace('\\', '/', $path), '/');
        }

        if ($key === self::KEY_FAVICON_A) {
            $fallback = trim((string) SiteSetting::query()
                ->where('key', self::KEY_HEADER_LOGO)
                ->value('value'));

            return $fallback !== '' ? ltrim(str_replace('\\', '/', $fallback), '/') : '';
        }

        return '';
    }

    public function absolutePathForMode($mode = null)
    {
        $relative = $this->storagePathForMode($mode);
        if ($relative === '') {
            return null;
        }

        $fullPath = storage_path('app/public/'.$relative);

        return is_file($fullPath) && filesize($fullPath) > 0 ? $fullPath : null;
    }

    public function publishFromCurrentFavicon()
    {
        $relative = $this->storagePathForMode();
        if ($relative === '') {
            return false;
        }

        return $this->publishFromStoragePath($relative);
    }

    public function publishFromStoragePath($storageRelativePath)
    {
        $relative = ltrim(str_replace('\\', '/', (string) $storageRelativePath), '/');
        if ($relative === '') {
            return false;
        }

        $sourcePath = storage_path('app/public/'.$relative);
        if (!is_readable($sourcePath) || filesize($sourcePath) < 1) {
            return false;
        }

        $this->removeBrokenFaviconFiles();

        $targetPng = public_path('favicon.png');
        $targetIco = public_path('favicon.ico');
        $published = false;

        if (!function_exists('imagecreatetruecolor')) {
            $published = $this->copyAsFavicon($sourcePath, $targetPng, $targetIco);
        } else {
            $imageInfo = @getimagesize($sourcePath);
            if (!$imageInfo || !isset($imageInfo['mime'])) {
                $published = $this->copyAsFavicon($sourcePath, $targetPng, $targetIco);
            } else {
                $src = $this->createImageResource($sourcePath, $imageInfo['mime']);
                if (!$src) {
                    $published = $this->copyAsFavicon($sourcePath, $targetPng, $targetIco);
                } else {
                    $published = $this->resizeToFaviconPng($src, $targetPng);
                    imagedestroy($src);
                }
            }
        }

        if ($published && is_file($targetPng) && filesize($targetPng) > 0) {
            @copy($targetPng, $targetIco);
            @chmod($targetPng, 0644);
            @chmod($targetIco, 0644);

            return is_file($targetIco) && filesize($targetIco) > 0;
        }

        $this->removeBrokenFaviconFiles();

        return false;
    }

    /**
     * 保存上传的标签页图标（32x32 PNG）到 storage。
     */
    public function storeUploadedFavicon(UploadedFile $file, $namePrefix)
    {
        $this->ensureBrandDirectoryExists();

        $realPath = $file->getRealPath();
        if (!$realPath || !is_readable($realPath)) {
            throw new \RuntimeException('无法读取上传的图标文件。');
        }

        $outputPath = 'images/brand/'.$namePrefix.'-'.time().'.png';

        if (!function_exists('imagecreatetruecolor')) {
            $stored = $file->storeAs('images/brand', basename($outputPath), 'public');
            if (!$stored) {
                throw new \RuntimeException('无法保存图标文件。');
            }

            return $stored;
        }

        $imageInfo = @getimagesize($realPath);
        if (!$imageInfo || !isset($imageInfo['mime'])) {
            $stored = $file->storeAs('images/brand', basename($outputPath), 'public');
            if (!$stored) {
                throw new \RuntimeException('无法保存图标文件。');
            }

            return $stored;
        }

        $src = $this->createImageResource($realPath, $imageInfo['mime']);
        if (!$src) {
            $stored = $file->storeAs('images/brand', basename($outputPath), 'public');
            if (!$stored) {
                throw new \RuntimeException('无法保存图标文件。');
            }

            return $stored;
        }

        $targetPng = storage_path('app/public/'.$outputPath);
        $saved = $this->resizeToFaviconPng($src, $targetPng);
        imagedestroy($src);

        if (!$saved) {
            throw new \RuntimeException('图标压缩失败，请换一张较小的图片。');
        }

        return $outputPath;
    }

    public function ensureBrandDirectoryExists()
    {
        $disk = Storage::disk('public');
        foreach (['images', 'images/brand'] as $dir) {
            if ($disk->exists($dir)) {
                continue;
            }

            if (!$disk->makeDirectory($dir)) {
                throw new \RuntimeException('无法创建 '.$dir.' 目录。');
            }
        }
    }

    protected function removeBrokenFaviconFiles()
    {
        foreach ([public_path('favicon.png'), public_path('favicon.ico')] as $file) {
            if (is_file($file) && filesize($file) < 1) {
                @unlink($file);
            }
        }
    }

    protected function copyAsFavicon($sourcePath, $targetPng, $targetIco)
    {
        $copiedPng = @copy($sourcePath, $targetPng);
        $copiedIco = $copiedPng ? @copy($sourcePath, $targetIco) : false;

        return $copiedPng && $copiedIco && filesize($targetPng) > 0 && filesize($targetIco) > 0;
    }

    protected function resizeToFaviconPng($src, $targetPng)
    {
        $size = 32;
        $dst = imagecreatetruecolor($size, $size);
        if (!$dst) {
            return false;
        }

        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $size, $size, $transparent);

        $srcWidth = max(1, imagesx($src));
        $srcHeight = max(1, imagesy($src));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $size, $size, $srcWidth, $srcHeight);

        $saved = imagepng($dst, $targetPng, 2);
        imagedestroy($dst);

        return $saved && is_file($targetPng) && filesize($targetPng) > 0;
    }

    protected function createImageResource($path, $mime)
    {
        switch ($mime) {
            case 'image/jpeg':
                return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : null;
            case 'image/png':
                return function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : null;
            case 'image/gif':
                return function_exists('imagecreatefromgif') ? @imagecreatefromgif($path) : null;
            case 'image/webp':
                return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
            default:
                return null;
        }
    }
}
