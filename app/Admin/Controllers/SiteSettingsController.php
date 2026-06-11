<?php

namespace App\Admin\Controllers;

use App\Models\SiteSetting;
use App\Services\ExchangeRateService;
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
        $jpyPerCny = SiteSetting::query()->firstOrCreate(
            ['key' => ExchangeRateService::SETTING_KEY],
            ['value' => (string) config('site.default_jpy_per_cny', 22)]
        );

        return Admin::content(function (Content $content) use ($setting, $brandTextZh, $brandTextEn, $disableEmailVerificationForTesting, $activeSiteMode, $jpyPerCny) {
            $content->header('站点设置');
            $content->description('维护站点品牌信息与运行模式');
            $content->body(view('admin.site_settings.logo', [
                'setting' => $setting,
                'brandTextZh' => $brandTextZh,
                'brandTextEn' => $brandTextEn,
                'disableEmailVerificationForTesting' => $disableEmailVerificationForTesting,
                'activeSiteMode' => $activeSiteMode,
                'jpyPerCny' => $jpyPerCny,
            ]));
        });
    }

    public function updateLogo(Request $request)
    {
        try {
            $this->validate($request, [
                'logo' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp|max:8192',
                'brand_text_zh' => 'nullable|string|max:60',
                'brand_text_en' => 'nullable|string|max:120',
                'disable_email_verification_for_testing' => 'nullable|boolean',
                'active_site_mode' => 'required|in:A,B',
                'jpy_per_cny' => 'required|numeric|min:0.01',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        }

        try {
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
        $jpyPerCny = SiteSetting::query()->firstOrCreate(
            ['key' => ExchangeRateService::SETTING_KEY],
            ['value' => (string) config('site.default_jpy_per_cny', 22)]
        );

        $logoFile = $request->file('logo');
        if ($logoFile) {
            try {
                $path = $this->storeOptimizedLogo($logoFile);
            } catch (\Throwable $e) {
                Log::error('站点 Logo 上传失败', [
                    'message' => $e->getMessage(),
                    'file' => $logoFile->getClientOriginalName(),
                ]);

                return redirect()
                    ->route('admin.site_settings.logo.edit')
                    ->withInput($request->except('logo'))
                    ->withErrors([
                        'logo' => 'Logo 保存失败，请检查 storage 目录权限或换一张较小的图片后重试。',
                    ]);
            }

            if ($setting->value && $setting->value !== $path) {
                try {
                    Storage::disk('public')->delete($setting->value);
                } catch (\Throwable $e) {
                    Log::warning('删除旧 Logo 失败', [
                        'path' => $setting->value,
                        'message' => $e->getMessage(),
                    ]);
                }
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
        $activeSiteMode->update([
            'value' => strtoupper((string) $request->input('active_site_mode', 'A')) === 'B' ? 'B' : 'A',
        ]);
        $jpyPerCny->update([
            'value' => (string) round((float) $request->input('jpy_per_cny'), 6),
        ]);
        Cache::forget('site.active_mode');
        ExchangeRateService::forgetCache();

            return redirect()
                ->route('admin.site_settings.logo.edit')
                ->with('logo_upload_success', '站点品牌信息已更新。');
        } catch (\Throwable $e) {
            Log::error('站点设置保存失败', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return redirect()
                ->route('admin.site_settings.logo.edit')
                ->withInput($request->except('logo'))
                ->withErrors([
                    'logo' => '保存失败：'.$e->getMessage(),
                ]);
        }
    }

    /**
     * Resize and compress uploaded logo to avoid giant display and heavy payload.
     */
    protected function storeOptimizedLogo(UploadedFile $file)
    {
        $this->ensureBrandDirectoryExists();

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

        // Keep output crisp while avoiding oversized files.
        $maxWidth = 640;
        $maxHeight = 220;
        $ratio = min($maxWidth / $srcWidth, $maxHeight / $srcHeight, 1);
        $targetWidth = max(1, (int) floor($srcWidth * $ratio));
        $targetHeight = max(1, (int) floor($srcHeight * $ratio));

        // If the upload is already small enough, keep original bytes to avoid recompression blur.
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
        $this->ensureBrandDirectoryExists();

        $path = $file->store('images/brand', 'public');
        if (!$path) {
            throw new \RuntimeException('无法保存 Logo 文件，请检查 storage/app/public 目录权限。');
        }

        return $path;
    }

    protected function ensureBrandDirectoryExists()
    {
        $disk = Storage::disk('public');
        $root = storage_path('app/public');
        if (!is_dir($root) && !@mkdir($root, 0775, true) && !is_dir($root)) {
            throw new \RuntimeException('storage/app/public 目录不存在且无法创建，请检查 storage 权限。');
        }

        foreach (['images', 'images/brand'] as $dir) {
            if ($disk->exists($dir)) {
                continue;
            }

            if (!$disk->makeDirectory($dir)) {
                throw new \RuntimeException('无法创建 '.$dir.' 目录，请执行 chmod/chown storage。');
            }
        }
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
