<?php

namespace App\Http\Controllers;

use App\Models\ProcurementOrder;
use App\Models\ProxyQualification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProcurementOrdersController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(is_site_mode_b(), 404);

            return $next($request);
        });
    }

    public function show(ProcurementOrder $procurementOrder)
    {
        return view('procurement.show', [
            'procurementOrder' => $procurementOrder,
        ]);
    }

    public function accept(Request $request, ProcurementOrder $procurementOrder)
    {
        if ((int) $procurementOrder->proxy_status !== ProcurementOrder::STATUS_PENDING) {
            return redirect()
                ->route('procurement.show', $procurementOrder)
                ->with('error', '该求购已有人接单或已结束。');
        }

        if ($procurementOrder->user_id && (int) $procurementOrder->user_id === (int) $request->user()->id) {
            return redirect()
                ->route('procurement.show', $procurementOrder)
                ->with('error', '不能接自己发布的求购。');
        }

        $qualification = ProxyQualification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->first();

        if (!$qualification || !$qualification->isApproved()) {
            return redirect()
                ->route('procurement.qualification.status')
                ->with('error', '请先完成代购资质认证后再接单。');
        }

        $procurementOrder->proxy_status = ProcurementOrder::STATUS_ACCEPTED;

        if (Schema::hasColumn('procurement_orders', 'accepted_by')) {
            $procurementOrder->accepted_by = $request->user()->id;
            $procurementOrder->accepted_at = now();
        } else {
            $extra = is_array($procurementOrder->extra) ? $procurementOrder->extra : [];
            $extra['accepted_by'] = (int) $request->user()->id;
            $extra['accepted_at'] = now()->toDateTimeString();
            $procurementOrder->extra = $extra;
        }

        $procurementOrder->save();

        return redirect()
            ->route('procurement.show', $procurementOrder)
            ->with('success', '接单成功！货款由求购方托管，您无需在此页面付款。');
    }
}
