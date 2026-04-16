@extends('b_mode.layouts.app')
@section('title', '订单核算与支付')

@section('content')
@php
  $defaultSkuId = (int) $defaultSku->id;
@endphp

@push('styles')
<style>
  body.site-mode-b .pc-wrap { max-width: 1240px; margin: 14px auto 26px; padding: 0 10px; }
  body.site-mode-b .pc-hero {
    border-radius: 16px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: linear-gradient(135deg, #1c4fa3 0%, #245fc7 48%, #3475de 100%);
    color: #eaf3ff;
    padding: 16px 18px;
    margin-bottom: 12px;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
  }
  body.site-mode-b .pc-hero__title { margin: 0; font-size: 24px; font-weight: 800; }
  body.site-mode-b .pc-hero__sub { margin: 6px 0 0; font-size: 13px; color: rgba(234, 243, 255, 0.94); }
  body.site-mode-b .pc-grid { display: grid; grid-template-columns: 1.2fr .8fr; gap: 12px; }
  body.site-mode-b .pc-card { background: #fff; border: 1px solid rgba(15, 23, 42, 0.08); border-radius: 16px; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06); overflow: hidden; }
  body.site-mode-b .pc-head { padding: 13px 16px; border-bottom: 1px solid rgba(15, 23, 42, 0.08); font-size: 15px; font-weight: 800; color: #0f172a; background: linear-gradient(135deg, #fff 0%, #f8fbff 100%); }
  body.site-mode-b .pc-body { padding: 16px; }
  body.site-mode-b .pc-product { display: grid; grid-template-columns: 148px 1fr; gap: 12px; }
  body.site-mode-b .pc-media { width: 100%; height: 148px; overflow: hidden; border-radius: 12px; background: #eef3fb; box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08); }
  body.site-mode-b .pc-media img { width: 100%; height: 100%; object-fit: cover; display: block; }
  body.site-mode-b .pc-title { margin: 0 0 6px; font-size: 24px; font-weight: 800; color: #0f172a; }
  body.site-mode-b .pc-price { margin: 0; font-size: 28px; font-weight: 800; color: #1d4ed8; line-height: 1.15; }
  body.site-mode-b .pc-muted { color: #64748b; font-size: 12px; margin-top: 5px; line-height: 1.6; }
  body.site-mode-b .pc-row { margin-bottom: 13px; }
  body.site-mode-b .pc-label { display: block; margin-bottom: 6px; font-size: 12px; color: #64748b; font-weight: 700; }
  body.site-mode-b .pc-select, body.site-mode-b .pc-textarea { width: 100%; border: 1px solid rgba(15, 23, 42, 0.14); border-radius: 10px; padding: 9px 10px; }
  body.site-mode-b .pc-select:focus, body.site-mode-b .pc-textarea:focus { outline: 0; border-color: rgba(44, 123, 229, 0.48); box-shadow: 0 0 0 3px rgba(44, 123, 229, 0.12); }
  body.site-mode-b .pc-textarea { min-height: 78px; resize: vertical; }
  body.site-mode-b .pc-skus { display: flex; flex-wrap: wrap; gap: 8px; }
  body.site-mode-b .pc-sku { border: 1px solid rgba(15, 23, 42, 0.14); border-radius: 999px; padding: 7px 12px; cursor: pointer; font-size: 12px; font-weight: 700; background: #fff; }
  body.site-mode-b .pc-sku.is-active { border-color: #1d4ed8; background: rgba(44, 123, 229, 0.12); color: #1d4ed8; }
  body.site-mode-b .pc-qty { display: inline-flex; border: 1px solid rgba(15, 23, 42, 0.14); border-radius: 10px; overflow: hidden; }
  body.site-mode-b .pc-qty button { width: 34px; border: 0; background: #f8fafc; color: #334155; }
  body.site-mode-b .pc-qty input { width: 56px; border: 0; text-align: center; }
  body.site-mode-b .pc-summary-line { display: flex; justify-content: space-between; margin-bottom: 10px; color: #475569; font-size: 14px; }
  body.site-mode-b .pc-summary-total { font-size: 22px; font-weight: 800; color: #0f172a; border-top: 1px dashed rgba(15, 23, 42, 0.16); padding-top: 11px; }
  body.site-mode-b .pc-pay-btn { width: 100%; border: 0; border-radius: 12px; padding: 11px 12px; font-size: 15px; font-weight: 800; color: #fff; background: linear-gradient(180deg, #2c7be5 0%, #1d4ed8 100%); box-shadow: 0 8px 18px rgba(44, 123, 229, 0.24); }
  body.site-mode-b .pc-note { margin-top: 8px; color: #64748b; font-size: 12px; }
  @media (max-width: 920px) {
    body.site-mode-b .pc-grid { grid-template-columns: 1fr; }
    body.site-mode-b .pc-product { grid-template-columns: 1fr; }
    body.site-mode-b .pc-media { height: 190px; }
    body.site-mode-b .pc-title { font-size: 22px; }
  }
</style>
@endpush

<div class="pc-wrap">
  <section class="pc-hero">
    <h1 class="pc-hero__title">订单核算与支付确认</h1>
    <p class="pc-hero__sub">确认规格、数量与转寄地址后，系统将创建订单并进入支付流程。</p>
  </section>

  <div class="pc-grid">
    <section class="pc-card">
      <div class="pc-head">订单核算</div>
      <div class="pc-body">
        <div class="pc-product">
          <div class="pc-media"><img src="{{ $product->image_url }}" alt="{{ $product->title }}"></div>
          <div>
            <h1 class="pc-title">{{ $product->title }}</h1>
            <p class="pc-price" id="pc-unit-price">JPY ¥{{ number_format((float) $defaultSku->price, 2, '.', '') }}</p>
            <p class="pc-muted">提交订单后将进入支付页面，支持支付宝与微信支付。</p>
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
        <div class="pc-summary-line"><span>服务费(13%)</span><strong id="pc-service">0.00</strong></div>
        <div class="pc-summary-line"><span>打包费</span><strong id="pc-pack">300.00</strong></div>
        <div class="pc-summary-line"><span>EMS运费</span><strong id="pc-ship">1750.00</strong></div>
        <div class="pc-summary-line pc-summary-total"><span>应付总额</span><strong id="pc-total">0.00</strong></div>

        <button type="button" id="pc-pay-now" class="pc-pay-btn">提交订单并支付</button>
        <div class="pc-note">点击后将创建订单，并进入支付页面。</div>
      </div>
    </aside>
  </div>
</div>
@endsection

@section('scriptsAfterJs')
<script>
$(function () {
  var serviceRate = 0.13;
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
    $btn.prop('disabled', true).text('正在跳转支付...');

    axios.post('{{ route('orders.store') }}', {
      address_id: addressId,
      remark: $('#pc-remark').val() || '',
      items: [{ sku_id: selected.skuId, amount: qty }]
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

  refreshSummary();
});
</script>
@endsection
