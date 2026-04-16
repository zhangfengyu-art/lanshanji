@extends('b_mode.layouts.app')
@section('title', $product->title)

@php
  $buyerName = trim((string) data_get($product, 'title', '求购者'));
  $verified = true;
  $completionRate = max(65, min(99, (int) round(((float) $product->rating) * 20 + 65)));
  $creditStars = max(3, min(5, (int) round(((float) $product->rating) ?: 4)));
  $primaryCtaUrl = route('procurement.checkout', ['product_id' => $product->id]);
  $contactUrl = route('b_mode.faq_guidelines');
@endphp

@push('styles')
<style>
  .site-mode-b .b-demand-detail {
    max-width: 980px;
    margin: 0 auto;
    padding: 14px 0 102px;
  }

  .site-mode-b .b-demand-hero {
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    box-shadow: 0 14px 34px rgba(15, 23, 42, 0.1);
  }

  .site-mode-b .b-demand-hero__media {
    position: relative;
    background: #e2e8f0;
  }

  .site-mode-b .b-demand-hero__media::before {
    content: '';
    display: block;
    padding-top: 64%;
  }

  .site-mode-b .b-demand-hero__media img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .site-mode-b .b-demand-verified {
    position: absolute;
    left: 12px;
    top: 12px;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 999px;
    background: rgba(44, 123, 229, 0.95);
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    padding: 6px 10px;
    box-shadow: 0 8px 18px rgba(44, 123, 229, 0.3);
  }

  .site-mode-b .b-demand-hero__body {
    padding: 16px;
  }

  .site-mode-b .b-demand-title {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 0.01em;
    color: var(--b-mode-text);
  }

  .site-mode-b .b-demand-budget {
    margin: 8px 0 0;
    color: var(--b-mode-action);
    font-size: 30px;
    font-weight: 800;
    letter-spacing: 0.02em;
  }

  .site-mode-b .b-demand-desc {
    margin: 10px 0 0;
    color: var(--b-mode-muted);
    font-size: 13px;
    line-height: 1.6;
  }

  .site-mode-b .b-trust-card,
  .site-mode-b .b-credit-card {
    margin-top: 12px;
    border-radius: 16px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    box-shadow: 0 14px 34px rgba(15, 23, 42, 0.08);
    padding: 16px;
  }

  .site-mode-b .b-trust-card__title,
  .site-mode-b .b-credit-card__title {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    color: var(--b-mode-primary);
    font-weight: 700;
  }

  .site-mode-b .b-trust-card__text {
    margin: 10px 0 0;
    color: #1f2937;
    line-height: 1.7;
    font-size: 14px;
  }

  .site-mode-b .b-credit-row {
    margin-top: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    font-size: 13px;
    color: #334155;
  }

  .site-mode-b .b-credit-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 11px;
    border-radius: 999px;
    background: rgba(44, 123, 229, 0.12);
    color: var(--b-mode-primary);
    font-weight: 700;
  }

  .site-mode-b .b-credit-stars {
    color: #f6ad55;
    letter-spacing: 2px;
    font-size: 14px;
  }

  .site-mode-b .b-progress {
    width: 100%;
    height: 8px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
    margin-top: 8px;
  }

  .site-mode-b .b-progress__bar {
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, var(--b-mode-primary), #60a5fa);
  }

  .site-mode-b .b-sticky-cta {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 58px;
    z-index: 1035;
    padding: 9px 12px calc(9px + env(safe-area-inset-bottom));
    background: rgba(244, 247, 251, 0.92);
    backdrop-filter: blur(8px);
    border-top: 1px solid rgba(15, 23, 42, 0.08);
  }

  .site-mode-b .b-sticky-cta__inner {
    max-width: 920px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 8px;
  }

  .site-mode-b .b-btn-contact,
  .site-mode-b .b-btn-fulfill {
    min-height: 46px;
    border-radius: 12px;
    border: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: transform .16s ease, box-shadow .16s ease, background .16s ease;
  }

  .site-mode-b .b-btn-contact {
    background: rgba(44, 123, 229, 0.1);
    color: var(--b-mode-primary);
    border: 1px solid rgba(44, 123, 229, 0.18);
  }

  .site-mode-b .b-btn-fulfill {
    background: var(--b-mode-action);
    color: #1f2937;
    box-shadow: 0 8px 18px rgba(246, 173, 85, 0.3);
  }

  .site-mode-b .b-btn-contact:hover,
  .site-mode-b .b-btn-fulfill:hover {
    transform: translateY(-1px);
    text-decoration: none;
  }

  .site-mode-b .b-btn-fulfill:hover {
    box-shadow: 0 10px 22px rgba(246, 173, 85, 0.38);
  }

  @media (max-width: 520px) {
    .site-mode-b .b-demand-detail {
      padding: 8px 0 116px;
    }

    .site-mode-b .b-demand-title {
      font-size: 20px;
    }

    .site-mode-b .b-demand-budget {
      font-size: 27px;
    }

    .site-mode-b .b-sticky-cta__inner {
      grid-template-columns: 1fr;
    }
  }
