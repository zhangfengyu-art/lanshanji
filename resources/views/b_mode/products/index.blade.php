@extends('b_mode.layouts.app')
@section('title', '岚山集 - 互助广场')

@php
  $bModePlaceholder = asset('images/b_mode/proc-placeholder.svg');
  $hallOrdersRaw = isset($procurementOrders)
    ? $procurementOrders
    : \App\Models\ProcurementOrder::query()->orderBy('created_at', 'desc')->limit(48)->get();
  $totalHallOrders = $hallOrdersRaw->count();
  $hallOrders = $hallOrdersRaw->take(6);
  $activeFlowCount = $hallOrdersRaw->whereIn('proxy_status', [
    \App\Models\ProcurementOrder::STATUS_PENDING,
    \App\Models\ProcurementOrder::STATUS_ACCEPTED,
    \App\Models\ProcurementOrder::STATUS_SOURCING,
  ])->count();
  $todayPublishedCount = $hallOrdersRaw->filter(function ($row) {
    $createdAt = data_get($row, 'created_at');
    if ($createdAt instanceof \Carbon\Carbon || $createdAt instanceof \Carbon\CarbonInterface) {
      return $createdAt->isToday();
    }
    if (!$createdAt) {
      return false;
    }
    try {
      return \Carbon\Carbon::parse($createdAt)->isToday();
    } catch (\Throwable $e) {
      return false;
    }
  })->count();

  $urgencyMap = [
    \App\Models\ProcurementOrder::STATUS_PENDING => ['text' => '高优先', 'class' => 'is-high'],
    \App\Models\ProcurementOrder::STATUS_ACCEPTED => ['text' => '处理中', 'class' => 'is-mid'],
    \App\Models\ProcurementOrder::STATUS_SOURCING => ['text' => '执行中', 'class' => 'is-low'],
  ];
@endphp

