@extends('layouts.app')
@section('title', $product->title)

@section('content')
<div class="show-page-container">
  <div class="show-breadcrumb">
    <a href="{{ route('root') }}">首页</a>
    <span class="sep">/</span>
    <a href="{{ route('products.index') }}">商品列表</a>
    @if($breadcrumbParent)
      <span class="sep">/</span>
      <a href="{{ route('products.index', ['category' => $breadcrumbParent->id]) }}">{{ $breadcrumbParent->name }}</a>
    @endif
    @if($breadcrumbChild)
      <span class="sep">/</span>
      <a href="{{ route('products.index', ['category' => $breadcrumbChild->id]) }}">{{ $breadcrumbChild->name }}</a>
    @endif
    <span class="sep">/</span>
    <span class="current">{{ $product->title }}</span>
  </div>

  @php
    $defaultSku = $product->skus->first();
    $defaultLimitQty = (int) ($product->limit_qty ?: 0);
    $saleStatus = $product->inventory_status;
    $defaultSkuDescription = trim((string) optional($defaultSku)->description);
    if ($defaultSkuDescription === '') {
      $defaultSkuDescription = trans('frontend.product.subtitle');
    }
  @endphp

  <div class="product-show">
    <div class="product-gallery">
      <div class="gallery-main">
        <div class="gallery-wrapper">
          <img class="main-image" src="{{ $product->image_url }}" alt="{{ $product->title }}">
        </div>
      </div>
      <div class="gallery-thumbs">
        <div class="thumb-item is-active">
          <img src="{{ $product->image_url }}" alt="{{ $product->title }}">
        </div>
      </div>
    </div>

    <div class="product-details">
      <div class="title-stack">
        <h1 class="product-title">{{ $product->title }}</h1>
        <p class="product-ref" id="product-sku-description">{{ $defaultSkuDescription }}</p>
        @if(is_site_mode_a())
          <p class="product-ref text-muted" style="margin-top:6px;">
            {{ $product->shipping_mode_label }}
            @if($product->tobacco_type)
              · {{ $product->tobacco_type_label }}
              · 单位 {{ (int) $product->unit_weight_grams }}g
              @if($product->countsTowardStickLimit() && $product->unit_sticks)
                · 每包 {{ (int) $product->unit_sticks }} 支
              @endif
            @endif
          </p>
          @php
            $emsMaxQty = $defaultSku ? app(\App\Services\OrderTobaccoLimitService::class)->maxUnitsForSku($defaultSku) : null;
          @endphp
          @if($product->shipping_mode_resolved === \App\Services\ShippingModeService::MODE_EMS && $emsMaxQty)
            <p class="product-ref text-muted" style="font-size:12px;">单笔 EMS 订单参考上限：约 {{ $emsMaxQty }} 件（受 香烟+加热烟 400 支 / 烟丝 5kg / 16kg 计费重量限制）</p>
          @elseif($product->shipping_mode_resolved === \App\Services\ShippingModeService::MODE_TAX_INCLUDED)
            <p class="product-ref text-muted" style="font-size:12px;">含税包邮：报价已含运费与税费，不可与 EMS 商品混单</p>
          @endif
        @endif
      </div>

      <div class="pricing-section">
        <div class="price-display">{{ format_shop_price($product->price) }}</div>
      </div>

      @if($product->skus->count() > 1)
        <div class="sku-selection">
          <label>{{ trans('frontend.product.select_sku') }}</label>
          <div class="sku-options">
            @foreach($product->skus as $sku)
              <label class="sku-option" data-price="{{ $sku->price }}" data-description="{{ $sku->description }}" title="{{ $sku->description }}">
                <input type="radio" name="skus" value="{{ $sku->id }}">
                <span class="sku-label">{{ $sku->title }}</span>
              </label>
            @endforeach
          </div>
        </div>
      @else
        <input type="hidden" id="single-sku-id" value="{{ optional($defaultSku)->id }}">
      @endif

      <div class="action-box">
        <div class="qty-container">
          <label class="qty-label">{{ trans('frontend.product.qty_label') }}</label>
          <div class="qty-selector">
            <button type="button" class="qty-btn qty-minus">−</button>
            <input type="text" class="qty-input" value="1" min="1">
            <button type="button" class="qty-btn qty-plus">+</button>
          </div>
          <div class="qty-meta" data-sale-status="{{ $saleStatus }}" data-purchase-limit="{{ $defaultLimitQty }}">
            <span class="quota-hint" style="display:none;"></span>
          </div>
        </div>

        <div class="button-group">
          @if(!is_site_mode_b())
            <button type="button" class="btn btn-primary btn-add-to-cart">{{ trans('frontend.product.add_to_cart') }}</button>
          @endif
          <button type="button" class="btn btn-secondary btn-buy-now">{{ trans('frontend.product.buy_now') }}</button>
        </div>

        <div class="wishlist-section">
          @if($favored)
            <button type="button" class="btn btn-outline btn-disfavor">{{ trans('frontend.product.disfavor') }}</button>
          @else
            <button type="button" class="btn btn-outline btn-favor">{{ trans('frontend.product.favor') }}</button>
          @endif
        </div>
      </div>

      <div class="metadata-section">
        <div class="meta-row">
          <span class="meta-label">{{ trans('frontend.product.sku_label') }}</span>
          <span class="meta-value">ATA-{{ $product->id }}</span>
        </div>
        <div class="meta-row">
          <span class="meta-label">{{ trans('frontend.product.category') }}</span>
          <span class="meta-value">
            @if($breadcrumbParent)
              <a href="{{ route('products.index', ['category' => $breadcrumbParent->id]) }}">{{ $breadcrumbParent->name }}</a>
            @else
              —
            @endif
          </span>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scriptsAfterJs')
