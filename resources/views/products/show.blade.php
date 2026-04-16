@extends('layouts.app')
@section('title', $product->title)

@section('styles')
<style>
  body.site-mode-b .show-page-container {
    max-width: 1120px;
    margin: 10px auto 26px;
    padding: 0 6px;
  }

  body.site-mode-b .show-breadcrumb {
    margin-bottom: 12px;
    padding: 9px 12px;
    border-radius: 12px;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
    font-size: 12px;
    color: #64748b;
  }

  body.site-mode-b .product-show {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
  }

  body.site-mode-b .product-gallery,
  body.site-mode-b .product-details {
    border-radius: 16px;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
    padding: 14px;
  }

  body.site-mode-b .gallery-wrapper {
    border-radius: 14px;
    overflow: hidden;
    background: #e9eef6;
  }

  body.site-mode-b .main-image {
    width: 100%;
    aspect-ratio: 1/1;
    object-fit: cover;
  }

  body.site-mode-b .gallery-thumbs {
    margin-top: 10px;
  }

  body.site-mode-b .thumb-item {
    width: 68px;
    height: 68px;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid rgba(15, 23, 42, 0.1);
    background: #eef3fb;
  }

  body.site-mode-b .product-title {
    font-size: 28px;
    font-weight: 800;
    line-height: 1.25;
    margin: 0;
  }

  body.site-mode-b .product-ref {
    margin-top: 6px;
    font-size: 13px;
    color: #64748b;
  }

  body.site-mode-b .pricing-section {
    margin-top: 12px;
    padding: 0;
    border: 0;
    background: transparent;
  }

  body.site-mode-b .price-display .amount {
    font-size: 36px;
    line-height: 1;
    font-weight: 800;
    color: #b45309;
  }

  body.site-mode-b .price-display .currency {
    margin-left: 6px;
    color: #92400e;
    font-size: 13px;
    font-weight: 700;
  }

  body.site-mode-b .sku-selection,
  body.site-mode-b .action-box,
  body.site-mode-b .metadata-section {
    margin-top: 12px;
    padding: 12px;
    border-radius: 12px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fafcff;
  }

  body.site-mode-b .sku-option {
    border-radius: 10px;
    border-color: #d7dee8;
    background: #fff;
  }

  body.site-mode-b .sku-option.is-selected {
    border-color: rgba(44, 123, 229, 0.45);
    background: rgba(44, 123, 229, 0.08);
  }

  body.site-mode-b .qty-selector {
    border-radius: 10px;
    overflow: hidden;
  }

  body.site-mode-b .qty-input,
  body.site-mode-b .qty-btn {
    min-height: 40px;
  }

  body.site-mode-b .button-group {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
  }

  body.site-mode-b .button-group .btn,
  body.site-mode-b .wishlist-section .btn {
    border-radius: 12px;
    min-height: 42px;
    font-weight: 700;
  }

  body.site-mode-b .metadata-section .meta-row {
    padding: 7px 0;
    border-top: 1px dashed rgba(15, 23, 42, 0.08);
  }

  body.site-mode-b .metadata-section .meta-row:first-child {
    border-top: 0;
    padding-top: 0;
  }

  body.site-mode-b .show-mobile-sticky {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 62px;
    z-index: 1033;
    padding: 8px 10px calc(8px + env(safe-area-inset-bottom));
    background: rgba(244, 247, 251, 0.94);
    border-top: 1px solid rgba(15, 23, 42, 0.08);
    backdrop-filter: blur(8px);
    display: none;
    gap: 8px;
  }

  body.site-mode-b .show-mobile-sticky .btn {
    flex: 1;
    border-radius: 12px;
    min-height: 42px;
    font-weight: 700;
  }

  @media (max-width: 992px) {
    body.site-mode-b .product-show {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 768px) {
    body.site-mode-b .show-page-container {
      margin: 4px auto 14px;
      padding-bottom: 110px;
    }

    body.site-mode-b .product-gallery,
    body.site-mode-b .product-details {
      padding: 10px;
      border-radius: 14px;
    }

    body.site-mode-b .product-title {
      font-size: 22px;
    }

    body.site-mode-b .price-display .amount {
      font-size: 31px;
    }

    body.site-mode-b .button-group {
      grid-template-columns: 1fr;
    }

    body.site-mode-b .show-mobile-sticky {
      display: flex;
    }
  }
</style>
@endsection

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
    $defaultLimitQty = (int) optional($defaultSku)->limit_qty;
    $defaultSkuDescription = trim((string) optional($defaultSku)->description);
    if (is_site_mode_b()) {
      $defaultSkuDescription = trim((string) $product->mapped_category);
    }
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
      </div>

      <div class="pricing-section">
        <div class="price-display">
          <span class="amount">{{ number_format($product->price, 2, '.', '') }}</span><span class="currency">日元</span>
        </div>
      </div>

      @if($product->skus->count() > 1)
        <div class="sku-selection">
            <label>加入购物车</label>
          <div class="sku-options">
            @foreach($product->skus as $sku)
              <label class="sku-option" data-price="{{ $sku->price }}" data-stock="{{ $sku->stock }}" data-limit-qty="{{ (int) $sku->limit_qty }}" data-description="{{ $sku->description }}" title="{{ $sku->description }}">
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
          <div class="qty-meta" data-default-stock="{{ (int) $product->stock }}" data-default-limit-qty="{{ $defaultLimitQty }}">
            <span class="stock-status">{{ trans('frontend.product.stock_prefix') }}: {{ (int) $product->stock }}</span>
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

  <div class="show-mobile-sticky" id="show-mobile-sticky">
    @if(!is_site_mode_b())
      <button type="button" class="btn btn-primary" id="sticky-add-cart">{{ trans('frontend.product.add_to_cart') }}</button>
    @endif
    <button type="button" class="btn btn-secondary" id="sticky-buy-now">{{ trans('frontend.product.buy_now') }}</button>
  </div>
</div>
@endsection

@section('scriptsAfterJs')
<script>
  $(document).ready(function () {
    if (typeof window.isSiteModeB === 'undefined' || window.isSiteModeB) {
      $('body').addClass('site-mode-b');
    }

    var productI18n = @json(trans('frontend.product'));
    var jsI18n = @json(trans('frontend.js'));
    var defaultAddText = $('.btn-add-to-cart').text();
    var defaultBuyText = $('.btn-buy-now').text();

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
      var $activeInput = skuId ? $('input[name=skus][value="' + skuId + '"]') : $();
      var $activeSku = $activeInput.closest('.sku-option');

      var stock;
      var limitQty;

      if ($activeSku.length) {
        stock = parseInt($activeSku.data('stock'), 10);
        limitQty = parseInt($activeSku.data('limit-qty'), 10);
      } else {
        stock = parseInt($('.qty-meta').data('default-stock'), 10);
        limitQty = parseInt($('.qty-meta').data('default-limit-qty'), 10);
      }

      if (isNaN(stock) || stock < 0) {
        stock = 0;
      }
      if (isNaN(limitQty) || limitQty < 0) {
        limitQty = 0;
      }

      var status = 'ACTIVE';
      var maxAllowed = stock;
      if (stock <= 0) {
        status = 'DEPLETED';
        maxAllowed = 0;
      } else if (limitQty > 0) {
        status = 'LIMITED';
        maxAllowed = Math.min(stock, limitQty);
      }

      return {
        skuId: skuId,
        stock: stock,
        limitQty: limitQty,
        status: status,
        maxAllowed: maxAllowed
      };
    }

    function applySkuStateUI() {
      var skuData = getActiveSkuData();
      var isDepleted = skuData.status === 'DEPLETED';
      var isLimited = skuData.status === 'LIMITED';

      $('.stock-status').toggleClass('is-unavailable', isDepleted);
      if (isDepleted) {
        $('.stock-status').text(productI18n.status_unavailable || '状态：暂不可售');
        $('.btn-add-to-cart, .btn-buy-now').prop('disabled', true).addClass('is-depleted');
        $('.btn-add-to-cart').text(productI18n.sold_out || '已售罄');
        $('.btn-buy-now').text(productI18n.sold_out || '已售罄');
        $('.qty-minus, .qty-plus, .qty-input').prop('disabled', true);
        $('.quota-hint').hide();
        return;
      }

      $('.stock-status').text((productI18n.stock_prefix || '库存') + ': ' + skuData.stock);
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

    function addCurrentSkuToCart(redirectToCheckout) {
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
      }).then(function () {
        if (window.MiniCart && window.MiniCart.refresh) {
          window.MiniCart.refresh();
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
          swal(jsI18n.please_login || '请先登录', '', 'error');
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
      $('label.sku-option').removeClass('is-selected');
      $activeSku.addClass('is-selected');
      var skuPrice = parseFloat($activeSku.data('price')) || 0;
      $('.price-display .amount').text(skuPrice.toFixed(2));
      var description = String($activeSku.data('description') || '').trim();
      if (!description) {
        description = '{{ addslashes(trans('frontend.product.subtitle')) }}';
      }
      $('#product-sku-description').text(description);
      applySkuStateUI();
      normalizeQty($('.qty-input').val(), false);
    });

    $('input[name=skus]:checked').closest('.sku-option').addClass('is-selected');

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

    $('#sticky-add-cart').on('click', function () {
      $('.btn-add-to-cart').trigger('click');
    });

    $('#sticky-buy-now').on('click', function () {
      $('.btn-buy-now').trigger('click');
    });

    $('.btn-favor').on('click', function () {
      axios.post('{{ route('products.favor', ['product' => $product->id]) }}').then(function () {
        swal(jsI18n.action_success || '操作成功', '', 'success').then(function () {
          location.reload();
        });
      }).catch(function (error) {
        if (error.response && error.response.status === 401) {
          swal(jsI18n.please_login || '请先登录', '', 'error');
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
          swal(jsI18n.please_login || '请先登录', '', 'error');
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

    $('.main-image, .thumb-item img').on('error', function () {
      $(this).attr('src', '/images/b_mode/proc-placeholder.svg');
    });
  });
</script>
@endsection
