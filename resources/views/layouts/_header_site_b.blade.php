<header class="site-header site-header--b">
    <div class="site-header__accent-bar" aria-hidden="true"></div>
    <div class="site-header__container">
        <div class="site-header__main">
            <div class="site-header__logo-wrap">
                <a class="site-header__logo" href="{{ route('root') }}" aria-label="{{ site_brand_zh() }}">
                    <span class="site-header__logo-fallback site-header__logo-fallback--b">{{ site_brand_zh() }}</span>
                </a>
                <div class="site-header__brand-tag">
                    <span class="site-header__brand-tag-main">互助代购大厅</span>
                    <span class="site-header__brand-tag-sub">跨境求购与承接</span>
                </div>
            </div>

            <div class="site-header__actions">
                <form class="site-header__search site-header__search--b" action="{{ route('products.index') }}" method="GET">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="搜索求购关键词…">
                    <button type="submit" aria-label="搜索">
                        <span class="glyphicon glyphicon-search" aria-hidden="true"></span>
                    </button>
                </form>

                <div class="site-header__user-nav">
                    @guest
                        <a class="site-header__nav-link" href="{{ route('login') }}">登录</a>
                        <a class="site-header__nav-link site-header__nav-link--accent" href="{{ route('register') }}">注册</a>
                    @else
                        <div class="dropdown site-header__account-dropdown">
                            <a href="#" class="dropdown-toggle site-header__nav-link" data-toggle="dropdown" role="button" aria-expanded="false">
                                {{ Auth::user()->name }} <span class="caret"></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu--b" role="menu">
                                <li><a href="{{ route('user_addresses.index') }}">国内转寄地址</a></li>
                                <li><a href="{{ route('orders.index') }}">我的求购 / 订单</a></li>
                                <li>
                                    <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form-b').submit();">退出登录</a>
                                    <form id="logout-form-b" action="{{ route('logout') }}" method="POST" style="display: none;">
                                        {{ csrf_field() }}
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @endguest
                </div>
            </div>
        </div>
    </div>

    <div class="site-header__nav-wrap site-header__nav-wrap--b">
        <div class="site-header__container">
            <nav class="site-header__nav site-header__nav--b" aria-label="主导航">
                <a class="top-category-link{{ request()->routeIs('products.index') && !request('search') ? ' is-active' : '' }}" href="{{ route('products.index') }}">求购大厅</a>
                @auth
                <a class="top-category-link{{ request()->routeIs('procurement.create') ? ' is-active' : '' }}" href="{{ route('procurement.create') }}">发起求购</a>
                <a class="top-category-link{{ request()->routeIs('orders.*') ? ' is-active' : '' }}" href="{{ route('orders.index') }}">我的订单</a>
                @else
                <a class="top-category-link" href="{{ route('login') }}">登录后发起求购</a>
                @endauth
            </nav>
        </div>
    </div>
</header>