<script>
  $(document).ready(function () {
    var productI18n = @json(trans('frontend.product'));
    var jsI18n = @json(trans('frontend.js'));
    var isLoggedIn = @json(auth()->check());
    var loginUrl = '{{ route('login') }}';
    var defaultAddText = $('.btn-add-to-cart').text();
    var defaultBuyText = $('.btn-buy-now').text();

    function formatShopPrice(value) {
      return window.formatShopPrice(value);
    }

    function getSelectedSkuId() {
      var checkedSkuId = $('input[name=skus]:checked').val();
      if (checkedSkuId) {
        return checkedSkuId;
      }

      var singleSkuId = $('#single-sku-id').val();
      if (singleSkuId) {
        return singleSkuId;
      }

      var firstSkuId = $('input[name=skus]').first().val();
      return firstSkuId || null;
    }

    function getActiveSkuData() {
      var skuId = getSelectedSkuId();
      var status = String($('.qty-meta').data('sale-status') || 'ACTIVE');
      var limitQty = parseInt($('.qty-meta').data('purchase-limit'), 10) || 0;
      var maxAllowed = 999;

      if (status === 'DEPLETED') {
        maxAllowed = 0;
      } else if (status === 'LIMITED' && limitQty > 0) {
        maxAllowed = limitQty;
      }

      return {
        skuId: skuId,
        limitQty: limitQty,
        status: status,
        maxAllowed: maxAllowed
      };
    }

    function applySkuStateUI() {
      var skuData = getActiveSkuData();
      var isDepleted = skuData.status === 'DEPLETED';
      var isLimited = skuData.status === 'LIMITED';

      if (isDepleted) {
        $('.btn-add-to-cart, .btn-buy-now').prop('disabled', true).addClass('is-depleted');
        $('.btn-add-to-cart').text(productI18n.sold_out || '已售罄');
        $('.btn-buy-now').text(productI18n.sold_out || '已售罄');
        $('.qty-minus, .qty-plus, .qty-input').prop('disabled', true);
        $('.quota-hint').hide();
        return;
      }

      $('.btn-add-to-cart, .btn-buy-now').prop('disabled', false).removeClass('is-depleted');
      $('.btn-add-to-cart').text(defaultAddText);
      $('.btn-buy-now').text(defaultBuyText);
      $('.qty-minus, .qty-plus, .qty-input').prop('disabled', false);

      if (isLimited) {
        $('.quota-hint').text((productI18n.quota_prefix || '限购') + ': ' + skuData.limitQty + ' ' + (productI18n.quota_suffix || '件/单')).show();
      } else {
        $('.quota-hint').hide();
      }
    }

    function normalizeQty(nextQty, shouldNotify) {
      var skuData = getActiveSkuData();
      var qty = parseInt(nextQty, 10);

      if (isNaN(qty) || qty < 1) {
        qty = 1;
      }

      if (skuData.maxAllowed <= 0) {
        qty = 1;
      } else if (qty > skuData.maxAllowed) {
        qty = skuData.maxAllowed;
        if (shouldNotify && skuData.status === 'LIMITED') {
          var msg = (jsI18n.limited_warning || '该商品限购，单笔最多购买 :count 件。').replace(':count', skuData.maxAllowed);
          swal(msg, '', 'warning');
        }
      }

      $('.qty-input').val(qty);
    }

    function ensureLoggedInForShop() {
      if (isLoggedIn) {
        return true;
      }
      window.promptLoginToShop(loginUrl);
      return false;
    }

    function addCurrentSkuToCart(redirectToCheckout) {
      if (!ensureLoggedInForShop()) {
        return;
      }

      if ($('.btn-add-to-cart').prop('disabled')) {
        return;
      }

      var skuData = getActiveSkuData();
      var skuId = skuData.skuId;
      if (!skuId) {
        swal(jsI18n.spec_unavailable || '该商品暂无可售规格', '', 'warning');
        return;
      }

      var amount = parseInt($('.qty-input').val(), 10);
      if (isNaN(amount) || amount < 1) {
        amount = 1;
      }
      normalizeQty(amount, false);
      amount = parseInt($('.qty-input').val(), 10);

      axios.post('{{ route('cart.add') }}', {
        sku_id: skuId,
        amount: amount
      }).then(function (res) {
        if (window.MiniCart) {
          if (!window.MiniCart.setCount || !res.data || typeof res.data.count === 'undefined') {
            if (window.MiniCart.refresh) {
              window.MiniCart.refresh();
            }
          } else {
            window.MiniCart.setCount(res.data.count);
          }
        }

        if (redirectToCheckout) {
          swal(jsI18n.added_to_cart_redirect || '已加入购物车，正在前往结算', '', 'success').then(function () {
            location.href = '{{ route('cart.index') }}?buy_now_sku=' + encodeURIComponent(skuId) + '&buy_now_amount=' + encodeURIComponent(amount);
          });
          return;
        }

        swal(jsI18n.added_to_cart || '已加入购物车', '', 'success');
      }).catch(function (error) {
        if (error.response && error.response.status === 401) {
          window.promptLoginToShop(loginUrl);
          return;
        }
        if (error.response && error.response.status === 400 && error.response.data && error.response.data.msg) {
          swal(error.response.data.msg, '', 'error');
          return;
        }
        if (error.response && error.response.status === 422 && error.response.data && error.response.data.errors) {
          var html = '<div>';
          _.each(error.response.data.errors, function (errors) {
            _.each(errors, function (message) {
              html += message + '<br>';
            });
          });
          html += '</div>';
          swal({ content: $(html)[0], icon: 'error' });
          return;
        }
        swal(jsI18n.system_error || '系统错误', '', 'error');
      });
    }

    $('label.sku-option').on('click', function () {
      var $input = $(this).find('input[name=skus]');
      $input.prop('checked', true).trigger('change');
    });

    $('input[name=skus]').on('change', function () {
      var $activeSku = $(this).closest('.sku-option');
      $('.price-display').text(formatShopPrice($activeSku.data('price')));
      var description = String($activeSku.data('description') || '').trim();
      if (!description) {
        description = '{{ addslashes(trans('frontend.product.subtitle')) }}';
      }
      $('#product-sku-description').text(description);
      applySkuStateUI();
      normalizeQty($('.qty-input').val(), false);
    });

    $('.qty-minus').on('click', function () {
      normalizeQty(parseInt($('.qty-input').val(), 10) - 1, false);
    });

    $('.qty-plus').on('click', function () {
      normalizeQty(parseInt($('.qty-input').val(), 10) + 1, true);
    });

    $('.qty-input').on('change blur', function () {
      normalizeQty($(this).val(), true);
    });

    $('.btn-add-to-cart').on('click', function () {
      addCurrentSkuToCart(false);
    });

    $('.btn-buy-now').on('click', function () {
      addCurrentSkuToCart(true);
    });

    $('.btn-favor').on('click', function () {
      if (!ensureLoggedInForShop()) {
        return;
      }

      axios.post('{{ route('products.favor', ['product' => $product->id]) }}').then(function () {
        swal(jsI18n.action_success || '操作成功', '', 'success').then(function () {
          location.reload();
        });
      }).catch(function (error) {
        if (error.response && error.response.status === 401) {
          window.promptLoginToShop(loginUrl);
        } else if (error.response && error.response.data && error.response.data.msg) {
          swal(error.response.data.msg, '', 'error');
        } else {
          swal(jsI18n.system_error || '系统错误', '', 'error');
        }
      });
    });

    $('.btn-disfavor').on('click', function () {
      axios.delete('{{ route('products.disfavor', ['product' => $product->id]) }}').then(function () {
        swal(jsI18n.action_success || '操作成功', '', 'success').then(function () {
          location.reload();
        });
      }).catch(function (error) {
        if (error.response && error.response.status === 401) {
          window.promptLoginToShop(loginUrl);
        } else if (error.response && error.response.data && error.response.data.msg) {
          swal(error.response.data.msg, '', 'error');
        } else {
          swal(jsI18n.system_error || '系统错误', '', 'error');
        }
      });
    });

    if ($('input[name=skus]').length) {
      $('input[name=skus]').first().prop('checked', true).trigger('change');
    } else {
      applySkuStateUI();
      normalizeQty(1, false);
    }
  });
</script>
@endsection
