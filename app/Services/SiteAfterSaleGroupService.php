<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;

class SiteAfterSaleGroupService
{
    const KEY_QR_IMAGE = 'after_sale_group_qr';

    const KEY_NOTICE = 'after_sale_group_notice';

    public function qrImagePath()
    {
        if (!Schema::hasTable('site_settings')) {
            return '';
        }

        return trim((string) SiteSetting::query()
            ->where('key', self::KEY_QR_IMAGE)
            ->value('value'));
    }

    public function qrImageUrl()
    {
        if (!Schema::hasTable('site_settings')) {
            return null;
        }

        $setting = SiteSetting::query()
            ->where('key', self::KEY_QR_IMAGE)
            ->first();

        $path = trim((string) optional($setting)->value);
        if ($path === '') {
            return null;
        }

        $url = site_setting_image_url($path);
        if (!$url) {
            return null;
        }

        $version = optional($setting->updated_at)->timestamp ?: time();

        return $url.(strpos($url, '?') !== false ? '&' : '?').'v='.$version;
    }

    public function shouldShowOnPaidOrder()
    {
        return $this->qrImagePath() !== '';
    }

    public function noticeText()
    {
        if (!Schema::hasTable('site_settings')) {
            return trans('frontend.order.after_sale_group_title');
        }

        $custom = trim((string) SiteSetting::query()
            ->where('key', self::KEY_NOTICE)
            ->value('value'));

        return $custom !== '' ? $custom : trans('frontend.order.after_sale_group_title');
    }
}
