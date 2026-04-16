<?php

namespace App\Admin\Controllers;

use App\Models\SiteSetting;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;

class SiteSettingsController extends Controller
{
    public function editLogo()
    {
        // Avoid conflict with laravel-admin built-in success message bag key.
        if (session()->has('success') && is_string(session('success'))) {
            session()->forget('success');
        }

        $setting = SiteSetting::query()->firstOrCreate(
            ['key' => 'header_logo'],
            ['value' => '']
        );
        $brandTextZh = SiteSetting::query()->firstOrCreate(
            ['key' => 'header_brand_text_zh'],
            ['value' => '岚山烟务所']
        );
        $brandTextEn = SiteSetting::query()->firstOrCreate(
            ['key' => 'header_brand_text_en'],
            ['value' => 'ARASHIYAMA TOBACCO SHOP']
        );
        $disableEmailVerificationForTesting = SiteSetting::query()->firstOrCreate(
            ['key' => 'disable_email_verification_for_testing'],
            ['value' => '0']
        );
        $activeSiteMode = SiteSetting::query()->firstOrCreate(
            ['key' => 'active_site_mode'],
            ['value' => strtoupper((string) config('site.mode', 'A')) === 'B' ? 'B' : 'A']
        );
        $resolvedSiteMode = site_mode();

        return Admin::content(function (Content $content) use ($setting, $brandTextZh, $brandTextEn, $disableEmailVerificationForTesting, $activeSiteMode, $resolvedSiteMode) {
            $content->header('站点设置');
            $content->description('上传前台 Header Logo');
            $content->body(view('admin.site_settings.logo', [
                'setting' => $setting,
                'brandTextZh' => $brandTextZh,
                'brandTextEn' => $brandTextEn,
                'disableEmailVerificationForTesting' => $disableEmailVerificationForTesting,
                'activeSiteMode' => $activeSiteMode,
                'resolvedSiteMode' => $resolvedSiteMode,
            ]));
        });
    }

    public function updateLogo(Request $request)
    {
        $request->validate([
            'logo' => 'nullable|image|max:8192',
            'brand_text_zh' => 'nullable|string|max:60',
            'brand_text_en' => 'nullable|string|max:120',
            'disable_email_verification_for_testing' => 'nullable|boolean',
        ]);

        $setting = SiteSetting::query()->firstOrCreate(
            ['key' => 'header_logo'],
            ['value' => '']
        );
        $brandTextZh = SiteSetting::query()->firstOrCreate(
            ['key' => 'header_brand_text_zh'],
            ['value' => '岚山烟务所']
        );
        $brandTextEn = SiteSetting::query()->firstOrCreate(
            ['key' => 'header_brand_text_en'],
            ['value' => 'ARASHIYAMA TOBACCO SHOP']
        );
        $disableEmailVerificationForTesting = SiteSetting::query()->firstOrCreate(
            ['key' => 'disable_email_verification_for_testing'],
            ['value' => '0']
        );
        $logoFile = $request->file('logo');
        if ($logoFile) {
            $path = $this->storeOptimizedLogo($logoFile);

            if ($setting->value && $setting->value !== $path) {
                Storage::disk('public')->delete($setting->value);
            }

            $setting->update(['value' => $path]);
        }

        $brandTextZh->update([
            'value' => trim((string) $request->input('brand_text_zh', '')) ?: '岚山烟务所',
        ]);
        $brandTextEn->update([
            'value' => trim((string) $request->input('brand_text_en', '')) ?: 'ARASHIYAMA TOBACCO SHOP',
        ]);
        $disableToggleInput = $request->input('disable_email_verification_for_testing');
        $disableEmailVerificationForTesting->update([
            'value' => in_array($disableToggleInput, ['1', 1, true, 'on', 'yes'], true) ? '1' : '0',
        ]);

        return redirect()
            ->route('admin.site_settings.logo.edit')
            ->with('logo_upload_success', '站点品牌信息已更新。');
    }

    /**
     * Resize and compress uploaded logo to avoid giant display and heavy payload.
     */
    protected function storeOptimizedLogo(UploadedFile $file)
    {
        $realPath = $file->getRealPath();
        $imageInfo = @getimagesize($realPath);

        if (!$imageInfo || !isset($imageInfo['mime'])) {
            return $file->store('images/brand', 'public');
        }

        $mime = $imageInfo['mime'];
        if (!function_exists('imagecreatetruecolor')) {
            return $file->store('images/brand', 'public');
        }

        $src = $this->createImageResource($realPath, $mime);
        if (!$src) {
            return $file->store('images/brand', 'public');
        }

        $srcWidth = imagesx($src);
        $srcHeight = imagesy($src);
        // Keep output crisp while avoiding oversized files.
        $maxWidth = 640;
        $maxHeight = 220;
        $ratio = min($maxWidth / $srcWidth, $maxHeight / $srcHeight, 1);
        $targetWidth = max(1, (int) floor($srcWidth * $ratio));
        $targetHeight = max(1, (int) floor($srcHeight * $ratio));

        // If the upload is already small enough, keep original bytes to avoid recompression blur.
        if ($ratio >= 0.999) {
            imagedestroy($src);
            return $file->store('images/brand', 'public');
        }

        $dst = imagecreatetruecolor($targetWidth, $targetHeight);
        if (in_array($mime, ['image/png', 'image/gif', 'image/webp'], true)) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
            imagefilledrectangle($dst, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled(
            $dst,
            $src,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $srcWidth,
            $srcHeight
        );

        // A tiny sharpen pass helps preserve text edges after downscaling.
        if (function_exists('imageconvolution')) {
            @imageconvolution($dst, [
                [-1, -1, -1],
                [-1, 16, -1],
                [-1, -1, -1],
            ], 8, 0);
        }

        $preferPng = in_array($mime, ['image/png', 'image/gif', 'image/webp'], true);
        $extension = $preferPng ? 'png' : 'jpg';
        $outputPath = 'images/brand/header-logo-'.time().'.'.$extension;

        ob_start();
        if ($preferPng) {
            imagepng($dst, null, 2);
        } else {
            imagejpeg($dst, null, 95);
        }
        $binary = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        Storage::disk('public')->put($outputPath, $binary);

        return $outputPath;
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
