@extends('layouts.app')
@section('title', '求购需求详情')

@section('content')
@php
  $imageRaw = (string) ($itemImage ?? '');
  if ($imageRaw === '') {
    $imageSrc = asset('images/default.png');
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

  $recommendations = isset($recommendedProducts) ? $recommendedProducts : collect();
  $match = isset($matchedProduct) ? $matchedProduct : null;
  $isLoggedIn = auth()->check();
@endphp

<link rel="stylesheet" href="{{ asset('css/procurement-detail.css') }}?v=20260411c">

<style>
  :root {
    --proc-bg: #f7f7f5;
    --proc-card: #ffffff;
    --proc-ink: #20222a;
    --proc-muted: #6e717c;
    --proc-line: #e6e7eb;
    --proc-accent: #c77829;
    --proc-accent-soft: #f6efe6;
    --proc-shadow: 0 10px 30px rgba(21, 24, 31, 0.06);
    --proc-radius-lg: 18px;
    --proc-radius-md: 12px;
  }

  .proc-page {
    position: relative;
    background:
      radial-gradient(circle at 8% 10%, rgba(199, 120, 41, 0.08), transparent 32%),
      radial-gradient(circle at 90% 0%, rgba(88, 113, 138, 0.08), transparent 30%),
      linear-gradient(180deg, #fafaf9 0%, var(--proc-bg) 56%, #f4f4f1 100%);
    margin: -20px -15px -30px;
    padding: 28px 0 38px;
  }

  .proc-detail-wrap {
    max-width: 1160px;
    margin: 0 auto;
    padding: 0 18px;
    font-family: "Avenir Next", "Hiragino Kaku Gothic ProN", "Noto Sans JP", "Yu Gothic", sans-serif;
    color: var(--proc-ink);
  }

  .proc-topline {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    color: var(--proc-muted);
    font-size: 12px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .proc-divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--proc-line), transparent);
    margin-bottom: 18px;
  }

  .proc-detail-card {
    border: 1px solid var(--proc-line);
    border-radius: var(--proc-radius-lg);
    overflow: hidden;
    background: var(--proc-card);
    box-shadow: var(--proc-shadow);
    display: grid;
    grid-template-columns: minmax(300px, 430px) 1fr;
    animation: procFadeUp 0.45s ease both;
  }

  .proc-detail-media {
    width: 100%;
    height: 420px;
    background: #eceef0;
    overflow: hidden;
    border-right: 1px solid var(--proc-line);
  }

  .proc-detail-media img {
    width: 100% !important;
    height: 100% !important;
    max-width: none !important;
    object-fit: cover !important;
    display: block !important;
    transform: scale(1.01);
  }

  .proc-detail-body {
    padding: 22px 24px 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .proc-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    border: 1px solid #edd9bf;
    background: var(--proc-accent-soft);
    color: #8f5a24;
    font-size: 12px;
    font-weight: 600;
    width: fit-content;
  }

  .proc-detail-title {
    margin: 2px 0 0;
    font-family: "Hiragino Mincho ProN", "Noto Serif JP", "Yu Mincho", serif;
    font-size: clamp(28px, 3.2vw, 42px);
    line-height: 1.15;
    letter-spacing: 0.03em;
    color: #1b1d24;
  }

  .proc-detail-budget {
    margin: 0;
    color: #1f2228;
    font-size: clamp(24px, 2.8vw, 36px);
    font-weight: 700;
    line-height: 1.12;
  }

  .proc-detail-desc {
    margin: 0;
    color: var(--proc-muted);
    line-height: 1.78;
    font-size: 14px;
  }

  .proc-meta-grid {
    margin-top: 2px;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }

  .proc-meta-item {
    background: #fafaf9;
    border: 1px solid var(--proc-line);
    border-radius: var(--proc-radius-md);
    padding: 11px 12px;
  }

  .proc-meta-label {
    font-size: 11px;
    color: var(--proc-muted);
    margin-bottom: 5px;
    letter-spacing: 0.06em;
  }

  .proc-meta-value {
    font-size: 14px;
    font-weight: 600;
    color: #20222a;
  }

  .proc-detail-actions {
    margin-top: 2px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }

  .proc-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 15px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    transition: all .2s ease;
    border: 1px solid transparent;
  }

  .proc-btn-primary {
    background: linear-gradient(180deg, #1f242e 0%, #11151d 100%);
    color: #fff;
    box-shadow: 0 8px 16px rgba(23, 27, 34, 0.2);
  }

  .proc-btn-primary:hover,
  .proc-btn-primary:focus {
    color: #fff;
    text-decoration: none;
    transform: translateY(-1px);
  }

  .proc-btn-light {
    background: #fff;
    color: #1f2937;
    border-color: #dadde4;
  }

  .proc-btn-light:hover,
  .proc-btn-light:focus {
    color: #1f2937;
    text-decoration: none;
    background: #f6f7f8;
  }

  .proc-reco {
    margin-top: 20px;
    border: 1px solid var(--proc-line);
    border-radius: var(--proc-radius-lg);
    background: var(--proc-card);
    box-shadow: var(--proc-shadow);
    padding: 16px;
    animation: procFadeUp 0.55s ease both;
    animation-delay: .08s;
  }

  .proc-reco-title {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: #1f2026;
  }

  .proc-reco-subtitle {
    margin: 6px 0 14px;
    color: var(--proc-muted);
    font-size: 13px;
  }

  .proc-reco-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
  }

  .proc-reco-card {
    border: 1px solid #e8e9ed;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    display: flex;
    flex-direction: column;
    transition: transform .22s ease, box-shadow .22s ease;
  }

  .proc-reco-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 18px rgba(22, 26, 32, 0.09);
  }

  .proc-reco-media {
    height: 156px;
    background: #f1f3f6;
    overflow: hidden;
  }

  .proc-reco-media img {
    width: 100% !important;
    height: 100% !important;
    max-width: none !important;
    object-fit: cover !important;
    display: block !important;
  }

  .proc-reco-body {
    padding: 11px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex: 1;
  }

  .proc-reco-name {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #20222b;
    line-height: 1.35;
    min-height: 44px;
    max-height: 44px;
    overflow: hidden;
  }

  .proc-reco-sub {
    margin: 0;
    color: #6d7280;
    font-size: 12px;
    line-height: 1.45;
    min-height: 34px;
  }

  .proc-reco-price {
    margin: 2px 0 8px;
    color: #1f222a;
    font-weight: 700;
    font-size: 20px;
    line-height: 1.2;
  }

  .proc-reco-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: auto;
  }

  .proc-reco-btn {
    border: 1px solid transparent;
    border-radius: 8px;
    padding: 7px 10px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .proc-reco-btn-dark {
    background: #111827;
    color: #fff;
  }

  .proc-reco-btn-dark:hover,
  .proc-reco-btn-dark:focus {
    color: #fff;
    text-decoration: none;
    opacity: 0.92;
  }

  .proc-reco-btn-light {
    background: #f4f5f7;
    border-color: #dfe2e8;
    color: #1f2937;
  }

  .proc-reco-btn-light:hover,
  .proc-reco-btn-light:focus {
    color: #1f2937;
    text-decoration: none;
    background: #eceef2;
  }

  .proc-empty {
    border: 1px dashed #d5d7de;
    border-radius: 10px;
    padding: 15px;
    color: #666b76;
    font-size: 13px;
    background: #fafaf9;
  }

  @keyframes procFadeUp {
    from {
      opacity: 0;
      transform: translateY(8px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  @media (max-width: 980px) {
    .proc-detail-card {
      grid-template-columns: 1fr;
    }
    .proc-detail-media {
      height: 280px;
      border-right: 0;
      border-bottom: 1px solid var(--proc-line);
    }
    .proc-reco-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 640px) {
    .proc-page {
      margin: -18px -15px -22px;
      padding: 16px 0 24px;
    }
    .proc-detail-wrap {
      padding: 0 12px;
    }
    .proc-topline {
      font-size: 11px;
      margin-bottom: 10px;
    }
    .proc-detail-body {
      padding: 14px;
    }
    .proc-meta-grid {
      grid-template-columns: 1fr;
    }
    .proc-reco-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="proc-page">
  <div class="proc-detail-wrap">
    <div class="proc-topline">
      <span>Demand Details</span>
      <span>{{ now()->format('Y.m.d') }}</span>
    </div>
    <div class="proc-divider"></div>

    <div class="proc-detail-card">
      <div class="proc-detail-media" style="width:100%;height:420px;max-height:420px;overflow:hidden;">
        <img src="{{ $imageSrc }}" alt="{{ $itemName }}" loading="lazy" decoding="async" style="width:100% !important;height:100% !important;max-width:none !important;object-fit:cover !important;display:block !important;">
      </div>
      <div class="proc-detail-body">
        <span class="proc-badge">C2C 求购需求</span>
        <h1 class="proc-detail-title">{{ $itemName }}</h1>
        <p class="proc-detail-budget">JPY ¥{{ number_format((float) $budgetAmount, 0) }}</p>
        @if(!empty($nativeRequest))
          <p class="proc-detail-desc">这是首页发布的原生代购委托，预算金额即为商品金额，不进行 SKU 匹配。</p>
        @endif
        <p class="proc-detail-desc">{{ $narrative !== '' ? $narrative : '该求购需求正在等待匹配商品。' }}</p>

        <div class="proc-meta-grid">
          <div class="proc-meta-item">
            <div class="proc-meta-label">需求分类</div>
            <div class="proc-meta-value">{{ data_get($procurementMeta ?? [], 'category', '未指定') }}</div>
          </div>
          <div class="proc-meta-item">
            <div class="proc-meta-label">匹配状态</div>
            <div class="proc-meta-value">{{ data_get($procurementMeta ?? [], 'has_match', false) ? '已匹配可购买商品' : '暂未精准匹配' }}</div>
          </div>
        </div>

        <div class="proc-detail-actions">
          @if(!empty($nativeRequest))
            <p class="proc-detail-desc" style="margin-bottom:12px;">您是代购人：接单后由求购方预付托管，您无需在本站付款。</p>
          @endif
          <a class="proc-btn proc-btn-primary" href="{{ route('products.index') }}">返回求购大厅接单</a>
          <a class="proc-btn proc-btn-light" href="{{ route('products.index', ['search' => $itemName]) }}">去商品列表搜索</a>
          @if($match)
            <a class="proc-btn proc-btn-light" href="{{ route('products.show', ['product' => $match->id]) }}">查看匹配商品详情</a>
          @endif
          <a class="proc-btn proc-btn-light" href="{{ route('products.index') }}">返回求购大厅</a>
        </div>
      </div>
    </div>

    <section class="proc-reco">
      <h2 class="proc-reco-title">{{ !empty($nativeRequest) ? '原生求购说明' : '可承接任务商品推荐' }}</h2>
      <p class="proc-reco-subtitle">
        {{ !empty($nativeRequest) ? '该单为首页原生求购，不调用 SKU 匹配，只展示委托信息。' : '基于当前求购关键词与预算自动匹配，你可以先确认可承接的代购任务，再进入资金预付。' }}
      </p>

      @if(!empty($nativeRequest))
        <div class="proc-empty">这是原生求购委托单，预算金额即商品金额，不拆分 SKU、不做商品推荐。</div>
      @elseif($recommendations->count())
        <div class="proc-reco-grid">
          @foreach($recommendations as $product)
            @php
              $sku = $product->skus->first();
              $priceMin = $product->skus->min('price');
              $priceMax = $product->skus->max('price');
              $categoryPath = trim((optional(optional($product->category)->parent)->name ? optional(optional($product->category)->parent)->name . ' / ' : '') . (optional($product->category)->name ?: '未分类'));
            @endphp
            <article class="proc-reco-card">
              <div class="proc-reco-media">
                <img src="{{ $product->image_url }}" alt="{{ $product->title }}" loading="lazy" decoding="async">
              </div>
              <div class="proc-reco-body">
                <h3 class="proc-reco-name">{{ $product->title }}</h3>
                <p class="proc-reco-sub">{{ $categoryPath }} | 销量 {{ (int) $product->sold_count }}</p>
                <p class="proc-reco-price">
                  @if($priceMin !== null)
                    JPY ¥{{ number_format((float) $priceMin, 0) }}@if($priceMax !== null && (float) $priceMax > (float) $priceMin)-¥{{ number_format((float) $priceMax, 0) }}@endif
                  @else
                    价格待定
                  @endif
                </p>
                <div class="proc-reco-actions">
                  <a class="proc-reco-btn proc-reco-btn-light" href="{{ route('products.show', ['product' => $product->id]) }}">查看商品详情</a>
                  <a class="proc-reco-btn proc-reco-btn-dark" href="{{ route('products.index') }}">去大厅接单</a>
                </div>
              </div>
            </article>
          @endforeach
        </div>
      @else
        <div class="proc-empty">暂未检索到可直接购买的商品。你可以先去商品列表搜索关键词“{{ $itemName }}”查看更多商品。</div>
      @endif
    </section>
  </div>
</div>
@endsection
