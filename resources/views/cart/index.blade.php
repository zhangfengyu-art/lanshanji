@extends('layouts.app')
@section('title', trans('frontend.cart.title'))

@section('content')
<div class="row">
<div class="col-lg-10 col-lg-offset-1">
<div class="panel panel-default">
  <div class="panel-heading">{{ trans('frontend.cart.my_cart') }}</div>
  <div class="panel-body">
    <div class="alert alert-info buy-now-mode-hint" style="display:none; margin-bottom: 14px;">
      {{ trans('frontend.cart.buy_now_mode_hint') }}
    </div>
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
        @php $p = $item->productSku->product; @endphp
        <tr data-id="{{ $item->productSku->id }}"
            data-price="{{ $item->productSku->price }}"
            data-shipping-mode="{{ $p->shipping_mode_resolved }}"
            data-tobacco-type="{{ $p->tobacco_type }}"
            data-unit-weight-grams="{{ (int) $p->unit_weight_grams }}"
            data-unit-sticks="{{ (int) $p->unit_sticks }}"
            data-ems-max-qty="{{ is_site_mode_a() ? (app(\App\Services\OrderTobaccoLimitService::class)->maxUnitsForSku($item->productSku) ?: '') : '' }}">
          <td>
            <input type="checkbox" name="select" value="{{ $item->productSku->id }}" {{ $item->productSku->product->on_sale ? 'checked' : 'disabled' }}>
          </td>
          <td class="product_info">
            <div class="preview">
              <a target="_blank" href="{{ route('products.show', [$item->productSku->product_id]) }}">
                <img src="{{ $item->productSku->product->image_url }}">
              </a>
            </div>
            <div @if(!$item->productSku->product->on_sale) class="not_on_sale" @endif>
              <span class="product_title">
                <a target="_blank" href="{{ route('products.show', [$item->productSku->product_id]) }}">{{ $item->productSku->product->title }}</a>
              </span>
              <span class="sku_title">{{ $item->productSku->title }}</span>
              @if(!$item->productSku->product->on_sale)
                <span class="warning">{{ trans('frontend.cart.product_unavailable') }}</span>
              @endif
            </div>
          </td>
          <td><span class="price">￥{{ $item->productSku->price }}</span></td>
          <td>
            <div class="amount-group">
              <button type="button" class="btn btn-default btn-amount-minus" @if(!$item->productSku->product->on_sale) disabled @endif>-</button>
              <input type="text" class="form-control input-sm amount" @if(!$item->productSku->product->on_sale || $item->productSku->isDepleted()) disabled @endif name="amount" value="{{ $item->amount }}" data-sale-status="{{ $item->productSku->product->inventory_status }}" data-max-qty="{{ $item->productSku->getOrderMaxQty() }}">
              <button type="button" class="btn btn-default btn-amount-plus" @if(!$item->productSku->product->on_sale) disabled @endif>+</button>
            </div>
          </td>
          <td>
            <button class="btn btn-xs btn-danger btn-remove">{{ trans('frontend.cart.remove') }}</button>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
    <div>
      <form class="form-horizontal" role="form" id="order-form">
        <div class="form-group">
          <label class="control-label col-sm-3">{{ is_site_mode_b() ? '国内转寄地址（Domestic Forwarding Address）' : trans('frontend.cart.select_address') }}</label>
          <div class="col-sm-9 col-md-7">
            <select class="form-control" name="address">
              @foreach($addresses as $address)
                <option value="{{ $address->id }}">{{ $address->is_default ? '[默认] ' : '' }}{{ $address->full_address }} {{ $address->contact_name }} {{ $address->contact_phone }}</option>
              @endforeach
            </select>
            @if(is_site_mode_b())
              <span class="help-block">请确保填写准确，代购人在完成境外代买并入境后，将通过国内顺丰/邮政转寄至此地址。</span>
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
            <div class="cart-checkout-summary">
              <div class="summary-line">
                <span>{{ trans('frontend.cart.selected_items') }}</span>
                <strong><span id="settlement-count">0</span> {{ trans('frontend.cart.item_unit') }}</strong>
              </div>
              <div class="summary-line" id="settlement-shipping-mode-line" style="display:none;">
                <span>寄送模式</span>
                <strong><span id="settlement-shipping-mode">—</span></strong>
              </div>
              <div class="summary-line">
                <span>{{ trans('frontend.cart.products_total') }}</span>
                <strong><span class="money-prefix">@if(is_site_mode_a())JPY ¥@else￥@endif</span><span id="settlement-products-total">0.00</span></strong>
              </div>
               <div class="summary-line">
                 <span>{{ trans('frontend.cart.service_fee') }}</span>
                 <strong><span class="money-prefix">@if(is_site_mode_a())JPY ¥@else￥@endif</span><span id="settlement-service-fee">0.00</span></strong>
               </div>
               <div class="summary-line">
                 <span>{{ trans('frontend.cart.packaging_fee') }}</span>
                 <strong><span class="money-prefix">@if(is_site_mode_a())JPY ¥@else￥@endif</span><span id="settlement-packaging-fee">0.00</span></strong>
               </div>
               <div class="summary-line" id="settlement-ems-fee-line">
                 <span>{{ trans('frontend.cart.ems_shipping_fee') }}</span>
                 <strong><span class="money-prefix">@if(is_site_mode_a())JPY ¥@else￥@endif</span><span id="settlement-ems-shipping-fee">0.00</span></strong>
               </div>
              <div class="summary-line" id="settlement-weight-line">
                <span>计费重量（EMS）</span>
                <strong><span id="settlement-weight-grams">0</span> g</strong>
              </div>
              <div class="summary-line text-muted" style="font-size:12px;" id="settlement-tobacco-hint">
                单笔限：香烟+加热烟 ≤ {{ $tobaccoLimits['max_sticks'] }} 支，手卷烟丝 ≤ {{ round($tobaccoLimits['max_rolling_grams'] / 1000, 1) }}kg；EMS 计费上限 {{ round($tobaccoLimits['max_billable_grams'] / 1000, 1) }}kg
              </div>
              <div class="summary-line text-muted" style="font-size:12px;" id="settlement-tobacco-progress"></div>
              <div class="summary-line summary-line-payable">
                <span>{{ trans('frontend.cart.payable_amount') }}@if(is_site_mode_a())（日元）@endif</span>
                <strong><span class="money-prefix">@if(is_site_mode_a())JPY ¥@else￥@endif</span><span id="settlement-payable">0.00</span></strong>
              </div>
              <p class="text-danger" id="settlement-error" style="display:none; margin-top:8px; font-size:13px;"></p>
            </div>
          </div>
        </div>
        <div class="form-group">
          <div class="col-sm-offset-3 col-sm-3">
            <button type="button" class="btn btn-primary btn-create-order">{{ trans('frontend.cart.submit_order') }}</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
