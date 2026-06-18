@php
  $hallOrders = isset($procurementOrders)
    ? $procurementOrders
    : \App\Models\ProcurementOrder::query()->orderBy('created_at', 'desc')->limit(48)->get();

  $statusMeta = [
    \App\Models\ProcurementOrder::STATUS_PENDING => ['text' => '待接单', 'class' => 'is-success'],
    \App\Models\ProcurementOrder::STATUS_ACCEPTED => ['text' => '已接单', 'class' => 'is-warning'],
    \App\Models\ProcurementOrder::STATUS_SOURCING => ['text' => '采购中', 'class' => 'is-info'],
  ];
  $totalCount = $hallOrders->count();
@endphp

<section class="proc-hall-hero">
  <div class="proc-hall-hero__row">
    <div>
      <p class="proc-hall-hero__eyebrow">岚山集 · 互助代购</p>
      <h1 class="proc-hall-hero__title">互助代购大厅</h1>
      <p class="proc-hall-hero__sub">浏览最新跨境求购需求，按预算承接代购任务。所有交易受平台协议与资金托管约束。</p>
      <div class="proc-hall-hero__stats">
        <span class="proc-hall-stat"><strong>{{ $totalCount }}</strong>条在架求购</span>
        <span class="proc-hall-stat"><strong>安全</strong>昵称脱敏展示</span>
        <span class="proc-hall-stat"><strong>实时</strong>预算日元标注</span>
      </div>
    </div>
    <a class="proc-post-btn" href="{{ route('procurement.create') }}">
      <span class="glyphicon glyphicon-plus" aria-hidden="true"></span>
      发起求购
    </a>
  </div>
  @if(session('success'))
    <div class="alert alert-success proc-hall-alert">
      {{ session('success') }}
      @if(auth()->check())
        <a href="{{ route('orders.index') }}" class="alert-link" style="margin-left: 8px; color: #fff; text-decoration: underline;">查看我的求购</a>
      @endif
    </div>
  @endif
</section>

@if($hallOrders->count())
  <div class="proc-grid">
    @foreach($hallOrders as $index => $order)
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
        $statusLabel = isset($statusMeta[$status]) ? $statusMeta[$status] : ['text' => '待接单', 'class' => 'is-success'];
        $category = trim((string) data_get($order, 'extra.category', data_get($order, 'extra.reference_category', '')));
        $buyUrl = (string) data_get($order, 'buy_url', route('products.index', ['search' => (string) $order->item_name]));
      @endphp

      <article class="proc-card proc-card--text" style="--b-stagger: {{ $index }};">
        <div class="proc-body">
          <div class="proc-meta">
            <span class="proc-nickname">{{ $maskedNickname }}</span>
            <span class="proc-tags">
              @if($category !== '')
                <span class="proc-tag is-category">{{ $category }}</span>
              @endif
              @if($isDemoData)
                <span class="proc-tag is-demo">演示</span>
              @endif
              <span class="proc-tag {{ $statusLabel['class'] }}">{{ $statusLabel['text'] }}</span>
            </span>
          </div>
          <h2 class="proc-title">{{ $order->item_name }}</h2>
          <p class="proc-budget"><small>JPY</small> ¥{{ number_format((float) $order->budget_amount, 0) }}</p>
          <p class="proc-desc">{{ $order->order_narrative }}</p>
          <div class="proc-actions">
            <a class="proc-buy-btn" href="{{ $buyUrl }}">我能带</a>
          </div>
        </div>
      </article>
    @endforeach
  </div>
@else
  <div class="proc-empty">
    <p style="margin: 0 0 8px; font-size: 16px; font-weight: 700; color: var(--b-text);">暂无求购信息</p>
    <p style="margin: 0;">成为第一个发布需求的人，或稍后再来看看。</p>
    <a class="proc-post-btn" href="{{ route('procurement.create') }}" style="margin-top: 16px;">立即发起求购</a>
  </div>
@endif
