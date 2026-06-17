@extends('layouts.app')
@section('title', '订单核算与支付')

@section('content')
@php
  $defaultSkuId = (int) $defaultSku->id;
  $serviceFeeRate = (float) config('site.service_fee_rate', 0.15);
  $serviceFeePercent = (int) round($serviceFeeRate * 100);
@endphp

<style>
  .pc-wrap { max-width: 1080px; margin: 18px auto 28px; }
  .pc-grid { display: grid; grid-template-columns: 1.2fr .8fr; gap: 14px; }
  .pc-card { background: #fff; border: 1px solid #e6e7eb; border-radius: 12px; box-shadow: 0 4px 14px rgba(20, 24, 31, 0.05); }
  .pc-head { padding: 12px 14px; border-bottom: 1px solid #eef0f3; font-size: 16px; font-weight: 700; color: #20242c; }
  .pc-body { padding: 14px; }
  .pc-product { display: grid; grid-template-columns: 130px 1fr; gap: 12px; }
  .pc-media { width: 100%; height: 130px; overflow: hidden; border-radius: 10px; background: #f3f4f6; }
  .pc-media img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .pc-title { margin: 0 0 6px; font-size: 22px; font-weight: 700; color: #1f2937; }
  .pc-price { margin: 0; font-size: 24px; font-weight: 700; color: #111827; }
  .pc-muted { color: #6b7280; font-size: 12px; margin-top: 4px; }
  .pc-row { margin-bottom: 12px; }
  .pc-label { display: block; margin-bottom: 6px; font-size: 12px; color: #6b7280; }
  .pc-input, .pc-select, .pc-textarea { width: 100%; border: 1px solid #d7dbe2; border-radius: 8px; padding: 8px 10px; }
  .pc-textarea { min-height: 74px; resize: vertical; }
  .pc-skus { display: flex; flex-wrap: wrap; gap: 8px; }
  .pc-sku { border: 1px solid #d7dbe2; border-radius: 999px; padding: 6px 12px; cursor: pointer; font-size: 12px; }
  .pc-sku.is-active { border-color: #111827; background: #111827; color: #fff; }
  .pc-qty { display: inline-flex; border: 1px solid #d7dbe2; border-radius: 8px; overflow: hidden; }
  .pc-qty button { width: 32px; border: 0; background: #f8f9fb; }
  .pc-qty input { width: 52px; border: 0; text-align: center; }
  .pc-summary-line { display: flex; justify-content: space-between; margin-bottom: 10px; color: #4b5563; }
  .pc-summary-total { font-size: 20px; font-weight: 700; color: #111827; border-top: 1px dashed #d4d7de; padding-top: 10px; }
  .pc-pay-btn { width: 100%; border: 0; border-radius: 10px; padding: 11px 12px; font-size: 15px; font-weight: 700; color: #fff; background: linear-gradient(180deg, #1f242e 0%, #11151d 100%); }
  .pc-note { margin-top: 8px; color: #6b7280; font-size: 12px; }
  @media (max-width: 900px) { .pc-grid { grid-template-columns: 1fr; } }
</style>

<div class="pc-wrap">
  <div class="pc-grid">
    <section class="pc-card">
      <div class="pc-head">订单核算</div>
      <div class="pc-body">
        <div class="pc-product">
          <div class="pc-media"><img src="{{ $product->image_url }}" alt="{{ $product->title }}"></div>
          <div>
            <h1 class="pc-title">{{ $product->title }}</h1>
            <p class="pc-price" id="pc-unit-price">JPY ¥{{ number_format((float) $defaultSku->price, 2, '.', '') }}</p>
            <p class="pc-muted">下单后将自动跳转到支付页，支持支付宝与微信支付。</p>
          </div>
        </div>

        <div class="pc-row" style="margin-top:14px;">
          <label class="pc-label">选择规格</label>
          <div class="pc-skus" id="pc-sku-list">
            @foreach($product->skus as $sku)
              <button type="button" class="pc-sku{{ (int)$sku->id === $defaultSkuId ? ' is-active' : '' }}" data-sku-id="{{ $sku->id }}" data-price="{{ $sku->price }}" data-stock="{{ (int)$sku->stock }}" data-title="{{ $sku->title }}">{{ $sku->title }}</button>
            @endforeach
          </div>
        </div>

        <div class="pc-row">
          <label class="pc-label">购买数量</label>
          <div class="pc-qty">
            <button type="button" id="pc-qty-minus">-</button>
            <input type="text" id="pc-qty" value="1">
            <button type="button" id="pc-qty-plus">+</button>
          </div>
          <span class="pc-muted" id="pc-stock-hint"></span>
        </div>

        <div class="pc-row">
          <label class="pc-label">国内转寄地址（Domestic Forwarding Address）</label>
          <select id="pc-address" class="pc-select">
            @foreach($addresses as $address)
              <option value="{{ $address->id }}">{{ $address->is_default ? '[默认] ' : '' }}{{ $address->full_address }} {{ $address->contact_name }} {{ $address->contact_phone }}</option>
            @endforeach
          </select>
          <div class="pc-muted">请确保填写准确，代购人在完成境外代买并入境后，将通过国内顺丰/邮政转寄至此地址。</div>
        </div>

        <div class="pc-row">
          <label class="pc-label">备注（选填）</label>
          <textarea id="pc-remark" class="pc-textarea" placeholder="可填写颜色、版本、时效偏好等"></textarea>
        </div>
      </div>
    </section>

    <aside class="pc-card">
      <div class="pc-head">支付核算</div>
      <div class="pc-body">
        <div class="pc-summary-line"><span>商品金额</span><strong id="pc-base">0.00</strong></div>
        <div class="pc-summary-line"><span>服务费({{ $serviceFeePercent }}%)</span><strong id="pc-service">0.00</strong></div>
        <div class="pc-summary-line"><span>打包费</span><strong id="pc-pack">300.00</strong></div>
        <div class="pc-summary-line"><span>EMS运费</span><strong id="pc-ship">1750.00</strong></div>
        <div class="pc-summary-line pc-summary-total"><span>应付总额</span><strong id="pc-total">0.00</strong></div>

        <button type="button" id="pc-pay-now" class="pc-pay-btn">立刻支付</button>
        <div class="pc-note">点击后创建订单并自动跳转到支付页面。</div>
      </div>
    </aside>
  </div>
</div>
@endsection

@section('scriptsAfterJs')
<script>
$(function () {
  var serviceRate = {{ json_encode($serviceFeeRate) }};
  var packagingFee = 300;
  var shippingFee = 1750;

  var selected = {
    skuId: {{ $defaultSkuId }},
    price: parseFloat('{{ (float) $defaultSku->price }}'),
    stock: parseInt('{{ (int) $defaultSku->stock }}', 10)
  };

  function toFixed2(v) { return (Math.round(v * 100) / 100).toFixed(2); }

  function ensureQty() {
    var qty = parseInt($('#pc-qty').val(), 10);
    if (isNaN(qty) || qty < 1) qty = 1;
    if (selected.stock > 0 && qty > selected.stock) qty = selected.stock;
    $('#pc-qty').val(qty);
    return qty;
  }

  function refreshSummary() {
    var qty = ensureQty();
    var base = selected.price * qty;
    var service = base * serviceRate;
    var total = base + service + packagingFee + shippingFee;

    $('#pc-unit-price').text('JPY ¥' + toFixed2(selected.price));
    $('#pc-base').text(toFixed2(base));
    $('#pc-service').text(toFixed2(service));
    $('#pc-pack').text(toFixed2(packagingFee));
    $('#pc-ship').text(toFixed2(shippingFee));
    $('#pc-total').text(toFixed2(total));
    $('#pc-stock-hint').text('可用库存：' + selected.stock + ' 件');
  }

  $('#pc-sku-list').on('click', '.pc-sku', function () {
    var $btn = $(this);
    $('.pc-sku').removeClass('is-active');
    $btn.addClass('is-active');

    selected.skuId = parseInt($btn.data('sku-id'), 10);
    selected.price = parseFloat($btn.data('price')) || 0;
    selected.stock = parseInt($btn.data('stock'), 10) || 0;
    refreshSummary();
  });

  $('#pc-qty-minus').on('click', function () {
    $('#pc-qty').val((parseInt($('#pc-qty').val(), 10) || 1) - 1);
    refreshSummary();
  });
  $('#pc-qty-plus').on('click', function () {
    $('#pc-qty').val((parseInt($('#pc-qty').val(), 10) || 1) + 1);
    refreshSummary();
  });
  $('#pc-qty').on('change blur', refreshSummary);

  $('#pc-pay-now').on('click', function () {
    var qty = ensureQty();
    if (selected.stock <= 0) {
      swal('该规格暂无库存', '', 'error');
      return;
    }

    var addressId = $('#pc-address').val();
    if (!addressId) {
      swal('请选择国内转寄地址', '', 'warning');
      return;
    }

    var $btn = $(this);
    if ($btn.prop('disabled')) return;
    $btn.prop('disabled', true).text('创建订单中...');

    axios.post('{{ route('orders.store') }}', {
      address_id: addressId,
      remark: $('#pc-remark').val() || '',
      items: [{ sku_id: selected.skuId, amount: qty }]
    }).then(function (resp) {
      var orderId = resp.data.id;
      if (!orderId) {
        swal('创建订单失败，请重试', '', 'error');
        $btn.prop('disabled', false).text('立刻支付');
        return;
      }
      window.location.href = '{{ url('orders') }}/' + orderId;
    }).catch(function (error) {
      $btn.prop('disabled', false).text('立刻支付');
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

  refreshSummary();
});
</script>
@endsection
