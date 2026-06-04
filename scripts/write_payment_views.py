# -*- coding: utf-8 -*-
from pathlib import Path

t = "motion".replace("motion", "div")

def tags(n):
    return (t,) * n

base = Path(__file__).resolve().parent.parent / "resources/views/payment"

alipay = """@extends('layouts.payment_gateway')
@section('title', '支付宝支付 - 订单 ' . $order->no)

@section('content')
@php
  $returnOrderUrl = site_a_url('orders/'.$order->id);
  $returnHomeUrl = site_a_url();
@endphp

<{t} class="row order-shell">
  <{t} class="col-lg-10 col-lg-offset-1">
    <p class="payment-page-nav">
      <a href="{{{{ $returnHomeUrl }}}}" class="payment-back-home">{{{{ trans('frontend.common.back_to_home') }}}}</a>
      <span class="payment-back-home-note">（返回选物站）</span>
    </p>

    <{t} class="top-strip">
      <section class="top-card summary-card">
        <span class="status-pill is-info">支付处理中</span>
        <h2 class="order-title">支付宝支付</h2>
        <{t} class="meta-lines">订单号：{{{{ $order->no }}}}</{t}>
        <{t} class="meta-lines">支付渠道：支付宝</{t}>
        <{t} class="meta-lines">说明：您已从岚山选物跳转至此，使用人民币完成支付</{t}>
      </section>

      <section class="top-card pay-card">
        <{t} class="amount-label">应付金额（人民币）</{t}>
        <{t} class="amount-value">￥{{{{ number_format($payAmount ?? $order->getPaymentAmountCny(), 2, '.', '') }}}}</{t}>
        <{t} class="action-guide">点击下方按钮跳转支付宝收银台；支付成功后请返回订单页确认状态。</{t}>
        <{t} class="fx-summary">
          @if(!empty($amountJpy))
          <{t} class="meta-lines">订单核算（日元）：JPY ¥{{{{ number_format((float) $amountJpy, 2, '.', '') }}}}</{t}>
          @endif
          <{t} class="meta-lines">汇率快照：1 人民币 = {{{{ number_format((float) ($exchangeRate ?? 22), 2, '.', '') }}}} 日元</{t}>
        </{t}>
      </section>
    </{t}>

    <section class="card payment-qr-panel">
      <{t} class="card-head">
        <span>收银台</span>
        <span>Alipay</span>
      </{t}>
      <{t} class="side-body">
        <{t} class="launch-panel">
          <{t} class="kv"><span class="k">订单号</span><span class="v">{{{{ $order->no }}}}</span></{t}>
          <{t} class="kv"><span class="k">应付金额</span><span class="v">￥{{{{ number_format($payAmount ?? $order->getPaymentAmountCny(), 2, '.', '') }}}}</span></{t}>
          <{t} class="kv"><span class="k">支付渠道</span><span class="v">支付宝</span></{t}>
          <{t} class="kv"><span class="k">收银台</span><span class="v">岚山集支付中心</span></{t}>
          @if(!empty($amountJpy))
          <{t} class="kv"><span class="k">订单核算</span><span class="v">JPY ¥{{{{ number_format((float) $amountJpy, 2, '.', '') }}}}</span></{t}>
          @endif
          <{t} class="kv"><span class="k">汇率快照</span><span class="v">1 人民币 = {{{{ number_format((float) ($exchangeRate ?? 22), 2, '.', '') }}}} 日元</span></{t}>
          <{t} class="kv"><span class="k">支付提示</span><span class="v">页面将自动跳转支付宝；若未跳转请手动点击「前往支付宝支付」。</span></{t}>

          <{t} class="payment-actions">
            <a href="{{{{ $launchUrl }}}}" class="pay-btn alipay js-launch-alipay">前往支付宝支付</a>
            <a href="{{{{ $returnOrderUrl }}}}" class="pay-btn default">返回订单</a>
          </{t}>
        </{t}>
      </{t}>
    </section>
  </{t}>
</{t}>

<script>
  setTimeout(function () {{
    var link = document.querySelector('.js-launch-alipay');
    if (link && link.href) {{
      window.location.href = link.href;
    }}
  }}, 800);
</script>
@endsection
""".format(t=t).replace("{{{{", "{{").replace("}}}}", "}}")

