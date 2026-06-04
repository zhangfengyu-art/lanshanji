@extends('layouts.payment_gateway')
@section('title', '微信支付 - 订单 ' . $order->no)

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
        <h2 class="order-title">微信支付</h2>
        <div class="meta-lines">订单号：{{ $order->no }}</div>
        <div class="meta-lines">支付渠道：微信支付</div>
        <div class="meta-lines">说明：您已从岚山选物跳转至此，使用人民币完成支付</div>
      </section>

      <section class="top-card pay-card">
        <div class="amount-label">应付金额（人民币）</div>
        <div class="amount-value">￥{{ number_format($payAmount ?? $order->getPaymentAmountCny(), 2, '.', '') }}</div>
        <div class="action-guide">请使用微信扫描下方二维码完成支付，支付成功后返回订单页确认状态。</div>
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
        <span>支付二维码</span>
        <span>WeChat Pay</span>
      </div>
      <div class="side-body">
        <div class="payment-qr-layout">
          <div>
            <div class="qr-wrap">
              @if(!empty($qrImageDataUri))
                <img src="{{ $qrImageDataUri }}" alt="微信支付二维码" class="qr-img">
              @else
                <div class="qr-tip">二维码生成失败，请刷新页面重试</div>
              @endif
            </div>
            <div class="qr-tip">若无法扫码，可点击下方按钮在新窗口打开大图。</div>
          </div>
          <div>
            <div class="kv"><span class="k">订单号</span><span class="v">{{ $order->no }}</span></div>
            <div class="kv"><span class="k">应付金额</span><span class="v">￥{{ number_format($payAmount ?? $order->getPaymentAmountCny(), 2, '.', '') }}</span></div>
            <div class="kv"><span class="k">支付渠道</span><span class="v">微信支付</span></div>
            <div class="kv"><span class="k">收银台</span><span class="v">岚山集支付中心</span></div>
            @if(!empty($amountJpy))
            <div class="kv"><span class="k">订单核算</span><span class="v">JPY ¥{{ number_format((float) $amountJpy, 2, '.', '') }}</span></div>
            @endif
            <div class="kv"><span class="k">汇率快照</span><span class="v">1 人民币 = {{ number_format((float) ($exchangeRate ?? 22), 2, '.', '') }} 日元</span></div>
            <div class="kv"><span class="k">支付提示</span><span class="v">支付完成后请返回订单详情；若状态未更新请手动刷新。</span></div>

            <div class="payment-actions">
              @if(!empty($qrImageUrl))
              <a class="pay-btn wechat" href="{{ $qrImageUrl }}" target="_blank" rel="noopener">打开大图二维码</a>
              @endif
              <a class="pay-btn default" href="{{ $returnOrderUrl }}">返回订单</a>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>
@endsection

