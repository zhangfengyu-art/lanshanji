@php
  $marketing = $bSiteMarketing ?? [];
  $stats = $marketing['stats'] ?? [];
  $recentDeals = collect($marketing['recent_deals'] ?? []);
  $recentRequests = collect($marketing['recent_requests'] ?? []);

  $statusLabels = \App\Models\ProcurementOrder::$statusMap ?? [];
@endphp

<section class="b-marketing" aria-label="平台动态与优惠">
  <div class="b-promo-ticker" aria-hidden="true">
    <div class="b-promo-ticker__track">
      <span>春季跨境代购季 · 新用户发起求购享优先撮合</span>
      <span>资金托管全程可查 · 支付成功即锁定预算</span>
      <span>本周已有 {{ number_format((int) ($stats['week_deals'] ?? 0)) }} 笔订单完成托管支付</span>
      <span>热门品类：日烟限量 · 药妆护肤 · 动漫周边 · 零食礼盒</span>
      <span>春季跨境代购季 · 新用户发起求购享优先撮合</span>
    </div>
  </div>

  <div class="b-stats-grid">
    <article class="b-stat-card">
      <p class="b-stat-card__label">在架求购</p>
      <p class="b-stat-card__value">{{ number_format((int) ($stats['on_shelf'] ?? 0)) }}</p>
      <p class="b-stat-card__hint">待接单 {{ number_format((int) ($stats['pending_requests'] ?? 0)) }} 条</p>
    </article>
    <article class="b-stat-card is-accent">
      <p class="b-stat-card__label">今日成交</p>
      <p class="b-stat-card__value">{{ number_format((int) ($stats['today_deals'] ?? 0)) }}</p>
      <p class="b-stat-card__hint">托管支付已确认</p>
    </article>
    <article class="b-stat-card">
      <p class="b-stat-card__label">本周撮合</p>
      <p class="b-stat-card__value">{{ number_format((int) ($stats['week_deals'] ?? 0)) }}</p>
      <p class="b-stat-card__hint">近 7 日完成订单</p>
    </article>
    <article class="b-stat-card">
      <p class="b-stat-card__label">活跃代购师</p>
      <p class="b-stat-card__value">{{ number_format((int) ($stats['active_agents'] ?? 0)) }}</p>
      <p class="b-stat-card__hint">累计成交 {{ number_format((int) ($stats['completed_deals'] ?? 0)) }} 笔</p>
    </article>
  </div>

  <div class="b-promo-row">
    <a class="b-promo-card b-promo-card--primary" href="{{ route('procurement.create') }}">
      <strong>发起求购</strong>
      <span>填写预算与需求，平台自动匹配可承接代购师</span>
    </a>
    <div class="b-promo-card b-promo-card--trust">
      <strong>资金托管保障</strong>
      <span>支付进入托管账户，履约确认后结算，降低双方风险</span>
    </div>
    <div class="b-promo-card b-promo-card--hot">
      <strong>本周热门</strong>
      <span>日烟限量 · Peace 铁盒 · 七星系列 · EMS 直邮拼单</span>
    </div>
  </div>

  <div class="b-marketing-split">
    <section class="b-activity-panel">
      <header class="b-activity-panel__head">
        <h2>实时交易动态</h2>
        <span class="b-live-dot" aria-hidden="true"></span>
        <span class="b-activity-panel__badge">更新中</span>
      </header>
      <ul class="b-activity-list">
        @forelse($recentDeals as $deal)
          <li>
            <span class="b-activity-list__time">{{ optional($deal->paid_at)->format('m-d H:i') }}</span>
            <span class="b-activity-list__text">订单 {{ \Illuminate\Support\Str::limit((string) $deal->no, 14, '…') }} 已完成托管支付</span>
            <span class="b-activity-list__amount">¥{{ number_format((float) $deal->total_amount, 2) }}</span>
          </li>
        @empty
          <li>
            <span class="b-activity-list__time">--</span>
            <span class="b-activity-list__text">今日已有买家完成跨境托管支付，欢迎发起求购</span>
            <span class="b-activity-list__amount">托管</span>
          </li>
        @endforelse
      </ul>
    </section>

    <section class="b-activity-panel">
      <header class="b-activity-panel__head">
        <h2>最新求购需求</h2>
      </header>
      <ul class="b-activity-list">
        @forelse($recentRequests as $req)
          <li>
            <span class="b-activity-list__time">{{ optional($req->created_at)->format('m-d H:i') }}</span>
            <span class="b-activity-list__text">{{ \Illuminate\Support\Str::limit((string) $req->item_name, 28) }}</span>
            <span class="b-activity-list__amount">¥{{ number_format((float) $req->budget_amount, 0) }}</span>
          </li>
        @empty
          <li>
            <span class="b-activity-list__time">--</span>
            <span class="b-activity-list__text">暂无求购，成为第一个发布需求的人</span>
            <span class="b-activity-list__amount">JPY</span>
          </li>
        @endforelse
      </ul>
    </section>
  </div>

  <div class="b-hot-tags" aria-label="热门搜索">
    <span class="b-hot-tags__label">热门搜索</span>
  @foreach(['七星黑标', 'Peace 铁盒', '药妆面膜', '动漫手办', '限定零食', '保温杯', '相机配件'] as $tag)
    <a class="b-hot-tag" href="{{ route('products.index', ['search' => $tag]) }}">{{ $tag }}</a>
  @endforeach
  </div>
</section>
