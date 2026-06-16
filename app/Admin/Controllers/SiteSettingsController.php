<?php

namespace App\Admin\Controllers;

use App\Models\SiteSetting;
use App\Services\ExchangeRateService;
use App\Services\SiteAfterSaleGroupService;
use App\Services\SiteFaviconService;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;

class SiteSettingsController extends Controller
{
    public function editLogo()
    {
        if (session()->has('success') && is_string(session('success'))) {
            session()->forget('success');
        }

        $setting = $this->firstOrCreateSetting(SiteFaviconService::KEY_HEADER_LOGO, '');
        $faviconA = $this->firstOrCreateSetting(SiteFaviconService::KEY_FAVICON_A, '');
        $faviconB = $this->firstOrCreateSetting(SiteFaviconService::KEY_FAVICON_B, '');
        $brandTextZh = $this->firstOrCreateSetting('header_brand_text_zh', '岚山烟务所');
        $brandTextEn = $this->firstOrCreateSetting('header_brand_text_en', 'ARASHIYAMA TOBACCO SHOP');
        $disableEmailVerificationForTesting = $this->firstOrCreateSetting('disable_email_verification_for_testing', '0');
        $activeSiteMode = $this->firstOrCreateSetting(
            'active_site_mode',
            strtoupper((string) config('site.mode', 'A')) === 'B' ? 'B' : 'A'
        );
        $jpyPerCny = $this->firstOrCreateSetting(
            ExchangeRateService::SETTING_KEY,
            (string) config('site.default_jpy_per_cny', 22)
        );
        $afterSaleGroupQr = $this->firstOrCreateSetting(SiteAfterSaleGroupService::KEY_QR_IMAGE, '');
        $afterSaleGroupNotice = $this->firstOrCreateSetting(
            SiteAfterSaleGroupService::KEY_NOTICE,
            '扫码加入海淘售后群'
        );

        return Admin::content(function (Content $content) use (
            $setting,
            $faviconA,
            $faviconB,
            $brandTextZh,
            $brandTextEn,
            $disableEmailVerificationForTesting,
            $activeSiteMode,
            $jpyPerCny,
            $afterSaleGroupQr,
            $afterSaleGroupNotice
        ) {
            $content->header('站点设置');
            $content->description('维护站点品牌信息与运行模式');
            $content->body(view('admin.site_settings.logo', [
                'setting' => $setting,
                'faviconA' => $faviconA,
                'faviconB' => $faviconB,
                'brandTextZh' => $brandTextZh,
                'brandTextEn' => $brandTextEn,
                'disableEmailVerificationForTesting' => $disableEmailVerificationForTesting,
                'activeSiteMode' => $activeSiteMode,
                'jpyPerCny' => $jpyPerCny,
                'afterSaleGroupQr' => $afterSaleGroupQr,
                'afterSaleGroupNotice' => $afterSaleGroupNotice,
            ]));
        });
    }

