@extends('layouts.payment_gateway')
@section('title', '微信支付 - 订单 ' . $order->no)

@section('content')
@php
  $returnOrderUrl = site_a_url('orders/'.$order->id);
  $returnHomeUrl = site_a_url();
  $payCny = number_format($payAmount ?? $order->getPaymentAmountCny(), 2, '.', '');
@endphp

<div class="row order-shell payment-gateway-shell">
  <div class="col-lg-10 col-lg-offset-1">
    <div class="top-strip">
      <section class="top-card summary-card">
        <span class="status-pill is-info">支付处理中</span>
        <h2 class="order-title">微信支付</h2>
        <div class="meta-lines">订单号：{{ $order->no }}</div>
        <div class="meta-lines">支付渠道：微信支付（人民币）</div>
        <div class="meta-lines">说明：您已从岚山选物跳转至此完成付款，支付成功后请返回选物站订单页确认状态。</div>
      </section>

      <section class="top-card pay-card">
        <div class="amount-label">应付金额（人民币）</div>
        <div class="amount-value">￥{{ $payCny }}</div>
        <div class="action-guide">请使用微信扫描下方二维码完成支付。</div>
        <div class="fx-summary">
          @if(!empty($amountJpy))
          <div class="meta-lines">订单核算（日元）：JPY ¥{{ number_format((float) $amountJpy, 2, '.', '') }}</div>
          @endif
          <div class="meta-lines">汇率快照：1 人民币 = {{ number_format((float) ($exchangeRate ?? 22), 2, '.', '') }} 日元</div>
        </div>
      </section>
    </div>

    <section class="card payment-qr-panel">
      <div class="card-head">
        <span>微信扫码支付</span>
        <span>WeChat Pay</span>
      </div>
      <div class="side-body payment-qr-body">
        <div class="payment-qr-layout">
          <div class="payment-qr-visual">
            <div class="qr-wrap">
              @if(!empty($qrImageDataUri))
                <img src="{{ $qrImageDataUri }}" alt="微信支付二维码" class="qr-img">
              @else
                <div class="qr-tip">二维码生成失败，请刷新页面重试</div>
              @endif
            </div>
            <p class="qr-tip">打开微信「扫一扫」，对准二维码完成支付。</p>
            @if(!empty($qrImageUrl))
            <a class="pay-btn wechat qr-open-link" href="{{ $qrImageUrl }}" target="_blank" rel="noopener">打开大图二维码</a>
            @endif
          </div>
          <div class="payment-qr-meta">
            <div class="kv"><span class="k">订单号</span><span class="v">{{ $order->no }}</span></div>
            <div class="kv"><span class="k">应付金额</span><span class="v">￥{{ $payCny }}</span></div>
            @if(!empty($amountJpy))
            <div class="kv"><span class="k">订单核算</span><span class="v">JPY ¥{{ number_format((float) $amountJpy, 2, '.', '') }}</span></div>
            @endif
            <div class="kv"><span class="k">支付提示</span><span class="v">若状态未更新，请返回订单页手动刷新；勿重复支付。</span></div>
            <div class="payment-actions">
              <a class="pay-btn default" href="{{ $returnOrderUrl }}">返回订单</a>
              <a class="pay-btn default is-outline" href="{{ $returnHomeUrl }}">返回选物站首页</a>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>
@endsection
