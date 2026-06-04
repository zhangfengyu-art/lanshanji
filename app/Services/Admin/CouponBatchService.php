<?php

namespace App\Services\Admin;

use App\Exceptions\InvalidRequestException;
use App\Models\CouponCode;
use Carbon\Carbon;

class CouponBatchService
{
    protected function couponsByIds(array $ids)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (count($ids) === 0) {
            throw new InvalidRequestException('请先勾选优惠券');
        }

        return CouponCode::query()->whereIn('id', $ids)->get();
    }

    public function batchSetEnabled(array $ids, $enabled)
    {
        $count = CouponCode::query()->whereIn('id', array_map('intval', $ids))->update([
            'enabled' => $enabled ? 1 : 0,
        ]);
        $label = $enabled ? '启用' : '停用';

        return ['updated' => $count, 'message' => '已'.$label.' '.$count.' 张优惠券'];
    }

    public function batchAddTotal(array $ids, $amount)
    {
        $amount = (int) $amount;
        if ($amount < 1) {
            throw new InvalidRequestException('增加数量至少为 1');
        }

        $updated = 0;
        foreach ($this->couponsByIds($ids) as $coupon) {
            $coupon->update(['total' => (int) $coupon->total + $amount]);
            $updated++;
        }

        return ['updated' => $updated, 'message' => '已为 '.$updated.' 张优惠券各增加发放量 '.$amount];
    }

    public function batchExtendExpiry(array $ids, $days)
    {
        $days = (int) $days;
        if ($days < 1) {
            throw new InvalidRequestException('延长天数至少为 1');
        }

        $updated = 0;
        foreach ($this->couponsByIds($ids) as $coupon) {
            $base = $coupon->not_after && $coupon->not_after->gt(Carbon::now())
                ? $coupon->not_after
                : Carbon::now();
            $coupon->update(['not_after' => $base->copy()->addDays($days)]);
            $updated++;
        }

        return ['updated' => $updated, 'message' => '已延长 '.$updated.' 张优惠券失效时间 '.$days.' 天'];
    }
}