    public function updateLogo(Request $request)
    {
        try {
            $this->validate($request, [
                'logo' => 'nullable|file|max:8192',
                'favicon_a' => 'nullable|file|max:2048',
                'favicon_b' => 'nullable|file|max:2048',
                'brand_text_zh' => 'nullable|string|max:60',
                'brand_text_en' => 'nullable|string|max:120',
                'disable_email_verification_for_testing' => 'nullable|boolean',
                'active_site_mode' => 'required|in:A,B',
                'jpy_per_cny' => 'required|numeric|min:0.01',
                'after_sale_group_qr' => 'nullable|file|max:4096',
                'after_sale_group_notice' => 'nullable|string|max:120',
            ]);

            $setting = $this->firstOrCreateSetting(SiteFaviconService::KEY_HEADER_LOGO, '');
            $faviconA = $this->firstOrCreateSetting(SiteFaviconService::KEY_FAVICON_A, '');
            $faviconB = $this->firstOrCreateSetting(SiteFaviconService::KEY_FAVICON_B, '');
            $brandTextZh = $this->firstOrCreateSetting('header_brand_text_zh', '岚山烟务所');
            $brandTextEn = $this->firstOrCreateSetting('header_brand_text_en', 'ARASHIYAMA TOBACCO SHOP');
            $disableEmailVerificationForTesting = $this->firstOrCreateSetting('disable_email_verification_for_testing', '0');
            $activeSiteMode = $this->firstOrCreateSetting(
                'active_site_mode',
                strtoupper((string) config('site.mode', 'A')) === 'B' ? 'B' : 'A'
            );
            $jpyPerCny = $this->firstOrCreateSetting(
                ExchangeRateService::SETTING_KEY,
                (string) config('site.default_jpy_per_cny', 22)
            );
            $afterSaleGroupQr = $this->firstOrCreateSetting(SiteAfterSaleGroupService::KEY_QR_IMAGE, '');
            $afterSaleGroupNotice = $this->firstOrCreateSetting(
                SiteAfterSaleGroupService::KEY_NOTICE,
                '扫码加入海淘售后群'
            );

            $faviconService = app(SiteFaviconService::class);

            $logoFile = $request->file('logo');
            if ($logoFile) {
                if (!$this->isAllowedImageFile($logoFile)) {
                    return $this->redirectWithImageError($request, 'logo', '仅支持 JPG、PNG、GIF、WebP 格式的图片。');
                }

                $path = $this->storeOptimizedLogo($logoFile);
                $this->replaceStoredImage($setting, $path);
            }

            $faviconAFile = $request->file('favicon_a');
            if ($faviconAFile) {
                if (!$this->isAllowedImageFile($faviconAFile)) {
                    return $this->redirectWithImageError($request, 'favicon_a', 'A 站标签页图标格式无效。');
                }

                $path = $faviconService->storeUploadedFavicon($faviconAFile, 'favicon-a');
                $this->replaceStoredImage($faviconA, $path);
                if (!is_site_mode_b()) {
                    $faviconService->publishFromStoragePath($path);
                }
            }

            $faviconBFile = $request->file('favicon_b');
            if ($faviconBFile) {
                if (!$this->isAllowedImageFile($faviconBFile)) {
                    return $this->redirectWithImageError($request, 'favicon_b', 'B 站标签页图标格式无效。');
                }

                $path = $faviconService->storeUploadedFavicon($faviconBFile, 'favicon-b');
                $this->replaceStoredImage($faviconB, $path);
                if (is_site_mode_b()) {
                    $faviconService->publishFromStoragePath($path);
                }
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
            $activeSiteMode->update([
                'value' => strtoupper((string) $request->input('active_site_mode', 'A')) === 'B' ? 'B' : 'A',
            ]);
            $jpyPerCny->update([
                'value' => (string) round((float) $request->input('jpy_per_cny'), 6),
            ]);
            $afterSaleGroupNotice->update([
                'value' => trim((string) $request->input('after_sale_group_notice', '')) ?: '扫码加入海淘售后群',
            ]);

            $afterSaleGroupQrFile = $request->file('after_sale_group_qr');
            if ($afterSaleGroupQrFile) {
                if (!$this->isAllowedImageFile($afterSaleGroupQrFile)) {
                    return $this->redirectWithImageError($request, 'after_sale_group_qr', '售后群二维码仅支持 JPG、PNG、GIF、WebP 格式。');
                }

                $path = $this->storeSiteSettingImage($afterSaleGroupQrFile, 'after-sale-group');
                $this->replaceStoredImage($afterSaleGroupQr, $path);
            }

            Cache::forget('site.active_mode');
            ExchangeRateService::forgetCache();

            return redirect()
                ->route('admin.site_settings.logo.edit')
                ->with('logo_upload_success', '站点品牌信息已更新。');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('站点设置保存失败', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return redirect()
                ->route('admin.site_settings.logo.edit')
                ->withInput($request->except(['logo', 'favicon_a', 'favicon_b', 'after_sale_group_qr']))
                ->withErrors([
                    'logo' => '保存失败：'.$e->getMessage(),
                ]);
        }
    }

    protected function firstOrCreateSetting($key, $defaultValue)
    {
        return SiteSetting::query()->firstOrCreate(
            ['key' => $key],
            ['value' => $defaultValue]
        );
    }

    protected function redirectWithImageError(Request $request, $field, $message)
    {
        return redirect()
            ->route('admin.site_settings.logo.edit')
            ->withInput($request->except(['logo', 'favicon_a', 'favicon_b', 'after_sale_group_qr']))
            ->withErrors([$field => $message]);
    }

    protected function replaceStoredImage(SiteSetting $setting, $newPath)
    {
        if ($setting->value && $setting->value !== $newPath) {
            try {
                Storage::disk('public')->delete($setting->value);
            } catch (\Throwable $e) {
                Log::warning('删除旧图片失败', [
                    'path' => $setting->value,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $setting->update(['value' => $newPath]);
    }

    protected function isAllowedImageFile(UploadedFile $file)
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (!in_array($extension, $allowedExtensions, true)) {
            return false;
        }

        $realPath = $file->getRealPath();
        if (!$realPath || !is_readable($realPath)) {
            return false;
        }

        $imageInfo = @getimagesize($realPath);

        return is_array($imageInfo) && isset($imageInfo['mime']);
    }

    protected function storeOptimizedLogo(UploadedFile $file)
    {
        app(SiteFaviconService::class)->ensureBrandDirectoryExists();

        $realPath = $file->getRealPath();
        if (!$realPath || !is_readable($realPath)) {
            throw new \RuntimeException('无法读取上传的 Logo 文件。');
        }

        $imageInfo = @getimagesize($realPath);

        if (!$imageInfo || !isset($imageInfo['mime'])) {
            return $this->storeRawLogo($file);
        }

        $mime = $imageInfo['mime'];
        if (!function_exists('imagecreatetruecolor')) {
            return $this->storeRawLogo($file);
        }

        $src = $this->createImageResource($realPath, $mime);
        if (!$src) {
            return $this->storeRawLogo($file);
        }

        $srcWidth = imagesx($src);
        $srcHeight = imagesy($src);
        if ($srcWidth <= 0 || $srcHeight <= 0) {
            imagedestroy($src);

            return $this->storeRawLogo($file);
        }

        $maxWidth = 640;
        $maxHeight = 220;
        $ratio = min($maxWidth / $srcWidth, $maxHeight / $srcHeight, 1);
        $targetWidth = max(1, (int) floor($srcWidth * $ratio));
        $targetHeight = max(1, (int) floor($srcHeight * $ratio));

        if ($ratio >= 0.999) {
            imagedestroy($src);

            return $this->storeRawLogo($file);
        }

        $dst = imagecreatetruecolor($targetWidth, $targetHeight);
        if (!$dst) {
            imagedestroy($src);

            return $this->storeRawLogo($file);
        }

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

        if (!is_string($binary) || $binary === '') {
            throw new \RuntimeException('Logo 图片压缩失败，请换一张较小的图片。');
        }

        if (!Storage::disk('public')->put($outputPath, $binary)) {
            throw new \RuntimeException('无法写入 Logo 文件，请检查 storage/app/public 目录权限。');
        }

        return $outputPath;
    }

    protected function storeRawLogo(UploadedFile $file)
    {
        app(SiteFaviconService::class)->ensureBrandDirectoryExists();

        $path = $file->store('images/brand', 'public');
        if (!$path) {
            throw new \RuntimeException('无法保存 Logo 文件，请检查 storage/app/public 目录权限。');
        }

        return $path;
    }

    protected function storeSiteSettingImage(UploadedFile $file, $basenamePrefix)
    {
        app(SiteFaviconService::class)->ensureBrandDirectoryExists();

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $extension = 'png';
        }

        $filename = preg_replace('/[^a-z0-9\-]+/i', '-', (string) $basenamePrefix).'-'.time().'.'.$extension;
        $path = 'images/brand/'.$filename;

        if (!Storage::disk('public')->put($path, file_get_contents($file->getRealPath()))) {
            throw new \RuntimeException('无法保存图片，请检查 storage/app/public 目录权限。');
        }

        return $path;
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
