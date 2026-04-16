@php
  $routeName = (string) optional(request()->route())->getName();
  $isActive = function ($patterns) use ($routeName) {
      foreach ((array) $patterns as $pattern) {
          if ($pattern !== '' && strpos($routeName, $pattern) === 0) {
              return true;
          }
      }
      return false;
  };
@endphp

<header class="b-lite-header">
  <div class="b-lite-promo" role="note" aria-label="平台公告">
    <div class="b-lite-promo__inner">
      <span class="b-lite-promo__badge">公告</span>
      <span class="b-lite-promo__text">新用户首单任务托管服务费减免，详情见任务规则页。</span>
      <a class="b-lite-promo__link" href="{{ route('b_mode.faq_guidelines') }}">查看规则</a>
    </div>
  </div>

  <div class="b-lite-header__inner">
    <div class="b-lite-header__bar b-lite-header__bar--main">
      <a class="b-lite-header__brand" href="{{ route('products.index') }}">
        <span class="b-lite-header__dot" aria-hidden="true"></span>
        <span>岚山集</span>
        <small>代购互助广场</small>
      </a>

      <div class="b-lite-header__utilities">
        <span class="b-lite-header__utility-chip">中文 / CNY</span>
        <a class="b-lite-header__utility-link" href="{{ route('b_mode.faq_guidelines') }}">帮助中心</a>
        @auth
          <a class="b-lite-header__utility-link" href="{{ route('orders.index') }}">{{ str_limit(Auth::user()->name, 12) }}</a>
          <a class="b-lite-header__utility-link" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('b-lite-logout-form').submit();">退出</a>
          <form id="b-lite-logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">
            {{ csrf_field() }}
          </form>
        @else
          <a class="b-lite-header__utility-link" href="{{ route('register') }}">注册</a>
          <a class="b-lite-header__utility-link" href="{{ route('login') }}">登录</a>
        @endauth
      </div>
    </div>

    <div class="b-lite-header__bar b-lite-header__bar--search">
      <form class="b-lite-header__search" action="{{ route('products.index') }}" method="GET">
        <select class="b-lite-header__search-kind" name="kind" aria-label="任务类型">
          <option value="task">任务</option>
          <option value="goods">商品</option>
        </select>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="请输入任务关键词或商品名称">
        <button type="submit" aria-label="搜索">
          <span data-lucide="search"></span>
          <span>去匹配</span>
        </button>
      </form>
      <a class="b-lite-header__search-link" href="{{ route('b_mode.faq_guidelines') }}">新手指南</a>
    </div>

    <div class="b-lite-header__bar b-lite-header__bar--nav">
      <nav class="b-lite-header__nav" aria-label="B站轻量导航">
        <a class="{{ $isActive(['products.index', 'products.show']) ? 'is-active' : '' }}" href="{{ route('products.index') }}">广场</a>
        @auth
          <a class="{{ $isActive(['procurement.create']) ? 'is-active' : '' }}" href="{{ route('procurement.create') }}">发布</a>
          <a class="{{ $isActive(['orders.']) ? 'is-active' : '' }}" href="{{ route('orders.index') }}">任务</a>
          <a class="{{ $isActive(['user_addresses.']) ? 'is-active' : '' }}" href="{{ route('user_addresses.index') }}">存管</a>
        @else
          <a class="{{ $isActive(['login']) ? 'is-active' : '' }}" href="{{ route('login') }}">登录</a>
        @endauth
      </nav>

      <a class="b-lite-header__primary-cta" href="{{ route('procurement.create') }}">发布委托</a>
    </div>
  </div>
</header>