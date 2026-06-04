<?php

namespace App\Admin\Controllers;

use App\Admin\Concerns\RespondsWithAdminBatchJson;
use App\Http\Controllers\Controller;
use App\Services\Admin\CouponBatchService;
use Illuminate\Http\Request;

class CouponBatchController extends Controller
{
    use RespondsWithAdminBatchJson;

    public function setEnabled(Request $request, CouponBatchService $batch)
    {
        return $this->batchTry(function () use ($request, $batch) {
            return $batch->batchSetEnabled(
                $this->batchIds($request, '请先勾选优惠券'),
                (bool) $request->input('enabled', 1)
            );
        });
    }

    public function addTotal(Request $request, CouponBatchService $batch)
    {
        return $this->batchTry(function () use ($request, $batch) {
            return $batch->batchAddTotal($this->batchIds($request, '请先勾选优惠券'), $request->input('amount'));
        });
    }

    public function extendExpiry(Request $request, CouponBatchService $batch)
    {
        return $this->batchTry(function () use ($request, $batch) {
            return $batch->batchExtendExpiry($this->batchIds($request, '请先勾选优惠券'), $request->input('days'));
        });
    }
}
