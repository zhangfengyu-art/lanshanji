@extends('layouts.app')
@section('title', trans('frontend.cart.title'))

@section('content')
<style>
  body.site-mode-b .b-cart-wrap {
    max-width: 1080px;
    margin: 8px auto 20px;
    padding: 0 6px;
  }

  body.site-mode-b .b-cart-wrap .panel {
    border-radius: 16px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
  }

  body.site-mode-b .b-cart-wrap .panel-heading {
    padding: 14px 16px;
    font-size: 15px;
    font-weight: 700;
  }

  body.site-mode-b .b-cart-wrap .panel-body {
    padding: 14px 16px 16px;
  }

  body.site-mode-b .b-cart-wrap .table > thead > tr > th {
    background: #f8fafc;
    color: #64748b;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
  }

  body.site-mode-b .b-cart-wrap .table > tbody > tr > td {
    border-top: 1px solid rgba(15, 23, 42, 0.08);
    vertical-align: middle;
  }

  body.site-mode-b .b-cart-wrap .product_info .preview {
    border-radius: 10px;
    overflow: hidden;
    background: #eef3fb;
  }

  body.site-mode-b .b-cart-wrap .product_info .preview img {
    width: 58px;
    height: 58px;
    object-fit: cover;
  }

  body.site-mode-b .b-cart-wrap .amount-group .btn,
  body.site-mode-b .b-cart-wrap .amount-group .form-control {
    border-radius: 8px;
  }

  body.site-mode-b .b-cart-wrap .cart-checkout-summary {
    border-radius: 12px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fafcff;
    padding: 12px;
  }

  body.site-mode-b .b-cart-wrap .summary-line {
    display: flex;
    justify-content: space-between;
    margin-bottom: 7px;
    color: #475569;
  }

  body.site-mode-b .b-cart-wrap .summary-line-payable {
    margin-top: 6px;
    padding-top: 8px;
    border-top: 1px dashed rgba(15, 23, 42, 0.18);
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
  }

  body.site-mode-b .b-cart-wrap .btn-create-order {
    border-radius: 12px;
    min-height: 42px;
    font-weight: 700;
    min-width: 140px;
  }

  .logistics-dashboard {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #f9fafb;
    padding: 10px 12px;
    margin-bottom: 10px;
  }

  .logistics-dashboard-title {
    font-size: 13px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 8px;
  }

  .logistics-metric {
    margin-bottom: 10px;
  }

  .logistics-metric:last-child {
    margin-bottom: 0;
  }

  .logistics-metric-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    color: #374151;
    margin-bottom: 4px;
  }

  .logistics-bar-track {
    height: 8px;
    border-radius: 999px;
    background: #e5e7eb;
    overflow: hidden;
  }

  .logistics-bar-fill {
    height: 100%;
    width: 0;
    transition: width .25s ease;
  }

  .logistics-ok {
    background: #16a34a;
  }

  .logistics-warn {
    background: #eab308;
  }

  .logistics-danger {
    background: #dc2626;
  }

  .logistics-limit-alert {
    margin-top: 8px;
    padding: 8px 10px;
    border-radius: 8px;
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #991b1b;
    font-size: 12px;
    font-weight: 600;
    display: none;
  }

  body.site-mode-b .b-cart-empty {
    padding: 18px 14px;
  }

  body.site-mode-b .b-cart-mobile-action {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 62px;
    z-index: 1033;
    padding: 8px 10px calc(8px + env(safe-area-inset-bottom));
    display: none;
    background: rgba(244, 247, 251, 0.94);
    border-top: 1px solid rgba(15, 23, 42, 0.08);
    backdrop-filter: blur(8px);
  }

  body.site-mode-b .b-cart-mobile-action .btn {
    width: 100%;
    border-radius: 12px;
    min-height: 42px;
    font-weight: 700;
  }

  @media (max-width: 768px) {
    body.site-mode-b .b-cart-wrap {
      margin-bottom: 14px;
      padding-bottom: 108px;
    }

    body.site-mode-b .b-cart-wrap .panel-body {
      padding: 10px;
    }

    body.site-mode-b .b-cart-wrap .table {
      margin-bottom: 10px;
    }

    body.site-mode-b .b-cart-wrap .table > thead {
      display: none;
    }

    body.site-mode-b .b-cart-wrap .table > tbody > tr {
      display: block;
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 12px;
      margin-bottom: 10px;
      overflow: hidden;
      background: #fff;
    }

    body.site-mode-b .b-cart-wrap .table > tbody > tr > td {
      display: block;
      border-top: 1px dashed rgba(15, 23, 42, 0.08);
      padding: 9px 10px;
    }

    body.site-mode-b .b-cart-wrap .table > tbody > tr > td:first-child {
      border-top: 0;
    }

    body.site-mode-b .b-cart-mobile-action {
      display: block;
    }
  }
