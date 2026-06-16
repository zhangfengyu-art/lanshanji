<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ExchangeRateService
{
    const SETTING_KEY = 'jpy_per_cny';

    /**
     * 1 人民币可兑换的日元数量。
     */
    public function getJpyPerCny()
    {
        $fallback = (float) config('site.default_jpy_per_cny', 22);
        if ($fallback <= 0) {
            $fallback = 22;
        }

        try {
            if (!Schema::hasTable('site_settings')) {
                return $fallback;
            }

            return (float) Cache::remember('site.jpy_per_cny', 60, function () use ($fallback) {
                $value = SiteSetting::query()
                    ->where('key', self::SETTING_KEY)
                    ->value('value');

                $rate = (float) $value;
                return $rate > 0 ? $rate : $fallback;
            });
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    public function jpyToCny($jpyAmount)
    {
        $jpy = round((float) $jpyAmount, 2);
        $rate = $this->getJpyPerCny();

        return round($jpy / $rate, 2);
    }

    public function snapshotQuoteOnOrder(Order $order)
    {
        $rate = $this->getJpyPerCny();
        $jpy = round((float) $order->total_amount, 2);
        $cny = $this->jpyToCny($jpy);

        $extra = $order->extra;
        if (!is_array($extra)) {
            if (is_string($extra) && $extra !== '') {
                $decoded = json_decode($extra, true);
                $extra = is_array($decoded) ? $decoded : [];
            } else {
                $extra = [];
            }
        }
        $extra['currency'] = 'JPY';
        $extra['amount_jpy'] = $jpy;
        $extra['payment_amount_cny'] = $cny;
        $extra['exchange_rate_jpy_per_cny'] = $rate;
        $extra['payment_quote_at'] = now()->toDateTimeString();

        $order->update(['extra' => $extra]);

        return [
            'amount_jpy' => $jpy,
            'payment_amount_cny' => $cny,
            'exchange_rate' => $rate,
        ];
    }

    public static function forgetCache()
    {
        Cache::forget('site.jpy_per_cny');
    }
}