@push('styles')
<style>
  .site-mode-b .b-hall {
    padding: 26px 0 24px;
  }

  .site-mode-b .b-hall-header {
    max-width: 1120px;
    margin: 0 auto 22px;
    padding: 22px 24px;
    border-radius: 16px;
    border: 1px solid rgba(44, 123, 229, 0.12);
    background: linear-gradient(145deg, #ffffff 0%, #f7faff 100%);
  }

  .site-mode-b .b-hero {
    max-width: 1240px;
    margin: 0 auto 14px;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: linear-gradient(135deg, #1c4fa3 0%, #245fc7 44%, #3475de 100%);
    position: relative;
    min-height: 234px;
  }

  .site-mode-b .b-hero::after {
    content: '';
    position: absolute;
    right: -40px;
    bottom: -40px;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.12);
  }

  .site-mode-b .b-hero__content {
    position: relative;
    z-index: 1;
    padding: 28px 28px 22px;
    color: #eaf3ff;
  }

  .site-mode-b .b-hero__title {
    margin: 0;
    font-size: 46px;
    font-weight: 800;
    line-height: 1.05;
    letter-spacing: 0.01em;
  }

  .site-mode-b .b-hero__sub {
    margin: 10px 0 0;
    font-size: 18px;
    color: rgba(234, 243, 255, 0.92);
    line-height: 1.5;
  }

  .site-mode-b .b-hero__tags {
    margin-top: 14px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .site-mode-b .b-hero__tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border-radius: 999px;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 700;
    color: #eaf3ff;
    background: rgba(255, 255, 255, 0.15);
  }

  .site-mode-b .b-guide-cards {
    max-width: 1240px;
    margin: 0 auto 16px;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
  }

  .site-mode-b .b-announcement {
    max-width: 1240px;
    margin: 0 auto 14px;
    border-radius: 14px;
    border: 1px solid rgba(44, 123, 229, 0.14);
    background: #fff;
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
    overflow: hidden;
  }

  .site-mode-b .b-announcement__inner {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    white-space: nowrap;
    overflow: hidden;
  }

  .site-mode-b .b-announcement__badge {
    border-radius: 999px;
    padding: 4px 9px;
    font-size: 11px;
    font-weight: 700;
    color: #fff;
    background: #2c7be5;
    flex-shrink: 0;
  }

  .site-mode-b .b-announcement__track {
    display: flex;
    gap: 24px;
    min-width: 100%;
    animation: bAnnouncementMove 16s linear infinite;
  }

  .site-mode-b .b-announcement__item {
    color: #334155;
    font-size: 13px;
    font-weight: 600;
  }

  @keyframes bAnnouncementMove {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
  }

  .site-mode-b .b-guide-card {
    border-radius: 12px;
    min-height: 88px;
    padding: 12px;
    color: #fff;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12);
  }

  .site-mode-b .b-guide-card:hover,
  .site-mode-b .b-guide-card:focus {
    text-decoration: none;
    color: #fff;
    transform: translateY(-1px);
  }

  .site-mode-b .b-guide-card.is-blue { background: linear-gradient(135deg, #4aa6ef, #2c7be5); }
  .site-mode-b .b-guide-card.is-purple { background: linear-gradient(135deg, #8f89db, #7f7fd1); }
  .site-mode-b .b-guide-card.is-teal { background: linear-gradient(135deg, #5ab7cd, #3a9cb5); }
  .site-mode-b .b-guide-card.is-green { background: linear-gradient(135deg, #6fbf75, #5ba861); }

  .site-mode-b .b-guide-card__title {
    margin: 0;
    font-size: 20px;
    font-weight: 800;
    letter-spacing: 0.01em;
  }

  .site-mode-b .b-guide-card__desc {
    margin: 0;
    font-size: 12px;
    line-height: 1.4;
    color: rgba(255, 255, 255, 0.92);
  }

  .site-mode-b .b-hall-header__meta {
    margin-top: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .site-mode-b .b-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 999px;
    padding: 6px 11px;
    font-size: 12px;
    font-weight: 700;
    color: #1e3a8a;
    background: rgba(44, 123, 229, 0.12);
    border: 1px solid rgba(44, 123, 229, 0.2);
  }

  .site-mode-b .b-chip.is-soft {
    color: #334155;
    background: #f8fafc;
    border-color: rgba(15, 23, 42, 0.08);
  }

  .site-mode-b .b-hall-actions {
    margin-top: 12px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .site-mode-b .b-hall-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 10px;
    min-height: 38px;
    padding: 0 12px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
  }

  .site-mode-b .b-hall-action.is-primary {
    background: #f6ad55;
    color: #1f2937;
    box-shadow: 0 8px 16px rgba(246, 173, 85, 0.3);
  }

  .site-mode-b .b-hall-action.is-ghost {
    border: 1px solid rgba(44, 123, 229, 0.22);
    background: #fff;
    color: #1e3a8a;
  }

  .site-mode-b .b-hall-action:hover,
  .site-mode-b .b-hall-action:focus {
    text-decoration: none;
    transform: translateY(-1px);
  }

  .site-mode-b .b-hall-header__title {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    letter-spacing: 0.01em;
  }

  .site-mode-b .b-hall-header__sub {
    margin: 6px 0 0;
    color: var(--b-mode-muted);
    font-size: 13px;
  }

  .site-mode-b .b-trust-strip {
    max-width: 1240px;
    margin: 0 auto 16px;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
  }

  .site-mode-b .b-trust-card {
    border-radius: 14px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
    padding: 12px 13px;
  }

  .site-mode-b .b-trust-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
  }

  .site-mode-b .b-trust-card__title {
    margin: 0;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
  }

  .site-mode-b .b-trust-card__value {
    font-size: 22px;
    font-weight: 800;
    color: #1d4ed8;
    line-height: 1;
    letter-spacing: 0.01em;
  }

  .site-mode-b .b-trust-card__desc {
    margin: 0;
    font-size: 12px;
    color: #64748b;
    line-height: 1.6;
  }

  .site-mode-b .b-flow-strip {
    max-width: 1240px;
    margin: 0 auto 18px;
    border-radius: 14px;
    border: 1px solid rgba(44, 123, 229, 0.15);
    background: linear-gradient(135deg, #ffffff 0%, #f7fbff 100%);
    padding: 12px;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
  }

  .site-mode-b .b-flow-item {
    border-radius: 10px;
    border: 1px dashed rgba(44, 123, 229, 0.22);
    padding: 9px 10px;
    background: rgba(255, 255, 255, 0.9);
  }

  .site-mode-b .b-flow-item__step {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 999px;
    background: #2c7be5;
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    margin-bottom: 6px;
  }

  .site-mode-b .b-flow-item__title {
    margin: 0;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
  }

  .site-mode-b .b-flow-item__desc {
    margin: 4px 0 0;
    font-size: 12px;
    color: #64748b;
    line-height: 1.5;
  }

  .site-mode-b .b-topic-strip {
    max-width: 1240px;
    margin: 0 auto 18px;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
  }

  .site-mode-b .b-topic-card {
    border-radius: 14px;
    min-height: 102px;
    padding: 14px;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    color: #fff;
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.1);
  }

  .site-mode-b .b-topic-card:hover,
  .site-mode-b .b-topic-card:focus {
    color: #fff;
    text-decoration: none;
    transform: translateY(-1px);
  }

  .site-mode-b .b-topic-card.is-sky { background: linear-gradient(135deg, #7fc3ff 0%, #58a8f2 100%); }
  .site-mode-b .b-topic-card.is-violet { background: linear-gradient(135deg, #cac2ef 0%, #a79ae5 100%); }
  .site-mode-b .b-topic-card.is-mint { background: linear-gradient(135deg, #b6dde3 0%, #8ccfd8 100%); }
  .site-mode-b .b-topic-card.is-slate { background: linear-gradient(135deg, #d1d1e5 0%, #babada 100%); }

  .site-mode-b .b-topic-card__copy {
    min-width: 0;
  }

  .site-mode-b .b-topic-card__title {
    margin: 0;
    font-size: 19px;
    font-weight: 800;
    letter-spacing: 0.01em;
  }

  .site-mode-b .b-topic-card__desc {
    margin: 6px 0 0;
    font-size: 12px;
    line-height: 1.5;
    color: rgba(255, 255, 255, 0.92);
  }

  .site-mode-b .b-topic-card__icon {
    width: 58px;
    height: 58px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.18);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2);
  }

  .site-mode-b .b-topic-card__icon img {
    width: 40px;
    height: 40px;
    object-fit: contain;
  }

  .site-mode-b .b-hall-grid {
    max-width: 1240px;
    margin: 0 auto;
    display: grid;
    gap: 18px;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    align-items: start;
  }

  .site-mode-b .b-hall-card {
    display: flex;
    flex-direction: column;
    border-radius: 16px;
    border: 1px solid rgba(15, 23, 42, 0.07);
    background: #fff;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
    overflow: hidden;
    min-height: 320px;
    transition: transform .16s ease, box-shadow .18s ease;
  }

  .site-mode-b .b-hall-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 16px 32px rgba(15, 23, 42, 0.09);
  }

  .site-mode-b .b-hall-card__head {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 8px;
    padding: 14px 16px 10px;
  }

  .site-mode-b .b-hall-user {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
  }

  .site-mode-b .b-hall-avatar {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    background: rgba(44, 123, 229, 0.14);
    color: #2c7be5;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
  }

  .site-mode-b .b-hall-user__meta {
    min-width: 0;
  }

  .site-mode-b .b-hall-user__name {
    margin: 0;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .site-mode-b .b-hall-user__time {
    margin: 0;
    font-size: 11px;
    color: var(--b-mode-muted);
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .site-mode-b .b-hall-media {
    position: relative;
    display: block;
    width: calc(100% - 32px);
    margin: 0 auto;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
    background: #eef3fb;
  }

  .site-mode-b .b-hall-media::before {
    content: '';
    display: block;
    padding-top: 56%;
  }

  .site-mode-b .b-hall-media img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .site-mode-b .b-hall-body {
    padding: 14px 16px 4px;
  }

  .site-mode-b .b-hall-title {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    line-height: 1.35;
    letter-spacing: 0.01em;
  }

  .site-mode-b .b-hall-desc {
    margin: 8px 0 0;
    font-size: 12px;
    line-height: 1.6;
    color: var(--b-mode-muted);
    min-height: 40px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .site-mode-b .b-hall-foot {
    margin-top: auto;
    padding: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
  }

  .site-mode-b .b-hall-budget {
    margin: 0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #1e3a8a;
    font-weight: 700;
    letter-spacing: 0.01em;
    border-radius: 999px;
    padding: 6px 12px;
    background: rgba(44, 123, 229, 0.12);
  }

  .site-mode-b .b-hall-budget small {
    display: inline;
    margin-top: 0;
    font-size: 11px;
    color: var(--b-mode-muted);
    font-weight: 600;
    letter-spacing: 0;
  }

  .site-mode-b .b-hall-fulfill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-width: 118px;
    padding: 9px 12px;
    border-radius: 10px;
    background: #f6ad55;
    color: #1f2937;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: transform 0.12s ease, box-shadow 0.18s ease, filter 0.18s ease;
    box-shadow: 0 6px 14px rgba(246, 173, 85, 0.35);
  }

  .site-mode-b .b-hall-fulfill:hover,
  .site-mode-b .b-hall-fulfill:focus {
    color: #111827;
    text-decoration: none;
    filter: brightness(1.02);
  }

  .site-mode-b .b-hall-fulfill.is-pressed {
    transform: scale(0.97);
    box-shadow: 0 3px 8px rgba(246, 173, 85, 0.26);
  }

  .site-mode-b .b-hall-empty {
    max-width: 1120px;
    margin: 8px auto 0;
    padding: 26px 18px;
  }

  .site-mode-b .b-hall-more {
    max-width: 1240px;
    margin: 22px auto 0;
    display: flex;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  .site-mode-b .b-hall-more a {
    border-radius: 999px;
    border: 1px solid rgba(44, 123, 229, 0.22);
    background: rgba(255, 255, 255, 0.72);
    color: #1e3a8a;
    font-size: 13px;
    font-weight: 600;
    padding: 8px 16px;
    text-decoration: none;
  }

  .site-mode-b .b-hall-more__text {
    align-self: center;
    font-size: 12px;
    color: #64748b;
  }

  .site-mode-b .b-hall-more a:hover,
  .site-mode-b .b-hall-more a:focus {
    text-decoration: none;
    color: #1d4ed8;
    background: #fff;
  }

  .site-mode-b .b-hall-empty img {
    width: 120px;
    max-width: 48%;
    margin-bottom: 10px;
    opacity: 0.9;
  }

  @media (max-width: 1180px) {
    .site-mode-b .b-hall-grid {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }

  @media (max-width: 940px) {
    .site-mode-b .b-hero__title {
      font-size: 34px;
    }

    .site-mode-b .b-hero__sub {
      font-size: 15px;
    }

    .site-mode-b .b-guide-cards {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .site-mode-b .b-topic-strip {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .site-mode-b .b-announcement__track {
      animation-duration: 20s;
    }

    .site-mode-b .b-trust-strip,
    .site-mode-b .b-flow-strip {
      grid-template-columns: 1fr;
    }

    .site-mode-b .b-hall-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 16px;
    }
  }

  @media (max-width: 560px) {
    .site-mode-b .b-hall {
      padding-top: 12px;
    }

    .site-mode-b .b-hero {
      min-height: 186px;
    }

    .site-mode-b .b-hero__content {
      padding: 18px 14px;
    }

    .site-mode-b .b-hero__title {
      font-size: 26px;
    }

    .site-mode-b .b-guide-cards {
      grid-template-columns: 1fr;
      gap: 8px;
    }

    .site-mode-b .b-topic-strip {
      grid-template-columns: 1fr;
      gap: 8px;
    }

    .site-mode-b .b-announcement__inner {
      padding: 8px 10px;
    }

    .site-mode-b .b-announcement__track {
      gap: 16px;
      animation-duration: 24s;
    }

    .site-mode-b .b-hall-header {
      padding: 14px 12px;
      margin-bottom: 14px;
    }

    .site-mode-b .b-hall-header__title {
      font-size: 20px;
    }

    .site-mode-b .b-hall-grid {
      grid-template-columns: 1fr;
      gap: 14px;
    }
  }
</style>
@endpush

@section('content')
<section class="b-hall">
  <section class="b-announcement" aria-label="平台公告滚动条">
    <div class="b-announcement__inner">
      <span class="b-announcement__badge">公告</span>
      <div class="b-announcement__track">
        <span class="b-announcement__item">新用户首单任务可享托管服务费减免</span>
        <span class="b-announcement__item">任务发布后请补充清晰需求，有助于更快承接</span>
        <span class="b-announcement__item">确认签收后系统自动进入结算流程</span>
        <span class="b-announcement__item">平台支持资金托管、转寄履约与争议处理</span>
      </div>
      <div class="b-announcement__track" aria-hidden="true">
        <span class="b-announcement__item">新用户首单任务可享托管服务费减免</span>
        <span class="b-announcement__item">任务发布后请补充清晰需求，有助于更快承接</span>
        <span class="b-announcement__item">确认签收后系统自动进入结算流程</span>
        <span class="b-announcement__item">平台支持资金托管、转寄履约与争议处理</span>
      </div>
    </div>
  </section>

  <section class="b-hero" aria-label="平台主视觉">
    <div class="b-hero__content">
      <h1 class="b-hero__title">代购委托 · 可信履约</h1>
      <p class="b-hero__sub">发布需求、托管支付、承接履约、签收结算，一站式完成跨境代购流程。</p>
      <div class="b-hero__tags">
        <span class="b-hero__tag"><span data-lucide="shield-check"></span>托管保障</span>
        <span class="b-hero__tag"><span data-lucide="clock-3"></span>节点透明</span>
        <span class="b-hero__tag"><span data-lucide="truck"></span>转寄履约</span>
      </div>
    </div>
  </section>

  <section class="b-guide-cards" aria-label="功能入口">
    <a class="b-guide-card is-blue" href="{{ route('procurement.create') }}">
      <h3 class="b-guide-card__title">发布需求</h3>
      <p class="b-guide-card__desc">快速发起代购委托并进入托管流程</p>
    </a>
    <a class="b-guide-card is-purple" href="{{ route('products.index') }}">
      <h3 class="b-guide-card__title">任务广场</h3>
      <p class="b-guide-card__desc">实时浏览可承接任务与预算信息</p>
    </a>
    <a class="b-guide-card is-teal" href="{{ route('orders.index') }}">
      <h3 class="b-guide-card__title">履约中心</h3>
      <p class="b-guide-card__desc">查看任务状态、进度与结算节点</p>
    </a>
    <a class="b-guide-card is-green" href="{{ route('b_mode.faq_guidelines') }}">
      <h3 class="b-guide-card__title">帮助中心</h3>
      <p class="b-guide-card__desc">规则说明、争议处理与服务边界</p>
    </a>
  </section>

  <section class="b-topic-strip" aria-label="专题推荐">
    <a class="b-topic-card is-sky" href="{{ route('procurement.create') }}">
      <div class="b-topic-card__copy">
        <h3 class="b-topic-card__title">新人福利</h3>
        <p class="b-topic-card__desc">首次发布享托管服务费减免</p>
      </div>
      <span class="b-topic-card__icon"><span data-lucide="gift"></span></span>
    </a>

    <a class="b-topic-card is-violet" href="{{ route('orders.index') }}">
      <div class="b-topic-card__copy">
        <h3 class="b-topic-card__title">履约追踪</h3>
        <p class="b-topic-card__desc">查看任务进展与结算状态</p>
      </div>
      <span class="b-topic-card__icon"><span data-lucide="map-pinned"></span></span>
    </a>

    <a class="b-topic-card is-mint" href="{{ route('b_mode.faq_guidelines') }}">
      <div class="b-topic-card__copy">
        <h3 class="b-topic-card__title">规则说明</h3>
        <p class="b-topic-card__desc">了解服务边界与争议处理</p>
      </div>
      <span class="b-topic-card__icon"><span data-lucide="book-open"></span></span>
    </a>

    <a class="b-topic-card is-slate" href="{{ route('support.feedbacks.create') }}">
      <div class="b-topic-card__copy">
        <h3 class="b-topic-card__title">联系平台</h3>
        <p class="b-topic-card__desc">提交工单，获取人工支持</p>
      </div>
      <span class="b-topic-card__icon"><span data-lucide="message-circle"></span></span>
    </a>
  </section>

  <div class="b-hall-header">
    <h2 class="b-hall-header__title">岚山集 - 跨境互助广场</h2>
    <p class="b-hall-header__sub">轻量互助社区，默认只展示精选任务，减少干扰，聚焦真实需求。</p>
    <div class="b-hall-header__meta">
      <span class="b-chip"><span data-lucide="shield-check"></span>已核验需求</span>
      <span class="b-chip is-soft"><span data-lucide="clock-3"></span>实时更新</span>
      <span class="b-chip is-soft"><span data-lucide="package-search"></span>当前 {{ $totalHallOrders }} 条任务</span>
    </div>
    <div class="b-hall-actions">
      <a class="b-hall-action is-primary" href="{{ route('procurement.create') }}">
        <span data-lucide="plus-circle"></span>
        发起求购
      </a>
      <a class="b-hall-action is-ghost" href="{{ route('orders.index') }}">
        <span data-lucide="list-checks"></span>
        查看我的任务
      </a>
    </div>
    @if(session('success'))
      <div class="alert alert-success" style="margin:10px 0 0;">
        {{ session('success') }}
      </div>
    @endif
  </div>

  <section class="b-trust-strip" aria-label="平台可信度">
    <article class="b-trust-card">
      <div class="b-trust-card__top">
        <h3 class="b-trust-card__title">实时任务池</h3>
        <span data-lucide="activity"></span>
      </div>
      <div class="b-trust-card__value">{{ $totalHallOrders }}</div>
      <p class="b-trust-card__desc">公开委托任务实时展示，支持按状态持续跟踪。</p>
    </article>

    <article class="b-trust-card">
      <div class="b-trust-card__top">
        <h3 class="b-trust-card__title">履约进行中</h3>
        <span data-lucide="shield-check"></span>
      </div>
      <div class="b-trust-card__value">{{ $activeFlowCount }}</div>
      <p class="b-trust-card__desc">任务节点透明记录，托管支付与履约状态同步。</p>
    </article>

    <article class="b-trust-card">
      <div class="b-trust-card__top">
        <h3 class="b-trust-card__title">今日新增</h3>
        <span data-lucide="sparkles"></span>
      </div>
      <div class="b-trust-card__value">{{ $todayPublishedCount }}</div>
      <p class="b-trust-card__desc">新增需求持续进入广场，供承接者快速筛选。</p>
    </article>
  </section>

  <section class="b-flow-strip" aria-label="履约流程">
    <article class="b-flow-item">
      <span class="b-flow-item__step">1</span>
      <h3 class="b-flow-item__title">发布并托管</h3>
      <p class="b-flow-item__desc">需求发布后进入托管支付流程，避免线下风险。</p>
    </article>

    <article class="b-flow-item">
      <span class="b-flow-item__step">2</span>
      <h3 class="b-flow-item__title">承接与履约</h3>
      <p class="b-flow-item__desc">承接者按任务要求采购并提交履约节点信息。</p>
    </article>

    <article class="b-flow-item">
      <span class="b-flow-item__step">3</span>
      <h3 class="b-flow-item__title">转寄与结算</h3>
      <p class="b-flow-item__desc">确认签收后触发结算，形成可追踪闭环。</p>
    </article>
  </section>

  @if($hallOrders->count())
    <div class="b-hall-grid">
      @foreach($hallOrders as $order)
        @php
          $nickname = trim((string) data_get($order, 'buyer_nickname', '匿名用户'));
          $len = function_exists('mb_strlen') ? mb_strlen($nickname) : strlen($nickname);
          if ($len >= 3) {
            $first = function_exists('mb_substr') ? mb_substr($nickname, 0, 1) : substr($nickname, 0, 1);
            $last = function_exists('mb_substr') ? mb_substr($nickname, -1) : substr($nickname, -1);
            $maskedNickname = $first . '**' . $last;
          } elseif ($len === 2) {
            $first = function_exists('mb_substr') ? mb_substr($nickname, 0, 1) : substr($nickname, 0, 1);
            $maskedNickname = $first . '*';
          } else {
            $maskedNickname = $nickname !== '' ? '*' : '匿名用户';
          }

          $avatarChar = function_exists('mb_substr') ? mb_substr($maskedNickname, 0, 1) : substr($maskedNickname, 0, 1);
          $avatarChar = $avatarChar ?: '匿';

          $status = (int) data_get($order, 'proxy_status', \App\Models\ProcurementOrder::STATUS_PENDING);
          $urgency = $urgencyMap[$status] ?? ['text' => '高优先', 'class' => 'is-high'];

          $createdAt = data_get($order, 'created_at');
          $timeLabel = '刚刚发起';
          if ($createdAt instanceof \Carbon\Carbon || $createdAt instanceof \Carbon\CarbonInterface) {
            $timeLabel = $createdAt->diffForHumans();
          } elseif (!empty($createdAt)) {
            try {
              $timeLabel = \Carbon\Carbon::parse($createdAt)->diffForHumans();
            } catch (\Throwable $e) {
              $timeLabel = '刚刚发起';
            }
          }

          $imageSrc = $bModePlaceholder;

          $buyUrl = (string) data_get($order, 'buy_url', route('products.index', ['search' => (string) data_get($order, 'item_name', '')]));
        @endphp

        <article class="b-hall-card">
          <header class="b-hall-card__head">
            <div class="b-hall-user">
              <span class="b-hall-avatar">{{ $avatarChar }}</span>
              <div class="b-hall-user__meta">
                <p class="b-hall-user__name">{{ $maskedNickname }}</p>
                <p class="b-hall-user__time">
                  <span data-lucide="clock-3"></span>
                  {{ $timeLabel }} · {{ $urgency['text'] }}
                </p>
              </div>
            </div>
          </header>

          <a class="b-hall-media" href="{{ $buyUrl }}">
            <img src="{{ $imageSrc }}" alt="{{ data_get($order, 'item_name', '求购任务') }}" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='{{ $bModePlaceholder }}';">
          </a>

          <div class="b-hall-body">
            <h3 class="b-hall-title">{{ data_get($order, 'item_name', '未命名任务') }}</h3>
            <p class="b-hall-desc">{{ \Illuminate\Support\Str::limit((string) data_get($order, 'order_narrative', '等待补充任务说明。'), 64) }}</p>
          </div>

          <footer class="b-hall-foot">
            <p class="b-hall-budget">
              JPY ¥{{ number_format((float) data_get($order, 'budget_amount', 0), 0) }}
              <small>预算</small>
            </p>
            <a class="b-hall-fulfill" data-fulfill-btn href="{{ $buyUrl }}">
              <span data-lucide="shield-check"></span>
              我能带 (Fulfill)
            </a>
          </footer>
        </article>
      @endforeach
    </div>
    @if($totalHallOrders > $hallOrders->count())
      <div class="b-hall-more">
        <span class="b-hall-more__text">仅展示精选任务</span>
        <a href="{{ route('orders.index') }}">查看更多任务</a>
      </div>
    @endif
  @else
    <div class="b-hall-empty b-empty-state">
      <img src="{{ asset('images/b_mode/empty-hall.svg') }}" alt="暂无任务">
      <p style="margin:0 0 8px;">当前还没有新的求购任务。</p>
      <a class="btn btn-action" href="{{ route('procurement.create') }}">马上发起求购</a>
    </div>
  @endif
</section>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var buttons = document.querySelectorAll('[data-fulfill-btn]');
    buttons.forEach(function (btn) {
      btn.addEventListener('pointerdown', function () {
        btn.classList.add('is-pressed');
      });
      btn.addEventListener('pointerup', function () {
        btn.classList.remove('is-pressed');
      });
      btn.addEventListener('pointerleave', function () {
        btn.classList.remove('is-pressed');
      });
    });
  });
</script>
@endpush