</style>
<div class="b-cart-wrap">
@php
  $hasAddresses = $addresses->count() > 0;
  $createAddressUrl = route('user_addresses.create', ['redirect' => route('cart.index')]);
@endphp
<div class="row">
<div class="col-lg-10 col-lg-offset-1">
<div class="panel panel-default">
  <div class="panel-heading">{{ trans('frontend.cart.my_cart') }}</div>
  <div class="panel-body">
    <div class="alert alert-info buy-now-mode-hint" style="display:none; margin-bottom: 14px;">
      {{ trans('frontend.cart.buy_now_mode_hint') }}
    </div>
    @if($cartItems->count())
    <table class="table table-striped">
      <thead>
      <tr>
        <th><input type="checkbox" id="select-all"></th>
        <th>{{ trans('frontend.cart.product_info') }}</th>
        <th>{{ trans('frontend.cart.unit_price') }}</th>
        <th>{{ trans('frontend.cart.quantity') }}</th>
        <th>{{ trans('frontend.cart.actions') }}</th>
      </tr>
      </thead>
      <tbody class="product_list">
      @foreach($cartItems as $item)
        @php
          $sku = $item->productSku;
          $product = $sku ? $sku->product : null;
          $skuId = $sku ? $sku->id : $item->product_sku_id;
          $price = $sku ? $sku->price : 0;
          $stock = $sku ? $sku->stock : 0;
          $isAvailable = $sku && $product && $product->on_sale;
          $imageUrl = $product ? $product->image_url : '/images/b_mode/proc-placeholder.svg';
        @endphp
        <tr data-id="{{ $skuId }}" data-price="{{ $price }}">
          <td>
            <input type="checkbox" name="select" value="{{ $skuId }}" {{ $isAvailable ? 'checked' : 'disabled' }}>
          </td>
          <td class="product_info">
            <div class="preview">
              @if($product)
                <a target="_blank" href="{{ route('products.show', [$sku->product_id]) }}">
                  <img src="{{ $imageUrl }}">
                </a>
              @else
                <img src="{{ $imageUrl }}">
              @endif
            </div>
            <div @if(!$isAvailable) class="not_on_sale" @endif>
              <span class="product_title">
                @if($product)
                  <a target="_blank" href="{{ route('products.show', [$sku->product_id]) }}">{{ $product->title }}</a>
                @else
                  <span>商品已失效</span>
                @endif
              </span>
              <span class="sku_title">{{ $sku ? $sku->title : '' }}</span>
              @if(!$isAvailable)
                <span class="warning">{{ trans('frontend.cart.product_unavailable') }}</span>
              @endif
            </div>
          </td>
          <td><span class="price">￥{{ $price }}</span></td>
          <td>
            <div class="amount-group">
              <button type="button" class="btn btn-default btn-amount-minus" @if(!$isAvailable) disabled @endif>-</button>
              <input type="text" class="form-control input-sm amount" @if(!$isAvailable) disabled @endif name="amount" value="{{ $item->amount }}" data-stock="{{ $stock }}">
              <button type="button" class="btn btn-default btn-amount-plus" @if(!$isAvailable) disabled @endif>+</button>
            </div>
          </td>
          <td>
            <button class="btn btn-xs btn-danger btn-remove">{{ trans('frontend.cart.remove') }}</button>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
    @else
      <div class="b-cart-empty b-empty-state">
        <p style="margin:0;">购物车还是空的，先去挑选想要的商品吧。</p>
        <a href="{{ route('products.index') }}" class="btn btn-primary btn-sm">前往商品页</a>
      </div>
    @endif
    <div>
      <form class="form-horizontal" role="form" id="order-form" data-create-address-url="{{ $createAddressUrl }}">
        <div class="form-group">
          <label class="control-label col-sm-3">{{ is_site_mode_b() ? '国内转寄地址（Domestic Forwarding Address）' : trans('frontend.cart.select_address') }}</label>
          <div class="col-sm-9 col-md-7">
            @if($hasAddresses)
              <select class="form-control" name="address">
                @foreach($addresses as $address)
                  <option value="{{ $address->id }}">{{ $address->is_default ? '[默认] ' : '' }}{{ $address->full_address }} {{ $address->contact_name }} {{ $address->contact_phone }}</option>
                @endforeach
              </select>
              @if(is_site_mode_b())
                <span class="help-block">请确保填写准确，代购人在完成境外代买并入境后，将通过国内顺丰/邮政转寄至此地址。</span>
              @endif
            @else
              <div class="alert alert-warning" style="margin-bottom: 10px;">
                当前还没有收货地址，请先新增地址后再提交订单。
              </div>
              <a href="{{ $createAddressUrl }}" class="btn btn-primary btn-sm">新增收货地址</a>
              <span class="help-block">地址保存后会自动返回购物车，继续完成下单。</span>
            @endif
          </div>
        </div>
        <div class="form-group">
          <label class="control-label col-sm-3">{{ trans('frontend.cart.remark') }}</label>
          <div class="col-sm-9 col-md-7">
            <textarea name="remark" class="form-control" rows="3"></textarea>
          </div>
        </div>
        <!-- 优惠码开始 -->
        <div class="form-group">
          <label class="control-label col-sm-3">{{ trans('frontend.cart.coupon_code') }}</label>
          <div class="col-sm-4">
            <input type="text" class="form-control" name="coupon_code">
          <span class="help-block" id="coupon_desc"></span>
          </div>
          <div class="col-sm-3">
            <button type="button" class="btn btn-success" id="btn-check-coupon">{{ trans('frontend.cart.check_coupon') }}</button>
          <button type="button" class="btn btn-danger" style="display: none;" id="btn-cancel-coupon">{{ trans('frontend.cart.cancel_coupon') }}</button>
          </div>
        </div>
        <!-- 优惠码结束 -->
        <div class="form-group">
          <div class="col-sm-offset-3 col-sm-9 col-md-7">
            @if(!is_site_mode_b())
              <div class="logistics-dashboard" id="logistics-dashboard">
                <div class="logistics-dashboard-title">包裹装载状态</div>
                <div class="logistics-metric">
                  <div class="logistics-metric-head">
                    <span>香烟</span>
                    <strong id="logistics-sticks-text">0 / 400 支</strong>
                  </div>
                  <div class="logistics-bar-track">
                    <div class="logistics-bar-fill logistics-ok" id="logistics-sticks-bar"></div>
                  </div>
                </div>
                <div class="logistics-metric">
                  <div class="logistics-metric-head">
                    <span>烟丝</span>
                    <strong id="logistics-weight-text">0 / 500g</strong>
                  </div>
                  <div class="logistics-bar-track">
                    <div class="logistics-bar-fill logistics-ok" id="logistics-weight-bar"></div>
                  </div>
                </div>
                <div class="logistics-limit-alert" id="logistics-limit-alert"></div>
              </div>
            @endif
            <div class="cart-checkout-summary">
              <div class="summary-line">
                <span>{{ trans('frontend.cart.selected_items') }}</span>
                <strong><span id="settlement-count">0</span> {{ trans('frontend.cart.item_unit') }}</strong>
              </div>
              <div class="summary-line">
                <span>{{ trans('frontend.cart.products_total') }}</span>
                <strong>￥<span id="settlement-products-total">0.00</span></strong>
              </div>
               <div class="summary-line">
                 <span>{{ trans('frontend.cart.service_fee') }}</span>
                 <strong>￥<span id="settlement-service-fee">0.00</span></strong>
               </div>
               <div class="summary-line">
                 <span>{{ trans('frontend.cart.packaging_fee') }}</span>
                 <strong>￥<span id="settlement-packaging-fee">0.00</span></strong>
               </div>
               <div class="summary-line">
                 <span>{{ trans('frontend.cart.ems_shipping_fee') }}</span>
                 <strong>￥<span id="settlement-ems-shipping-fee">0.00</span></strong>
               </div>
              <div class="summary-line summary-line-payable">
                <span>{{ trans('frontend.cart.payable_amount') }}</span>
                <strong>￥<span id="settlement-payable">0.00</span></strong>
              </div>
            </div>
          </div>
        </div>
        <div class="form-group">
          <div class="col-sm-offset-3 col-sm-3">
            <button type="button" class="btn btn-primary btn-create-order" {{ $hasAddresses ? '' : 'disabled' }}>{{ $hasAddresses ? trans('frontend.cart.submit_order') : '请先添加收货地址' }}</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
