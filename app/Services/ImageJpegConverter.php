<?php

namespace App\Services;

class ImageJpegConverter
{
    public function convertFileToJpeg($sourcePath, $targetPath, $quality = 90)
    {
        return $this->saveResizedJpeg($sourcePath, $targetPath, 0, $quality);
    }

    public function saveResizedJpeg($sourcePath, $targetPath, $maxLongEdge = 0, $quality = 90)
    {
        $image = $this->loadImageResource($sourcePath);
        if (!$image) {
            return false;
        }

        if (\function_exists('imagepalettetotruecolor')) {
            \imagepalettetotruecolor($image);
        }

        $width = \imagesx($image);
        $height = \imagesy($image);
        $maxLongEdge = (int) $maxLongEdge;

        if ($maxLongEdge > 0) {
            $longEdge = max($width, $height);
            if ($longEdge > $maxLongEdge) {
                $scale = $maxLongEdge / $longEdge;
                $targetWidth = max(1, (int) round($width * $scale));
                $targetHeight = max(1, (int) round($height * $scale));
            } else {
                $targetWidth = $width;
                $targetHeight = $height;
            }
        } else {
            $targetWidth = $width;
            $targetHeight = $height;
        }

        $canvas = \imagecreatetruecolor($targetWidth, $targetHeight);
        $white = \imagecolorallocate($canvas, 255, 255, 255);
        \imagefill($canvas, 0, 0, $white);
        \imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        \imagedestroy($image);

        $saved = \imagejpeg($canvas, $targetPath, max(1, min(100, (int) $quality)));
        \imagedestroy($canvas);

        return $saved;
    }

    public function loadImageResource($sourcePath)
    {
        $type = $this->detectImageType($sourcePath);

        switch ($type) {
            case IMAGETYPE_JPEG:
                return @\imagecreatefromjpeg($sourcePath);
            case IMAGETYPE_PNG:
                return @\imagecreatefrompng($sourcePath);
            case IMAGETYPE_GIF:
                return @\imagecreatefromgif($sourcePath);
            case IMAGETYPE_WEBP:
                if (\function_exists('imagecreatefromwebp')) {
                    return @\imagecreatefromwebp($sourcePath);
                }
                return null;
            default:
                return $this->loadImageByTrial($sourcePath);
        }
    }

    protected function detectImageType($sourcePath)
    {
        if (\function_exists('exif_imagetype')) {
            $type = @\exif_imagetype($sourcePath);
            if ($type) {
                return $type;
            }
        }

        $extension = strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (in_array($extension, ['jpg', 'jpeg'], true)) {
            return IMAGETYPE_JPEG;
        }
        if ($extension === 'png') {
            return IMAGETYPE_PNG;
        }
        if ($extension === 'gif') {
            return IMAGETYPE_GIF;
        }
        if ($extension === 'webp') {
            return IMAGETYPE_WEBP;
        }

        $header = @file_get_contents($sourcePath, false, null, 0, 16);
        if ($header !== false && strlen($header) >= 12) {
            if (substr($header, 0, 2) === "\xFF\xD8") {
                return IMAGETYPE_JPEG;
            }
            if (substr($header, 0, 3) === 'GIF') {
                return IMAGETYPE_GIF;
            }
            if (substr($header, 0, 8) === "\x89PNG\r\n\x1a\n") {
                return IMAGETYPE_PNG;
            }
            if (substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP') {
                return IMAGETYPE_WEBP;
            }
        }

        return null;
    }

    protected function loadImageByTrial($sourcePath)
    {
        $loaders = [];

        if (\function_exists('imagecreatefromwebp')) {
            $loaders[] = 'imagecreatefromwebp';
        }
        $loaders[] = 'imagecreatefromjpeg';
        $loaders[] = 'imagecreatefrompng';
        $loaders[] = 'imagecreatefromgif';

        foreach ($loaders as $loader) {
            $image = @$loader($sourcePath);
            if ($image) {
                return $image;
            }
        }

        return null;
    }
}
