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
        $path = $this->qrImagePath();

        return $path !== '' ? site_setting_image_url($path) : null;
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