</div>
</div>
</div>
<div class="b-cart-mobile-action">
  <button type="button" class="btn btn-primary" id="b-cart-mobile-submit" {{ $hasAddresses ? '' : 'disabled' }}>{{ $hasAddresses ? trans('frontend.cart.submit_order') : '请先添加收货地址' }}</button>
</div>
@endsection

@section('scriptsAfterJs')
<script>
  $(document).ready(function () {
    var SERVICE_FEE_RATE = 0.13;
    var PACKAGING_FEE = 300;
    var EMS_SHIPPING_FEE = 1750;
    var urlParams = new URLSearchParams(window.location.search);
    var buyNowSku = parseInt(urlParams.get('buy_now_sku'), 10);
    var buyNowAmount = parseInt(urlParams.get('buy_now_amount'), 10);
    var isBuyNowMode = !isNaN(buyNowSku) && buyNowSku > 0;
    var logisticsState = {
      exceeded: false,
      reason: ''
    };

    function formatMoney(value) {
      return (Math.round(value * 100) / 100).toFixed(2);
    }

    function getLogisticsLevel(progress) {
      if (progress >= 100) {
        return 'logistics-danger';
      }
      if (progress >= 80) {
        return 'logistics-warn';
      }
      return 'logistics-ok';
    }

    function toggleCheckoutInterlock(exceeded, reason) {
      logisticsState.exceeded = !!exceeded;
      logisticsState.reason = reason || '';

      var $checkoutBtn = $('.btn-create-order');
      var $mobileBtn = $('#b-cart-mobile-submit');
      $checkoutBtn.prop('disabled', logisticsState.exceeded);
      $mobileBtn.prop('disabled', logisticsState.exceeded);

      var $alert = $('#logistics-limit-alert');
      if (!$alert.length) {
        return;
      }
      if (logisticsState.exceeded) {
        $alert.text(logisticsState.reason || '根据邮寄规则，香烟总支数需在 400 支以内且烟丝总克重需在 500g 以内（可同时寄 400 支香烟 + 500g 烟丝），请分拆下单。').show();
      } else {
        $alert.hide().text('');
      }
    }

    function renderLogisticsDashboard(summary) {
      if (!summary || !$('#logistics-dashboard').length) {
        return;
      }

      var sticksProgress = parseFloat(summary.sticks_progress);
      var weightProgress = parseFloat(summary.weight_progress);
      if (isNaN(sticksProgress)) {
        sticksProgress = 0;
      }
      if (isNaN(weightProgress)) {
        weightProgress = 0;
      }

      var sticksBarWidth = Math.min(sticksProgress, 100);
      var weightBarWidth = Math.min(weightProgress, 100);

      $('#logistics-sticks-text').text((summary.total_sticks || 0) + ' / ' + (summary.sticks_limit || 400) + ' 支');
      $('#logistics-weight-text').text((summary.total_weight || 0) + ' / ' + (summary.weight_limit || 500) + 'g');

      $('#logistics-sticks-bar')
        .removeClass('logistics-ok logistics-warn logistics-danger')
        .addClass(getLogisticsLevel(sticksProgress))
        .css('width', sticksBarWidth + '%');

      $('#logistics-weight-bar')
        .removeClass('logistics-ok logistics-warn logistics-danger')
        .addClass(getLogisticsLevel(weightProgress))
        .css('width', weightBarWidth + '%');

      toggleCheckoutInterlock(!!summary.exceeded, summary.reason || '');
    }

    function syncCartLogisticsSummary() {
      if (!$('#logistics-dashboard').length) {
        return;
      }

      axios.get('/cart/summary').then(function (response) {
        if (response && response.data && response.data.logistics_summary) {
          renderLogisticsDashboard(response.data.logistics_summary);
        }
      }).catch(function () {
        toggleCheckoutInterlock(false, '');
      });
    }


    function refreshSettlementSummary() {
      var selectedCount = 0;
      var productsTotal = 0;

      $('table tr[data-id]').each(function () {
        var $row = $(this);
        var $checkbox = $row.find('input[name=select][type=checkbox]');
        if ($checkbox.prop('disabled') || !$checkbox.prop('checked')) {
          return;
        }

        var amount = parseInt($row.find('input[name=amount]').val(), 10);
        if (isNaN(amount) || amount < 1) {
          return;
        }

        var price = parseFloat($row.data('price'));
        if (isNaN(price)) {
          price = 0;
        }

        selectedCount += amount;
        productsTotal += price * amount;
      });

       var serviceFee = 0;
       var packagingFee = 0;
       var emsShippingFee = 0;
       var payable = productsTotal;

       if (selectedCount > 0) {
         serviceFee = Math.round(productsTotal * SERVICE_FEE_RATE * 100) / 100;
         packagingFee = PACKAGING_FEE;
         emsShippingFee = EMS_SHIPPING_FEE;
         payable = productsTotal + serviceFee + packagingFee + emsShippingFee;
       }

       $('#settlement-count').text(selectedCount);
       $('#settlement-products-total').text(formatMoney(productsTotal));
       $('#settlement-service-fee').text(formatMoney(serviceFee));
       $('#settlement-packaging-fee').text(formatMoney(packagingFee));
       $('#settlement-ems-shipping-fee').text(formatMoney(emsShippingFee));
       $('#settlement-payable').text(formatMoney(payable));
    }

    function updateCartAmount($row, amount) {
      var skuId = $row.data('id');
      var $amountInput = $row.find('input[name=amount]');
      var stock = parseInt($amountInput.data('stock'), 10) || 99999;

      amount = parseInt(amount, 10);
      if (isNaN(amount) || amount < 1) {
        amount = 1;
      }
      if (amount > stock) {
        amount = stock;
      }

      $amountInput.val(amount);

      axios.patch('/cart/' + skuId, {
        amount: amount,
        sku_id: skuId,
      }).then(function () {
        refreshSettlementSummary();
        syncCartLogisticsSummary();
      }).catch(function (error) {
        if (error.response && error.response.status === 422) {
          var html = '<div>';
          _.each(error.response.data.errors, function (errors) {
            _.each(errors, function (message) {
              html += message + '<br>';
            });
          });
          html += '</div>';
          swal({content: $(html)[0], icon: 'error'});
          return;
        }
        refreshSettlementSummary();
        syncCartLogisticsSummary();
        swal('{{ trans('frontend.js.update_qty_failed') }}', '', 'error');
      });
    }

    function applyBuyNowMode() {
      if (!isBuyNowMode) {
        return;
      }

      var matched = false;
      $('.buy-now-mode-hint').show();
      $('#select-all').prop('checked', false).prop('disabled', true);

      $('table tr[data-id]').each(function () {
        var $row = $(this);
        var skuId = parseInt($row.data('id'), 10);
        var $checkbox = $row.find('input[name=select][type=checkbox]');

        if (skuId === buyNowSku && !$checkbox.prop('disabled')) {
          matched = true;
          $checkbox.prop('checked', true).prop('disabled', false);
          if (!isNaN(buyNowAmount) && buyNowAmount > 0) {
            updateCartAmount($row, buyNowAmount);
          }
          return;
        }

        $checkbox.prop('checked', false).prop('disabled', true);
        $row.addClass('buy-now-locked');
        $row.find('.btn-amount-minus, .btn-amount-plus, input[name=amount]').prop('disabled', true);
      });

      if (!matched) {
        swal('{{ trans('frontend.cart.buy_now_product_unavailable') }}', '', 'warning');
      }
    }

    $('.btn-amount-minus').click(function () {
      var $row = $(this).closest('tr');
      var $input = $row.find('input[name=amount]');
      var current = parseInt($input.val(), 10) || 1;
      updateCartAmount($row, current - 1);
    });

    $('.btn-amount-plus').click(function () {
      var $row = $(this).closest('tr');
      var $input = $row.find('input[name=amount]');
      var current = parseInt($input.val(), 10) || 1;
      updateCartAmount($row, current + 1);
    });

    $('input[name=amount]').on('change', function () {
      var $row = $(this).closest('tr');
      updateCartAmount($row, $(this).val());
    });

    $('input[name=select][type=checkbox], #select-all').on('change', function () {
      refreshSettlementSummary();
    });

    // 监听 移除 按钮的点击事件
    $('.btn-remove').click(function () {
      // $(this) 可以获取到当前点击的 移除 按钮的 jQuery 对象
      // closest() 方法可以获取到匹配选择器的第一个祖先元素，在这里就是当前点击的 移除 按钮之上的 <tr> 标签
      // data('id') 方法可以获取到我们之前设置的 data-id 属性的值，也就是对应的 SKU id
      var id = $(this).closest('tr').data('id');
      swal({
        title: "{{ trans('frontend.cart.confirm_remove') }}",
        icon: "warning",
        buttons: ['{{ trans('frontend.common.cancel') }}', '{{ trans('frontend.common.confirm') }}'],
        dangerMode: true,
      })
      .then(function(willDelete) {
        // 用户点击 确定 按钮，willDelete 的值就会是 true，否则为 false
        if (!willDelete) {
          return;
        }
        axios.delete('/cart/' + id)
          .then(function () {
            location.reload();
          })
      });
    });

    // 监听 全选/取消全选 单选框的变更事件
    $('#select-all').change(function() {
      // 获取单选框的选中状态
      // prop() 方法可以知道标签中是否包含某个属性，当单选框被勾选时，对应的标签就会新增一个 checked 的属性
      var checked = $(this).prop('checked');
      // 获取所有 name=select 并且不带有 disabled 属性的勾选框
      // 对于已经下架的商品我们不希望对应的勾选框会被选中，因此我们需要加上 :not([disabled]) 这个条件
      $('input[name=select][type=checkbox]:not([disabled])').each(function() {
        // 将其勾选状态设为与目标单选框一致
        $(this).prop('checked', checked);
      });

      refreshSettlementSummary();
    });

    // 监听创建订单按钮的点击事件
    $('.btn-create-order').click(function () {
      var createAddressUrl = $('#order-form').data('create-address-url');
      var addressId = $('#order-form').find('select[name=address]').val();
      if (!addressId) {
        swal({
          title: '请先添加收货地址',
          text: '新增地址后会自动返回购物车，继续完成下单。',
          icon: 'warning',
          buttons: ['取消', '去新增地址']
        }).then(function (goCreate) {
          if (goCreate) {
            window.location.href = createAddressUrl;
          }
        });
        return;
      }

      if (logisticsState.exceeded) {
        swal(logisticsState.reason || '根据邮寄规则，香烟总支数需在 400 支以内且烟丝总克重需在 500g 以内（可同时寄 400 支香烟 + 500g 烟丝），请分拆下单。', '', 'error');
        return;
      }

      // 构建请求参数，将用户选择的地址的 id 和备注内容写入请求参数
      var req = {
        address_id: addressId,
        items: [],
        remark: $('#order-form').find('textarea[name=remark]').val(),
        coupon_code: $('input[name=coupon_code]').val(), // 从优惠码输入框中获取优惠码
      };
      // 遍历 <table> 标签内所有带有 data-id 属性的 <tr> 标签，也就是每一个购物车中的商品 SKU
      $('table tr[data-id]').each(function () {
        if (isBuyNowMode && parseInt($(this).data('id'), 10) !== buyNowSku) {
          return;
        }

        // 获取当前行的单选框
        var $checkbox = $(this).find('input[name=select][type=checkbox]');
        // 如果单选框被禁用或者没有被选中则跳过
        if ($checkbox.prop('disabled') || !$checkbox.prop('checked')) {
          return;
        }
        // 获取当前行中数量输入框
        var $input = $(this).find('input[name=amount]');
        // 如果用户将数量设为 0 或者不是一个数字，则也跳过
        if ($input.val() == 0 || isNaN($input.val())) {
          return;
        }
        // 把 SKU id 和数量存入请求参数数组中
        req.items.push({
          sku_id: $(this).data('id'),
          amount: $input.val(),
        })
      });

      if (req.items.length === 0) {
        swal('{{ trans('frontend.cart.select_items_to_checkout') }}', '', 'warning');
        return;
      }

      axios.post('{{ route('orders.store') }}', req)
        .then(function (response) {
          swal('{{ trans('frontend.js.order_submitted_success') }}', '', 'success')
            .then(() => {
              location.href = '/orders/' + response.data.id;
            });
        }, function (error) {
          if (error.response.status === 422) {
            // http 状态码为 422 代表用户输入校验失败
            var html = '<div>';
            _.each(error.response.data.errors, function (errors) {
              _.each(errors, function (error) {
                html += error+'<br>';
              })
            });
            html += '</div>';
            swal({content: $(html)[0], icon: 'error'})
          } else if (error.response && error.response.status === 400) {
            swal(error.response.data.msg || '{{ trans('frontend.cart.submit_failed_check_stock') }}', '', 'error');
          } else if (error.response.status === 403) { // 这里判断状态 403
            swal(error.response.data.msg, '', 'error');
          } else {
            // 其他情况应该是系统挂了
            swal('{{ trans('frontend.js.system_error') }}', '', 'error');
          }
        });
    });

    // 检查按钮点击事件
    $('#btn-check-coupon').click(function () {
      // 获取用户输入的优惠码
      var code = $('input[name=coupon_code]').val();
      // 如果没有输入则弹框提示
      if(!code) {
        swal('{{ trans('frontend.cart.enter_coupon_code') }}', '', 'warning');
        return;
      }
      // 调用检查接口
      axios.get('/coupon_codes/' + encodeURIComponent(code))
        .then(function (response) {  // then 方法的第一个参数是回调，请求成功时会被调用
          $('#coupon_desc').text(response.data.description); // 输出优惠信息
          $('input[name=coupon_code]').prop('readonly', true); // 禁用输入框
          $('#btn-cancel-coupon').show(); // 显示 取消 按钮
          $('#btn-check-coupon').hide(); // 隐藏 检查 按钮
        }, function (error) {
          // 如果返回码是 404，说明优惠券不存在
          if(error.response.status === 404) {
            swal('{{ trans('frontend.cart.coupon_not_found') }}', '', 'error');
          } else if (error.response.status === 403) {
          // 如果返回码是 403，说明有其他条件不满足
            swal(error.response.data.msg, '', 'error');
          } else {
          // 其他错误
            swal('{{ trans('frontend.js.internal_error') }}', '', 'error');
          }
        })
    });

    // 隐藏 按钮点击事件
    $('#btn-cancel-coupon').click(function () {
      $('#coupon_desc').text(''); // 隐藏优惠信息
      $('input[name=coupon_code]').prop('readonly', false);  // 启用输入框
      $('#btn-cancel-coupon').hide(); // 隐藏 取消 按钮
      $('#btn-check-coupon').show(); // 显示 检查 按钮
    });

    applyBuyNowMode();
    refreshSettlementSummary();
    syncCartLogisticsSummary();

    $('#b-cart-mobile-submit').on('click', function () {
      $('.btn-create-order').trigger('click');
    });

    $('.product_info .preview img').on('error', function () {
      $(this).attr('src', '/images/b_mode/proc-placeholder.svg');
    });

  });
</script>
@endsection