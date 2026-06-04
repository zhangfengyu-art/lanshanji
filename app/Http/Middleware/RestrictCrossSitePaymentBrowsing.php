<?php

namespace App\Http\Middleware;

use App\Services\CrossSitePaymentService;
use Closure;

class RestrictCrossSitePaymentBrowsing
{
    /** @var CrossSitePaymentService */
    protected $crossSitePayment;

    public function __construct(CrossSitePaymentService $crossSitePayment)
    {
        $this->crossSitePayment = $crossSitePayment;
    }

    /**
     * 跨站支付会话期间，禁止在 B 站浏览非支付页面（改地址栏亦无效）。
     */
    public function handle($request, Closure $next)
    {
        if (!is_site_mode_b()) {
            return $next($request);
        }

        if (!$this->crossSitePayment->hasActiveEscrow()) {
            return $next($request);
        }

        if ($this->crossSitePayment->isAllowedRouteDuringEscrow($request)) {
            return $next($request);
        }

        return redirect()->away($this->crossSitePayment->siteAReturnUrl());
    }
}
