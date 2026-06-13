@extends('layouts.payment_gateway')
@section('title', '支付宝支付 - 订单 ' . $order->no)

@section('content')
@php
  $returnOrderUrl = site_a_url('orders/'.$order->id);
  $returnHomeUrl = site_a_url();
@endphp

<div class="row order-shell">
  <div class="col-lg-10 col-lg-offset-1">
    <p class="payment-page-nav">
      <a href="{{ $returnHomeUrl }}" class="payment-back-home">{{ trans('frontend.common.back_to_home') }}</a>
      <span class="payment-back-home-note">（返回选物站）</span>
    </p>

    <div class="top-strip">
      <section class="top-card summary-card">
        <span class="status-pill is-info">支付处理中</span>
        <h2 class="order-title">支付宝支付</h2>
        <div class="meta-lines">订单号：{{ $order->no }}</div>
        <div class="meta-lines">支付渠道：支付宝</div>
        <div class="meta-lines">说明：您已从岚山选物跳转至此完成付款，支付成功后请返回选物站订单页确认状态。</div>
      </section>

      <section class="top-card pay-card">
        <div class="amount-label">应付金额（人民币）</div>
        <div class="amount-value">￥{{ number_format($payAmount ?? $order->getPaymentAmountCny(), 2, '.', '') }}</div>
        <div class="action-guide">点击下方按钮跳转支付宝收银台；支付成功后请返回订单页确认状态。</div>
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
        <span>收银台</span>
        <span>Alipay</span>
      </div>
      <div class="side-body">
        <div class="launch-panel">
          <div class="kv"><span class="k">订单号</span><span class="v">{{ $order->no }}</span></div>
          <div class="kv"><span class="k">应付金额</span><span class="v">￥{{ number_format($payAmount ?? $order->getPaymentAmountCny(), 2, '.', '') }}</span></div>
          <div class="kv"><span class="k">支付渠道</span><span class="v">支付宝</span></div>
          <div class="kv"><span class="k">收银台</span><span class="v">岚山选物收银台</span></div>
          @if(!empty($amountJpy))
          <div class="kv"><span class="k">订单核算</span><span class="v">JPY ¥{{ number_format((float) $amountJpy, 2, '.', '') }}</span></div>
          @endif
          <div class="kv"><span class="k">汇率快照</span><span class="v">1 人民币 = {{ number_format((float) ($exchangeRate ?? 22), 2, '.', '') }} 日元</span></div>
          <div class="kv"><span class="k">支付提示</span><span class="v">页面将自动跳转支付宝；若未跳转请手动点击「前往支付宝支付」。</span></div>

          <div class="payment-actions">
            <a href="{{ $launchUrl }}" class="pay-btn alipay js-launch-alipay">前往支付宝支付</a>
            <a href="{{ $returnOrderUrl }}" class="pay-btn default">返回订单</a>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>

<script>
  setTimeout(function () {
    var link = document.querySelector('.js-launch-alipay');
    if (link && link.href) {
      window.location.href = link.href;
    }
  }, 800);
</script>
@endsection
