<?php

namespace App\Services;

use App\Models\SiteSetting;

class SiteFaviconService
{
    public function needsRepublish()
    {
        $png = public_path('favicon.png');
        $ico = public_path('favicon.ico');

        if (!is_file($png) || filesize($png) < 1) {
            return true;
        }

        if (!is_file($ico) || filesize($ico) < 1) {
            return true;
        }

        return false;
    }

    public function publishFromCurrentLogo()
    {
        $path = trim((string) SiteSetting::query()
            ->where('key', 'header_logo')
            ->value('value'));

        if ($path === '') {
            return false;
        }

        return $this->publishFromStoragePath($path);
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