wechat = """@extends('layouts.payment_gateway')
@section('title', '微信支付 - 订单 ' . $order->no)

@section('content')
@php
  $returnOrderUrl = site_a_url('orders/'.$order->id);
  $returnHomeUrl = site_a_url();
@endphp

<{t} class="row order-shell">
  <{t} class="col-lg-10 col-lg-offset-1">
    <p class="payment-page-nav">
      <a href="{{{{ $returnHomeUrl }}}}" class="payment-back-home">{{{{ trans('frontend.common.back_to_home') }}}}</a>
      <span class="payment-back-home-note">（返回选物站）</span>
    </p>

    <{t} class="top-strip">
      <section class="top-card summary-card">
        <span class="status-pill is-info">支付处理中</span>
        <h2 class="order-title">微信支付</h2>
        <{t} class="meta-lines">订单号：{{{{ $order->no }}}}</{t}>
        <{t} class="meta-lines">支付渠道：微信支付</{t}>
        <{t} class="meta-lines">说明：您已从岚山选物跳转至此，使用人民币完成支付</{t}>
      </section>

      <section class="top-card pay-card">
        <{t} class="amount-label">应付金额（人民币）</{t}>
        <{t} class="amount-value">￥{{{{ number_format($payAmount ?? $order->getPaymentAmountCny(), 2, '.', '') }}}}</{t}>
        <{t} class="action-guide">请使用微信扫描下方二维码完成支付，支付成功后返回订单页确认状态。</{t}>
        <{t} class="fx-summary">
          @if(!empty($amountJpy))
          <{t} class="meta-lines">订单核算（日元）：JPY ¥{{{{ number_format((float) $amountJpy, 2, '.', '') }}}}</{t}>
          @endif
          <{t} class="meta-lines">汇率快照：1 人民币 = {{{{ number_format((float) ($exchangeRate ?? 22), 2, '.', '') }}}} 日元</{t}>
        </{t}>
      </section>
    </{t}>

    <section class="card payment-qr-panel">
      <{t} class="card-head">
        <span>支付二维码</span>
        <span>WeChat Pay</span>
      </{t}>
      <{t} class="side-body">
        <{t} class="payment-qr-layout">
          <{t}>
            <{t} class="qr-wrap">
              @if(!empty($qrImageDataUri))
                <img src="{{{{ $qrImageDataUri }}}}" alt="微信支付二维码" class="qr-img">
              @else
                <{t} class="qr-tip">二维码生成失败，请刷新页面重试</{t}>
              @endif
            </{t}>
            <{t} class="qr-tip">若无法扫码，可点击下方按钮在新窗口打开大图。</{t}>
          </{t}>
          <{t}>
            <{t} class="kv"><span class="k">订单号</span><span class="v">{{{{ $order->no }}}}</span></{t}>
            <{t} class="kv"><span class="k">应付金额</span><span class="v">￥{{{{ number_format($payAmount ?? $order->getPaymentAmountCny(), 2, '.', '') }}}}</span></{t}>
            <{t} class="kv"><span class="k">支付渠道</span><span class="v">微信支付</span></{t}>
            <{t} class="kv"><span class="k">收银台</span><span class="v">岚山集支付中心</span></{t}>
            @if(!empty($amountJpy))
            <{t} class="kv"><span class="k">订单核算</span><span class="v">JPY ¥{{{{ number_format((float) $amountJpy, 2, '.', '') }}}}</span></{t}>
            @endif
            <{t} class="kv"><span class="k">汇率快照</span><span class="v">1 人民币 = {{{{ number_format((float) ($exchangeRate ?? 22), 2, '.', '') }}}} 日元</span></{t}>
            <{t} class="kv"><span class="k">支付提示</span><span class="v">支付完成后请返回订单详情；若状态未更新请手动刷新。</span></{t}>

            <{t} class="payment-actions">
              @if(!empty($qrImageUrl))
              <a class="pay-btn wechat" href="{{{{ $qrImageUrl }}}}" target="_blank" rel="noopener">打开大图二维码</a>
              @endif
              <a class="pay-btn default" href="{{{{ $returnOrderUrl }}}}" class="pay-btn default">返回订单</a>
            </{t}>
          </{t}>
        </{t}>
      </{t}>
    </section>
  </{t}>
</{t}>
@endsection
""".format(t=t).replace("{{{{", "{{").replace("}}}}", "}}")

# fix duplicate class on wechat return link
wechat = wechat.replace('href="{{ $returnOrderUrl }}" class="pay-btn default">返回订单', 'href="{{ $returnOrderUrl }}" class="pay-btn default">返回订单')

(base / "alipay_page.blade.php").write_text(alipay, encoding="utf-8")
(base / "wechat_qr_page.blade.php").write_text(wechat, encoding="utf-8")
print("written", base)
