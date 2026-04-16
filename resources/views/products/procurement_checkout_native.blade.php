@extends(is_site_mode_b() ? 'b_mode.layouts.app' : 'layouts.app')
@section('title', '求购金额核算')

@section('content')
@php
  $serviceRate = (float) ($serviceRate ?? 0.13);
  $packagingFee = (float) ($packagingFee ?? 300);
  $shippingFee = (float) ($shippingFee ?? 1750);
  $baseAmount = (float) $budgetAmount;
  $serviceAmount = $baseAmount * $serviceRate;
  $totalAmount = $baseAmount + $serviceAmount + $packagingFee + $shippingFee;
@endphp

<style>
  .nc-wrap {
    max-width: 1120px;
    margin: 18px auto 28px;
    padding: 0 6px;
  }
  .nc-grid { display: grid; grid-template-columns: 1.15fr .85fr; gap: 14px; }
  .nc-card {
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 16px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
    overflow: hidden;
  }
  .nc-head {
    padding: 13px 16px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    font-size: 16px;
    font-weight: 700;
    color: #1f2937;
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
  }
  .nc-body { padding: 16px; }
  .nc-title { margin: 0 0 6px; font-size: 25px; font-weight: 800; color: #0f172a; letter-spacing: 0.01em; }
  .nc-budget { margin: 0; font-size: 30px; font-weight: 800; color: #0f172a; line-height: 1.15; }
  .nc-note { margin-top: 7px; color: #64748b; font-size: 12px; }
  .nc-row { margin-bottom: 14px; }
  .nc-label { display: block; margin-bottom: 6px; font-size: 12px; color: #64748b; }
  .nc-value { color: #111827; font-size: 14px; line-height: 1.8; }
  .nc-summary-line { display: flex; justify-content: space-between; margin-bottom: 10px; color: #475569; font-size: 14px; }
  .nc-summary-total {
    margin-top: 2px;
    font-size: 21px;
    font-weight: 800;
    color: #0f172a;
    border-top: 1px dashed #d4d7de;
    padding-top: 11px;
  }
  .nc-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    border: 0;
    border-radius: 12px;
    padding: 11px 12px;
    font-size: 15px;
    font-weight: 700;
    color: #fff;
    background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
    text-decoration: none;
    cursor: pointer;
    transition: transform .16s ease, box-shadow .16s ease;
  }
  .nc-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 16px rgba(15, 23, 42, 0.2); }
  .nc-btn + .nc-btn { margin-top: 10px; }
  .nc-btn-light { background: #fff; color: #111827; border: 1px solid #d7dbe2; }
  .nc-select {
    width: 100%;
    border: 1px solid #d7dbe2;
    border-radius: 10px;
    padding: 9px 10px;
    background: #fff;
  }
  .nc-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
  .nc-meta-item {
    background: #f8fafc;
    border: 1px solid #eef2f7;
    border-radius: 12px;
    padding: 10px 12px;
  }
  .nc-meta-label { font-size: 11px; color: #64748b; margin-bottom: 4px; }
  .nc-meta-value { font-size: 14px; font-weight: 700; color: #1f2937; }
  @media (max-width: 900px) {
    .nc-grid { grid-template-columns: 1fr; }
    .nc-meta { grid-template-columns: 1fr; }
    .nc-wrap { margin: 10px auto 16px; }
  }
</style>

<div class="nc-wrap">
  <div class="nc-grid">
    <section class="nc-card">
      <div class="nc-head">求购金额核算</div>
      <div class="nc-body">
        <h1 class="nc-title">{{ $itemName }}</h1>
        <p class="nc-budget">预算总计：JPY ¥{{ number_format($baseAmount, 0) }}</p>
        <p class="nc-note">原生求购单仅按你填写的预算核算，不拆分 SKU，也不调用后台商品规格。</p>

        <div class="nc-row">
          <label class="nc-label">委托说明</label>
          <div class="nc-value">{{ $narrative !== '' ? $narrative : '当前求购委托已提交，正在按预算金额进行核算。' }}</div>
        </div>

        <div class="nc-meta">
          <div class="nc-meta-item">
            <div class="nc-meta-label">委托单号</div>
            <div class="nc-meta-value">{{ $procurementOrder->no }}</div>
          </div>
          <div class="nc-meta-item">
            <div class="nc-meta-label">需求状态</div>
            <div class="nc-meta-value">{{ \App\Models\ProcurementOrder::$statusMap[(int) $procurementOrder->proxy_status] ?? '等待接单' }}</div>
          </div>
        </div>

        <div class="nc-row">
          <label class="nc-label">国内转寄地址（Domestic Forwarding Address）</label>
          <select id="nc-address" class="nc-select">
            @foreach($addresses as $address)
              <option value="{{ $address->id }}">{{ $address->is_default ? '[默认] ' : '' }}{{ $address->full_address }} {{ $address->contact_name }} {{ $address->contact_phone }}</option>
            @endforeach
          </select>
          <div style="margin-top: 4px; color: #6b7280; font-size: 12px;">请确保填写准确，代购人在完成境外代买并入境后，将通过国内顺丰/邮政转寄至此地址。</div>
        </div>
      </div>
    </section>

    <aside class="nc-card">
      <div class="nc-head">核算明细</div>
      <div class="nc-body">
        <div class="nc-summary-line"><span>商品金额</span><strong>{{ number_format($baseAmount, 2, '.', '') }}</strong></div>
        <div class="nc-summary-line"><span>服务费(13%)</span><strong>{{ number_format($serviceAmount, 2, '.', '') }}</strong></div>
        <div class="nc-summary-line"><span>打包费</span><strong>{{ number_format($packagingFee, 2, '.', '') }}</strong></div>
        <div class="nc-summary-line nc-summary-total"><span>应付总额</span><strong>{{ number_format($totalAmount, 2, '.', '') }}</strong></div>

        <button type="button" id="nc-pay-now" class="nc-btn">提交订单并支付</button>
        <a class="nc-btn nc-btn-light" href="{{ route('procurement.detail', ['item_name' => $itemName, 'item_image' => data_get($procurementOrder, 'item_image', ''), 'budget_amount' => $baseAmount, 'narrative' => $narrative, 'native_request' => 1]) }}">查看求购详情</a>
        <a class="nc-btn nc-btn-light" href="{{ route('products.index') }}">返回求购大厅</a>
      </div>
    </aside>
  </div>
</div>
@endsection

@section('scriptsAfterJs')
<script>
$(function () {
  $('#nc-pay-now').on('click', function () {
    var addressId = $('#nc-address').val();
    if (!addressId) {
      swal('请选择国内转寄地址', '', 'warning');
      return;
    }

    var $btn = $(this);
    if ($btn.prop('disabled')) return;
    $btn.prop('disabled', true).text('正在跳转支付...');

    axios.post('{{ route('orders.store') }}', {
      address_id: addressId,
      remark: '',
      items: [{ sku_id: 0, amount: 1, is_native_procurement: true, procurement_order_id: {{ (int) $procurementOrder->id }} }]
    }).then(function (resp) {
      var orderId = resp.data.id;
      if (!orderId) {
        swal('创建订单失败，请稍后重试', '', 'error');
        $btn.prop('disabled', false).text('提交订单并支付');
        return;
      }
      window.location.href = '{{ url('orders') }}/' + orderId;
    }).catch(function (error) {
      $btn.prop('disabled', false).text('提交订单并支付');
      if (error.response && error.response.status === 422 && error.response.data && error.response.data.errors) {
        var msg = '';
        _.forEach(error.response.data.errors, function (arr) { _.forEach(arr, function (m) { msg += m + '\n'; }); });
        swal(msg || '提交失败', '', 'error');
        return;
      }
      if (error.response && error.response.data && error.response.data.msg) {
        swal(error.response.data.msg, '', 'error');
        return;
      }
      swal('系统错误，请稍后重试', '', 'error');
    });
  });
});
</script>
@endsection