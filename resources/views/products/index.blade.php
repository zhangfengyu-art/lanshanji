@extends('layouts.app')
@section('title', '商品列表')

@section('content')
@if(is_site_mode_b())
@php
  $bModePlaceholder = asset('images/b_mode/proc-placeholder.svg');
  $hallOrders = isset($procurementOrders)
    ? $procurementOrders
    : \App\Models\ProcurementOrder::query()->orderBy('created_at', 'desc')->limit(48)->get();
  $referenceGallery = isset($referenceGallery) ? $referenceGallery : collect();

  $statusMeta = [
    \App\Models\ProcurementOrder::STATUS_PENDING => ['text' => 'Pending', 'class' => 'is-success'],
    \App\Models\ProcurementOrder::STATUS_ACCEPTED => ['text' => 'Accepted', 'class' => 'is-warning'],
    \App\Models\ProcurementOrder::STATUS_SOURCING => ['text' => 'Processing', 'class' => 'is-info'],
  ];
@endphp

<style>
  .proc-hall-header {
    margin: 12px 0 18px;
    padding: 20px;
    border: 1px solid #e5e7eb;
    background:
      radial-gradient(circle at top right, rgba(44, 123, 229, 0.10), transparent 30%),
      linear-gradient(135deg, #ffffff 0%, #f6f8fc 100%);
    border-radius: 18px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
  }
  .proc-hall-title {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    color: #111827;
    letter-spacing: 0.02em;
  }
  .proc-hall-sub {
    margin: 8px 0 0;
    color: #6b7280;
    font-size: 13px;
  }
  .proc-hall-head-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
  }
  .proc-hero {
    display: grid;
    grid-template-columns: 1.2fr .8fr;
    gap: 14px;
    margin-top: 16px;
  }
  .proc-hero-panel {
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    background: #ffffff;
    padding: 16px;
  }
  .proc-hero-panel--accent {
    background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
    color: #fff;
    border-color: transparent;
  }
  .proc-hero-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #6b7280;
    margin-bottom: 10px;
  }
  .proc-hero-panel--accent .proc-hero-kicker {
    color: rgba(255,255,255,0.72);
  }
  .proc-hero-heading {
    margin: 0 0 10px;
    font-size: 26px;
    line-height: 1.2;
    font-weight: 800;
    color: inherit;
  }
  .proc-hero-text {
    margin: 0;
    font-size: 13px;
    line-height: 1.75;
    color: inherit;
    opacity: .82;
  }
  .proc-hero-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-top: 14px;
  }
  .proc-stat {
    border-radius: 14px;
    border: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.06);
    padding: 12px;
  }
  .proc-stat-label {
    font-size: 11px;
    color: rgba(255,255,255,0.72);
    margin-bottom: 6px;
  }
  .proc-stat-value {
    font-size: 18px;
    font-weight: 800;
    color: #ffffff;
  }
  .proc-gallery {
    margin-top: 16px;
  }
  .proc-gallery-head {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
  }
  .proc-gallery-title {
    margin: 0;
    font-size: 18px;
    font-weight: 800;
    color: #111827;
  }
  .proc-gallery-sub {
    margin: 4px 0 0;
    color: #6b7280;
    font-size: 12px;
  }
  .proc-gallery-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
  }
  .proc-gallery-card {
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    background: #fff;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
  }
  .proc-gallery-media {
    position: relative;
    height: 140px;
    background: #f3f4f6;
    overflow: hidden;
  }
  .proc-gallery-media img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .proc-gallery-body {
    padding: 10px 12px 12px;
  }
  .proc-gallery-name {
    margin: 0 0 6px;
    font-size: 15px;
    font-weight: 700;
    color: #111827;
    line-height: 1.35;
    min-height: 40px;
  }
  .proc-gallery-meta {
    margin: 0;
    font-size: 12px;
    color: #6b7280;
    line-height: 1.55;
  }
  .proc-gallery-price {
    margin: 8px 0 0;
    font-size: 18px;
    font-weight: 800;
    color: #111827;
  }
  .proc-post-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px 14px;
    border-radius: 10px;
    background: #b45309;
    color: #fff;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
    box-shadow: 0 6px 14px rgba(180, 83, 9, 0.22);
  }
  .proc-post-btn:hover,
  .proc-post-btn:focus {
    color: #fff;
    text-decoration: none;
    opacity: 0.92;
  }
  .proc-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
  }
  .proc-card {
    margin: 0;
    border: 1px solid #e5e7eb;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(17, 24, 39, 0.04);
    display: flex;
    flex-direction: column;
    min-height: 330px;
  }
  .proc-media {
    position: relative;
    width: 100%;
    height: 170px;
    overflow: hidden;
    background: #f3f4f6;
  }
  .proc-media img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
  }
  .proc-body {
    padding: 10px 10px 12px;
    display: flex;
    flex-direction: column;
    flex: 1;
  }
  .proc-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
  }
  .proc-nickname {
    font-weight: 600;
    color: #1f2937;
    font-size: 13px;
  }
  .proc-budget {
    margin: 8px 0 6px;
    font-size: 22px;
    font-weight: 700;
    color: #111827;
    line-height: 1.1;
  }
  .proc-desc {
    margin: 0;
    color: #4b5563;
    font-size: 12px;
    line-height: 1.4;
    min-height: 34px;
  }
  .proc-title {
    margin: 0;
    font-size: 18px;
    line-height: 1.2;
    color: #111827;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .proc-tag {
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.7;
  }
  .proc-tag.is-success {
    background: #e8f8ef;
    color: #0f7a3f;
  }
  .proc-tag.is-warning {
    background: #fff4e5;
    color: #b45309;
  }
  .proc-tag.is-info {
    background: #e8f1ff;
    color: #1d4ed8;
  }
  .proc-tag.is-demo {
    background: #f3f4f6;
    color: #374151;
  }
  .proc-tag.is-ref {
    background: #eef2ff;
    color: #3730a3;
  }
  .proc-empty {
    border: 1px dashed #d1d5db;
    border-radius: 12px;
    padding: 28px;
    text-align: center;
    color: #6b7280;
    background: #fafafa;
  }
  .proc-actions {
    margin-top: auto;
    padding-top: 10px;
  }
  .proc-buy-btn {
    display: block;
    width: 100%;
    text-align: center;
    padding: 8px 12px;
    border-radius: 8px;
    background: #111827;
    color: #ffffff;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
  }
  .proc-buy-btn:hover,
  .proc-buy-btn:focus {
    color: #ffffff;
    text-decoration: none;
    opacity: 0.92;
  }

  @media (max-width: 1200px) {
    .proc-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  }
  @media (max-width: 900px) {
    .proc-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 600px) {
    .proc-grid { grid-template-columns: 1fr; }
    .proc-hall-title { font-size: 20px; }
    .proc-hall-head-row { flex-direction: column; }
    .proc-post-btn { width: 100%; }
    .proc-hero { grid-template-columns: 1fr; }
    .proc-hero-stats { grid-template-columns: 1fr; }
    .proc-gallery-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="proc-hall-header">
  <div class="proc-hall-head-row">
    <div>
      <h2 class="proc-hall-title">B 站代购互助广场</h2>
      <p class="proc-hall-sub">素材库精选 + 真实求购流，首页直接展示代购语境里的商品案例。</p>
    </div>
    <a class="proc-post-btn" href="{{ route('procurement.create') }}">我也要发起求购</a>
  </div>
  <div class="proc-hero">
    <div class="proc-hero-panel proc-hero-panel--accent">
      <div class="proc-hero-kicker">Shadow Gallery</div>
      <h3 class="proc-hero-heading">把首页做成“真实代购素材 + 求购订单”双层代购互助广场</h3>
      <p class="proc-hero-text">上半层是可搜索的素材库精选，下半层是最新求购卡片。你在后台录入的商品名、图片和重量，会直接反映到首页首屏。</p>
      <div class="proc-hero-stats">
        <div class="proc-stat">
          <div class="proc-stat-label">精选素材</div>
          <div class="proc-stat-value">{{ (int) $referenceGallery->count() }}</div>
        </div>
        <div class="proc-stat">
          <div class="proc-stat-label">最新求购</div>
          <div class="proc-stat-value">{{ (int) $hallOrders->count() }}</div>
        </div>
        <div class="proc-stat">
          <div class="proc-stat-label">首屏风格</div>
          <div class="proc-stat-value">日系极简</div>
        </div>
      </div>
    </div>
    <div class="proc-hero-panel">
      <div class="proc-hero-kicker">费用结构</div>
      <h3 class="proc-hero-heading" style="font-size:20px;">商品价 + 劳务费 + 国际快递费</h3>
      <p class="proc-hero-text">影子单会优先匹配价格落在预算 80% - 92% 的素材，再把差额拆成可解释的劳务费与快递费。</p>
    </div>
  </div>

  @if($referenceGallery->count())
    <div class="proc-gallery">
      <div class="proc-gallery-head">
        <div>
          <h3 class="proc-gallery-title">代购素材精选</h3>
          <p class="proc-gallery-sub">后台导入后会出现在这里，刷新首页就能看到真实商品案例。</p>
        </div>
      </div>
      <div class="proc-gallery-grid">
        @foreach($referenceGallery as $galleryItem)
          @php
            $galleryImage = (string) data_get($galleryItem, 'image_url', '');
            $galleryImageLower = strtolower($galleryImage);
            $isBlockedGalleryImage = $galleryImage !== '' && (
              strpos($galleryImageLower, 'brand-logo') !== false ||
              strpos($galleryImageLower, 'yanwusuo') !== false ||
              strpos($galleryImageLower, '%E5%B2%9A%E5%B1%B1') !== false ||
              strpos($galleryImageLower, 'cigarette') !== false ||
              strpos($galleryImageLower, 'tobacco') !== false
            );
            if ($galleryImage === '' || $isBlockedGalleryImage) {
              $gallerySrc = $bModePlaceholder;
            } elseif (\Illuminate\Support\Str::startsWith($galleryImage, ['http://', 'https://', '//'])) {
              $gallerySrc = $galleryImage;
            } elseif (\Illuminate\Support\Str::startsWith($galleryImage, '/')) {
              $gallerySrc = url($galleryImage);
            } elseif (\Illuminate\Support\Str::startsWith($galleryImage, 'storage/')) {
              $gallerySrc = asset($galleryImage);
            } elseif (\Illuminate\Support\Str::startsWith($galleryImage, 'references/')) {
              $gallerySrc = asset('storage/' . $galleryImage);
            } else {
              $gallerySrc = asset($galleryImage);
            }
            $galleryCategory = data_get($galleryItem, 'category.name', '未分类');
          @endphp
          <article class="proc-gallery-card">
            <div class="proc-gallery-media">
              <img src="{{ $gallerySrc }}" alt="{{ $galleryItem->item_name }}" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='{{ $bModePlaceholder }}';">
            </div>
            <div class="proc-gallery-body">
              <h4 class="proc-gallery-name">{{ $galleryItem->item_name }}</h4>
              <p class="proc-gallery-meta">{{ $galleryCategory }} · 预估重量 {{ $galleryItem->weight_estimate !== null ? number_format((float) $galleryItem->weight_estimate, 2, '.', '') . ' kg' : '未录入' }}</p>
              <p class="proc-gallery-price">JPY ¥{{ number_format((float) $galleryItem->reference_price, 0) }}</p>
            </div>
          </article>
        @endforeach
      </div>
    </div>
  @endif

  @if(session('success'))
    <div class="alert alert-success" style="margin-top: 10px; margin-bottom: 0;">
      {{ session('success') }}
      @if(auth()->check())
        <a href="{{ route('orders.index') }}" class="alert-link" style="margin-left: 8px;">查看我的求购</a>
      @endif
    </div>
  @endif
</div>

@if($hallOrders->count())
  <div class="proc-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;align-items:start;">
    @foreach($hallOrders as $order)
      @php
        $nickname = (string) $order->buyer_nickname;
        $nicknameLength = function_exists('mb_strlen') ? mb_strlen($nickname) : strlen($nickname);
        if ($nicknameLength >= 3) {
          $first = function_exists('mb_substr') ? mb_substr($nickname, 0, 1) : substr($nickname, 0, 1);
          $last = function_exists('mb_substr') ? mb_substr($nickname, -1) : substr($nickname, -1);
          $maskedNickname = $first . '**' . $last;
        } elseif ($nicknameLength === 2) {
          $first = function_exists('mb_substr') ? mb_substr($nickname, 0, 1) : substr($nickname, 0, 1);
          $maskedNickname = $first . '*';
        } else {
          $maskedNickname = $nickname !== '' ? '*' : '匿名用户';
        }

        $status = (int) $order->proxy_status;
        $statusLabel = isset($statusMeta[$status]) ? $statusMeta[$status] : ['text' => 'Pending', 'class' => 'is-success'];
        $isDemoData = (bool) data_get($order, 'is_mock', false) || (bool) data_get($order, 'extra.is_demo_data', false);
        $isReferenceSeed = (bool) data_get($order, 'extra.is_reference_seed', false);
        $referenceStrategy = (string) data_get($order, 'extra.reference_strategy', '');
        $pricingSnapshot = data_get($order, 'extra.pricing_snapshot', []);
        $referenceSnapshot = data_get($order, 'extra.reference_snapshot', []);
        $imageRaw = (string) data_get($order, 'item_image', '');
        $imageRawLower = strtolower($imageRaw);
        $isBlockedOrderImage = $imageRaw !== '' && (
          strpos($imageRawLower, 'brand-logo') !== false ||
          strpos($imageRawLower, 'yanwusuo') !== false ||
          strpos($imageRawLower, '%E5%B2%9A%E5%B1%B1') !== false ||
          strpos($imageRawLower, 'cigarette') !== false ||
          strpos($imageRawLower, 'tobacco') !== false
        );
        if ($imageRaw === '' || $isBlockedOrderImage) {
          $imageSrc = $bModePlaceholder;
        } elseif (\Illuminate\Support\Str::startsWith($imageRaw, ['http://', 'https://', '//'])) {
          $imageSrc = $imageRaw;
        } elseif (\Illuminate\Support\Str::startsWith($imageRaw, '/')) {
          $imageSrc = url($imageRaw);
        } elseif (\Illuminate\Support\Str::startsWith($imageRaw, 'storage/')) {
          $imageSrc = asset($imageRaw);
        } elseif (\Illuminate\Support\Str::startsWith($imageRaw, 'references/')) {
          $imageSrc = asset('storage/' . $imageRaw);
        } else {
          $imageSrc = asset($imageRaw);
        }
        $buyUrl = (string) data_get($order, 'buy_url', route('products.index', ['search' => (string) $order->item_name]));
      @endphp

      <article class="proc-card" style="display:flex;flex-direction:column;min-height:330px;width:100%;max-width:100%;">
        <div class="proc-media" style="position:relative;height:170px;max-height:170px;overflow:hidden;background:#f3f4f6;">
          <img src="{{ $imageSrc }}" alt="{{ $order->item_name }}" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='{{ $bModePlaceholder }}';" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;max-width:none;">
        </div>
        <div class="proc-body">
          <div class="proc-meta">
            <span class="proc-nickname">{{ $maskedNickname }}</span>
            <span>
              @if($isReferenceSeed)
                <span class="proc-tag is-ref">参考商品</span>
              @endif
              @if($referenceStrategy !== '')
                <span class="proc-tag is-info">{{ $referenceStrategy === 'range_match' ? '素材库命中' : '最近价格' }}</span>
              @endif
              @if($isDemoData)
                <span class="proc-tag is-demo">测试数据</span>
              @endif
              <span class="proc-tag {{ $statusLabel['class'] }}">{{ $statusLabel['text'] }}</span>
            </span>
          </div>
          <h4 class="proc-title">{{ $order->item_name }}</h4>
          @if(!empty(data_get($referenceSnapshot, 'item_name')))
            <p class="proc-desc" style="min-height:auto;color:#6b7280;">素材：{{ data_get($referenceSnapshot, 'item_name') }}</p>
          @endif
          <p class="proc-budget">JPY ¥{{ number_format((float) $order->budget_amount, 0) }}</p>
          @if(!empty(data_get($pricingSnapshot, 'gap_amount')))
            <p class="proc-desc" style="min-height:auto;">
              商品价 ¥{{ number_format((float) data_get($pricingSnapshot, 'item_amount', $order->budget_amount), 2, '.', '') }}
              + 劳务/运费 ¥{{ number_format((float) data_get($pricingSnapshot, 'gap_amount', 0), 2, '.', '') }}
            </p>
          @endif
          <p class="proc-desc">{{ $order->order_narrative }}</p>
          <div class="proc-actions">
            <a class="proc-buy-btn" href="{{ $buyUrl }}">我能带</a>
          </div>
        </div>
      </article>
    @endforeach
  </div>
@else
  <div class="proc-empty">当前暂无求购信息，稍后将展示最新代购需求。</div>
@endif

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
              $subtitle = data_get($product, 'jp_title') ?: data_get($product, 'title_jp') ?: data_get($product, 'name_jp') ?: trans('frontend.product.subtitle');
              $isDepleted = $product->inventory_status === 'DEPLETED';
              $isLimited = $product->inventory_status === 'LIMITED';
              $limitQty = (int) ($product->limit_qty ?: optional($defaultSku)->limit_qty);
              $skuDescriptionRaw = trim((string) optional($defaultSku)->description);
              $skuDescription = $skuDescriptionRaw !== '' ? \Illuminate\Support\Str::limit($skuDescriptionRaw, 60) : '';
              $categoryPath = trim((string) $product->mapped_category_path);
              if ($categoryPath === '') {
                $categoryPath = $subtitle;
              }
                $actionText = ($product->skus->count() > 1) ? '加入购物车' : trans('frontend.product.add_to_cart');
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
                <p class="product-category-path">{{ $categoryPath }}</p>
                <h4 class="product-title {{ $isDepleted ? 'is-depleted' : '' }}">
                  <a href="{{ route('products.show', ['product' => $product->id]) }}">{{ $product->title }}</a>
                </h4>
                <div class="product-rating" aria-label="SKU描述">{{ $skuDescription !== '' ? $skuDescription : ' ' }}</div>
                <div class="product-price {{ $isDepleted ? 'is-depleted' : '' }}">{{ number_format($product->price, 2, '.', '') }}日元</div>
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
    $(document).ready(function () {
      $('.search-form input[name=search]').val(filters.search);
      $('.search-form select[name=order]').val(filters.order);

      $('.search-form select[name=order]').on('change', function() {
        $('.search-form').submit();
      });

      $('[data-add-cart]').on('click', function () {
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
        }).then(function () {
          if (window.MiniCart && window.MiniCart.refresh) {
            window.MiniCart.refresh();
          }
          if (window.MiniCart && window.MiniCart.toast) {
            window.MiniCart.toast('{{ trans('frontend.js.added_to_cart') }}');
          }
        }).catch(function (error) {
          if (error.response && (error.response.status === 401 || error.response.status === 403)) {
            location.href = '{{ route('login') }}';
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