</div>
</div>
@endsection

@section('scriptsAfterJs')
<script>
  $(document).ready(function () {
    var IS_SITE_MODE_A = @json(is_site_mode_a());
    var TOBACCO_MAX_STICKS = {{ (int) $tobaccoLimits['max_sticks'] }};
    var TOBACCO_MAX_ROLLING_GRAMS = {{ (int) $tobaccoLimits['max_rolling_grams'] }};
    var SERVICE_FEE_RATE = 0.13;
    var PACKAGING_FEE = 300;
    var quoteRequestId = 0;
    var urlParams = new URLSearchParams(window.location.search);
    var buyNowSku = parseInt(urlParams.get('buy_now_sku'), 10);
    var buyNowAmount = parseInt(urlParams.get('buy_now_amount'), 10);
    var isBuyNowMode = !isNaN(buyNowSku) && buyNowSku > 0;

    function formatMoney(value) {
      return (Math.round(value * 100) / 100).toFixed(2);
    }


    function collectSelectedItems() {
      var payload = [];
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
        payload.push({
          sku_id: parseInt($row.data('id'), 10),
          amount: amount,
        });
      });
      return payload;
    }

    function refreshSettlementSummary() {
      var items = collectSelectedItems();
      var selectedCount = 0;
      items.forEach(function (it) { selectedCount += it.amount; });
      $('#settlement-count').text(selectedCount);

      if (items.length === 0) {
        $('#settlement-products-total').text('0.00');
        $('#settlement-service-fee').text('0.00');
        $('#settlement-packaging-fee').text('0.00');
        $('#settlement-ems-shipping-fee').text('0.00');
        $('#settlement-payable').text('0.00');
        $('#settlement-weight-grams').text('0');
        $('#settlement-error').hide().text('');
        $('.btn-create-order').prop('disabled', false);
        return;
      }

      if (!IS_SITE_MODE_A) {
        var productsTotal = 0;
        items.forEach(function (it) {
          var $row = $('table tr[data-id="' + it.sku_id + '"]');
          var price = parseFloat($row.data('price')) || 0;
          productsTotal += price * it.amount;
        });
        var serviceFee = Math.round(productsTotal * SERVICE_FEE_RATE * 100) / 100;
        var emsFee = 1750;
        var payable = productsTotal + serviceFee + PACKAGING_FEE + emsFee;
        $('#settlement-products-total').text(formatMoney(productsTotal));
        $('#settlement-service-fee').text(formatMoney(serviceFee));
        $('#settlement-packaging-fee').text(formatMoney(PACKAGING_FEE));
        $('#settlement-ems-shipping-fee').text(formatMoney(emsFee));
        $('#settlement-payable').text(formatMoney(payable));
        $('#settlement-weight-grams').text('—');
        $('#settlement-error').hide();
        $('.btn-create-order').prop('disabled', false);
        return;
      }

      var reqId = ++quoteRequestId;
      axios.post('{{ route('cart.quote') }}', { items: items })
        .then(function (res) {
          if (reqId !== quoteRequestId) {
            return;
          }
          var data = res.data || {};
          $('#settlement-products-total').text(formatMoney(data.products_total || 0));
          $('#settlement-service-fee').text(formatMoney(data.service_fee || 0));
          $('#settlement-packaging-fee').text(formatMoney(data.packaging_fee || 0));
          $('#settlement-ems-shipping-fee').text(formatMoney(data.ems_shipping_fee || 0));
          $('#settlement-payable').text(formatMoney(data.payable || 0));
          $('#settlement-weight-grams').text(data.total_weight_grams || 0);
          if (data.shipping_mode_label) {
            $('#settlement-shipping-mode-line').show();
            $('#settlement-shipping-mode').text(data.shipping_mode_label);
          } else {
            $('#settlement-shipping-mode-line').hide();
          }
          if (data.shipping_mode === 'tax_included') {
            $('#settlement-ems-fee-line').hide();
            $('#settlement-weight-line').hide();
          } else {
            $('#settlement-ems-fee-line').show();
            $('#settlement-weight-line').show();
          }
          var progress = [];
          if (typeof data.total_cigarette_sticks === 'number') {
            progress.push('香烟+加热烟 ' + data.total_cigarette_sticks + ' / ' + TOBACCO_MAX_STICKS + ' 支（剩余 ' + (data.remaining_cigarette_sticks || 0) + '）');
          }
          if (typeof data.total_rolling_tobacco_grams === 'number') {
            progress.push('烟丝 ' + (data.total_rolling_tobacco_grams / 1000).toFixed(2) + ' / ' + (TOBACCO_MAX_ROLLING_GRAMS / 1000).toFixed(1) + ' kg（剩余 ' + ((data.remaining_rolling_tobacco_grams || 0) / 1000).toFixed(2) + ' kg）');
          }
          $('#settlement-tobacco-progress').text(progress.join('；'));
          $('#settlement-error').hide().text('');
          $('.btn-create-order').prop('disabled', false);
        })
        .catch(function (err) {
          if (reqId !== quoteRequestId) {
            return;
          }
          var data = (err.response && err.response.data) ? err.response.data : {};
          $('#settlement-products-total').text(formatMoney(data.products_total || 0));
          $('#settlement-service-fee').text('0.00');
          $('#settlement-packaging-fee').text('0.00');
          $('#settlement-ems-shipping-fee').text('0.00');
          $('#settlement-payable').text(formatMoney(data.payable || data.products_total || 0));
          $('#settlement-weight-grams').text(data.total_weight_grams || '—');
          $('#settlement-tobacco-progress').text('');
          $('#settlement-error').show().text(data.message || '{{ trans('frontend.js.operation_failed_retry') }}');
          $('.btn-create-order').prop('disabled', true);
        });
    }

    function updateCartAmount($row, amount) {
      var skuId = $row.data('id');
      var $amountInput = $row.find('input[name=amount]');
      var saleStatus = String($amountInput.data('sale-status') || 'ACTIVE');
      var maxQty = parseInt($amountInput.data('max-qty'), 10) || 999;

      amount = parseInt(amount, 10);
      if (isNaN(amount) || amount < 1) {
        amount = 1;
      }
      if (saleStatus === 'DEPLETED') {
        amount = 1;
      } else if (maxQty > 0 && amount > maxQty) {
        amount = maxQty;
      }

      $amountInput.val(amount);

      axios.patch('/cart/' + skuId, {
        amount: amount,
        sku_id: skuId,
      }).then(function () {
        refreshSettlementSummary();
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
      // 构建请求参数，将用户选择的地址的 id 和备注内容写入请求参数
      var req = {
        address_id: $('#order-form').find('select[name=address]').val(),
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

  });
</script>
@endsection