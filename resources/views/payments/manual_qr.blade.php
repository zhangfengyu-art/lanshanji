@extends('layouts.app')
@section('title', '扫码支付')

@section('content')
<div class="row">
  <div class="col-lg-8 col-lg-offset-2">
    <div class="panel panel-default manual-pay-page">
      <div class="panel-heading">
        <h4>{{ $paymentType === 'alipay' ? '支付宝扫码支付' : '微信扫码支付' }}</h4>
      </div>
      <div class="panel-body text-center">
        <p class="text-muted">订单号：{{ $order->no }}</p>
        <p class="text-muted">应付金额：{{ number_format($order->total_amount, 2, '.', '') }}日元</p>

        <div class="manual-pay-qr-wrap">
          <img class="manual-pay-qr" src="{{ asset($qrPath) }}" alt="{{ $paymentType === 'alipay' ? '支付宝收款二维码' : '微信收款二维码' }}">
        </div>

        <div class="manual-pay-actions">
          <button type="button" class="btn btn-primary" id="btn-paid-refresh">我已完成付款</button>
          <a href="{{ route('orders.show', ['order' => $order->id]) }}" class="btn btn-default">返回订单</a>
        </div>

        <p class="help-block" style="margin-top: 12px;">
          完成付款后点击“我已完成付款”刷新订单状态。如长时间未更新，请联系客服并提供订单号。
        </p>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scriptsAfterJs')
<script>
  $(function () {
    $('#btn-paid-refresh').on('click', function () {
      window.location.href = '{{ route('orders.show', ['order' => $order->id]) }}';
    });
  });
</script>
@endsection
