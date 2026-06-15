@extends('layouts.app')
@section('title', is_site_mode_b() ? '互助代购大厅' : '商品列表')

@section('content')
@if(is_site_mode_b())
@include('products._b_site_marketing')
@include('products._procurement_hall')
@else
<div class="row">
  <div class="col-md-12">
    <nav class="smoke-breadcrumbs" aria-label="Breadcrumb">
      <a href="{{ route('root') }}">{{ trans('frontend.common.home') }}</a>
      <span class="sep">/</span>
      @if($breadcrumbParent || $breadcrumbChild)
        <a href="{{ route('products.index') }}">{{ trans('frontend.common.product_list') }}</a>
      @else
        <span class="current">{{ trans('frontend.common.product_list') }}</span>
      @endif

      @if($breadcrumbParent)
        <span class="sep">/</span>
        @if($breadcrumbChild)
          <a href="{{ route('products.index', ['category' => $breadcrumbParent->id]) }}">{{ $breadcrumbParent->name }}</a>
        @else
          <span class="current">{{ $breadcrumbParent->name }}</span>
        @endif
      @endif

      @if($breadcrumbChild)
        <span class="sep">/</span>
        <span class="current">{{ $breadcrumbChild->name }}</span>
      @endif
    </nav>

    <!-- 右侧商品展示区 -->
    <div class="panel panel-default products-panel">
      <div class="panel-body">
        <div class="row products-list">
          @foreach($products as $product)
          <div class="col-xs-6 col-sm-4 col-md-3 product-item">
            @php
              $defaultSku = $product->skus->first();
              $isDepleted = $product->inventory_status === 'DEPLETED';
              $isLimited = $product->inventory_status === 'LIMITED';
              $limitQty = (int) ($product->limit_qty ?: optional($defaultSku)->limit_qty);
              $category = $product->category;
              $categoryParentName = optional(optional($category)->parent)->name;
              $categoryName = optional($category)->name;
              $skuDescriptionRaw = trim((string) optional($defaultSku)->description);
              $skuDescription = $skuDescriptionRaw !== '' ? \Illuminate\Support\Str::limit($skuDescriptionRaw, 60) : '';
              $categoryPath = trim(($categoryParentName ? $categoryParentName.' / ' : '').($categoryName ?: ''));
              $actionText = ($product->skus->count() > 1) ? trans('frontend.product.select_sku') : trans('frontend.product.add_to_cart');
            @endphp
            <div class="product-card {{ $isDepleted ? 'is-depleted' : '' }}">
              <div class="product-card-media {{ $isDepleted ? 'is-depleted' : '' }}">
                <a class="product-card-media-link" href="{{ route('products.show', ['product' => $product->id]) }}">
                  <img src="{{ $product->image_url }}" alt="{{ $product->title }}">
                </a>
                @if($isLimited && !$isDepleted)
                  <div class="limited-badge">{{ trans('frontend.product.limited_badge') }}</div>
                @endif
                @if($isDepleted)
                  <div class="sold-out-overlay"><span>{{ trans('frontend.product.sold_out') }}</span></div>
                @endif
              </div>
              <div class="product-card-body">
                <div class="product-card-text">
                  @if($categoryPath !== '')
                    <p class="product-category-path">{{ $categoryPath }}</p>
                  @endif
                  <h4 class="product-title {{ $isDepleted ? 'is-depleted' : '' }}">
                  <a href="{{ route('products.show', ['product' => $product->id]) }}">{{ $product->title }}</a>
                </h4>
                  <div class="product-rating{{ $skuDescription === '' ? ' is-empty' : '' }}" aria-label="SKU描述">{{ $skuDescription }}</div>
                </div>
                <div class="product-card-commerce">
                <div class="product-price {{ $isDepleted ? 'is-depleted' : '' }}">{{ format_shop_price($product->price) }}</div>
                @if($isLimited && $limitQty > 0)
                  <p class="limit-note">{{ trans('frontend.product.limit_per_order', ['count' => $limitQty]) }}</p>
                @else
                  <p class="limit-note is-placeholder">&nbsp;</p>
                @endif
                  <button type="button" class="btn btn-add-cart-block {{ $isDepleted ? 'is-depleted' : '' }}" data-add-cart data-sku-id="{{ optional($defaultSku)->id }}" data-sku-amount="1" @if(!$defaultSku || $isDepleted) disabled @endif>
                    {{ $isDepleted ? trans('frontend.product.sold_out') : $actionText }}
                  </button>
                </div>
              </div>
            </div>
          </div>
          @endforeach
        </div>
        <div class="pull-right">{{ $products->appends($filters)->render() }}</div>
      </div>
    </div>
  </div>
</div>
@endif
@endsection

@section('scriptsAfterJs')
  @if(!is_site_mode_b())
  <script>
    var filters = {!! json_encode($filters) !!};
    var isLoggedIn = @json(auth()->check());
    var loginUrl = '{{ route('login') }}';
    $(document).ready(function () {
      $('.search-form input[name=search]').val(filters.search);
      $('.search-form select[name=order]').val(filters.order);

      $('.search-form select[name=order]').on('change', function() {
        $('.search-form').submit();
      });

      $('[data-add-cart]').on('click', function () {
        if (!isLoggedIn) {
          window.promptLoginToShop(loginUrl);
          return;
        }

        var skuId = $(this).data('sku-id');
        var amount = $(this).data('sku-amount') || 1;

        if (!skuId) {
          if (window.MiniCart && window.MiniCart.toast) {
            window.MiniCart.toast('{{ trans('frontend.js.spec_unavailable') }}');
          }
          return;
        }

        axios.post('{{ route('cart.add') }}', {
          sku_id: skuId,
          amount: amount
        }).then(function (res) {
          if (window.MiniCart) {
            if (window.MiniCart.setCount && res.data && typeof res.data.count !== 'undefined') {
              window.MiniCart.setCount(res.data.count);
            } else if (window.MiniCart.refresh) {
              window.MiniCart.refresh();
            }
          }
          if (window.MiniCart && window.MiniCart.toast) {
            window.MiniCart.toast('{{ trans('frontend.js.added_to_cart') }}');
          }
        }).catch(function (error) {
          if (error.response && (error.response.status === 401 || error.response.status === 403)) {
            window.promptLoginToShop(loginUrl);
            return;
          }
          if (error.response && error.response.status === 400 && error.response.data && error.response.data.msg) {
            if (window.MiniCart && window.MiniCart.toast) {
              window.MiniCart.toast(error.response.data.msg);
            }
            if (error.response.data.msg.indexOf('验证邮箱') !== -1) {
              setTimeout(function () {
                location.href = '{{ route('email_verify_notice') }}';
              }, 600);
            }
            return;
          }
          if (error.response && error.response.status === 422 && error.response.data && error.response.data.errors) {
            var firstError = Object.values(error.response.data.errors)[0];
            var message = Array.isArray(firstError) ? firstError[0] : '{{ trans('frontend.js.add_failed') }}';
            if (window.MiniCart && window.MiniCart.toast) {
              window.MiniCart.toast(message);
            }
            return;
          }
          if (window.MiniCart && window.MiniCart.toast) {
            window.MiniCart.toast('{{ trans('frontend.js.add_failed_retry') }}');
          }
        });
      });
    });
  </script>
  @endif
@endsection
