@php
  $returnHomeUrl = site_a_url();
  $returnOrderUrl = !empty($gatewayOrderId) ? site_a_url('orders/'.$gatewayOrderId) : null;
@endphp
<header class="payment-gateway-header">
  <div class="payment-gateway-header__inner">
    <a class="payment-gateway-header__brand" href="{{ $returnHomeUrl }}">
      <span class="payment-gateway-header__brand-text">{{ site_brand_zh() }}</span>
    </a>

    <p class="payment-gateway-header__notice">岚山集支付收银台 · 完成付款后请返回订单页</p>

    <nav class="payment-gateway-header__actions" aria-label="返回导航">
      <a class="payment-gateway-header__link" href="{{ $returnHomeUrl }}">{{ trans('frontend.common.back_to_home') }}</a>
      @if($returnOrderUrl)
      <a class="payment-gateway-header__link is-primary" href="{{ $returnOrderUrl }}">返回订单</a>
      @endif
    </nav>
  </div>
</header>