</style>
@endpush

@section('content')
<section class="b-demand-detail">
  <div class="b-demand-hero">
    <div class="b-demand-hero__media">
      <img src="{{ $product->image_url }}" alt="{{ $product->title }}">
      @if($verified)
        <span class="b-demand-verified">
          <span data-lucide="shield-check"></span>
          Verified Demand 已核验需求
        </span>
      @endif
    </div>
    <div class="b-demand-hero__body">
      <h1 class="b-demand-title">{{ $product->title }}</h1>
      <p class="b-demand-budget">JPY ¥{{ number_format($product->price, 2, '.', '') }}</p>
      <p class="b-demand-desc">{{ trim((string) $product->description) !== '' ? $product->description : '该求购任务已由平台校验基本信息，可通过协议流程安全承接。' }}</p>
    </div>
  </div>

  <div class="b-trust-card">
    <h3 class="b-trust-card__title">
      <span data-lucide="shield-check"></span>
      平台资金存管安全保障
    </h3>
    <p class="b-trust-card__text">岚山集资金托管保障：您的服务报酬已由平台存管，确认转寄后即刻结算。</p>
  </div>

  <div class="b-credit-card">
    <h3 class="b-credit-card__title">
      <span data-lucide="user-check"></span>
      求购者信用档案
    </h3>
    <div class="b-credit-row">
      <span>身份认证状态</span>
      <span class="b-credit-badge">
        <span data-lucide="user-check"></span>
        已实名认证
      </span>
    </div>
    <div class="b-credit-row">
      <span>往期任务完成度</span>
      <strong>{{ $completionRate }}%</strong>
    </div>
    <div class="b-progress" aria-label="往期任务完成度">
      <div class="b-progress__bar" style="width: {{ $completionRate }}%;"></div>
    </div>
    <div class="b-credit-row">
      <span>信用等级</span>
      <span class="b-credit-stars" aria-label="{{ $creditStars }} 星">{{ str_repeat('★', $creditStars) }}{{ str_repeat('☆', 5 - $creditStars) }}</span>
    </div>
    <div class="b-credit-row">
      <span>履约方式</span>
      <span class="b-credit-badge">
        <span data-lucide="truck"></span>
        转寄交付
      </span>
    </div>
  </div>
</section>

<div class="b-sticky-cta">
  <div class="b-sticky-cta__inner">
    <a class="b-btn-contact" id="b-contact-link" href="{{ $contactUrl }}">
      <span data-lucide="message-circle"></span>
      联系求购者 (Contact)
    </a>
    <a class="b-btn-fulfill" href="{{ $primaryCtaUrl }}">
      <span data-lucide="shield-check"></span>
      签署协议并承接任务 (Sign &amp; Fulfill)
    </a>
  </div>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var contactLink = document.getElementById('b-contact-link');
    if (!contactLink) {
      return;
    }

    contactLink.addEventListener('click', function (event) {
      event.preventDefault();
      var targetUrl = contactLink.getAttribute('href');

      if (window.swal) {
        window.swal({
          title: '正在建立安全加密连接...',
          text: '即将进入常见代购问题沟通页',
          buttons: false,
          closeOnEsc: false,
          closeOnClickOutside: false,
          icon: 'info'
        });

        setTimeout(function () {
          window.location.href = targetUrl;
        }, 900);
        return;
      }

      window.location.href = targetUrl;
    });
  });
</script>
@endpush
