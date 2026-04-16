<header class="site-header">
    <div class="site-header__container">
        <div class="site-header__main">
            @if(!is_site_mode_b())
                <div class="site-header__logo-wrap">
                    <a class="site-header__logo" href="{{ url('/') }}" aria-label="{{ $siteBrandZh }}">
                        @if(!empty($siteLogoUrl))
                            <img src="{{ $siteLogoUrl }}" alt="{{ $siteBrandZh }}" style="height: 152px; width: auto; max-height: none;">
                        @elseif(file_exists(public_path('images/brand-logo.svg')))
                            <img src="{{ asset('images/brand-logo.svg') }}" alt="{{ $siteBrandZh }}" style="height: 152px; width: auto; max-height: none;">
                        @else
                            <span class="site-header__logo-fallback">岚山烟务所</span>
                        @endif
                    </a>
                </div>
            @endif

            <div class="site-header__actions">
                <div class="site-header__user-nav">
                    @guest
                        <a href="{{ route('login') }}">{{ trans('frontend.nav.login') }}</a>
                        <a href="{{ route('register') }}">{{ trans('frontend.nav.register') }}</a>
                    @else
                        <div class="dropdown site-header__account-dropdown">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">
                                {{ Auth::user()->name }} <span class="caret"></span>
                            </a>
                            <ul class="dropdown-menu" role="menu">
                                <li><a href="{{ route('user_addresses.index') }}">{{ trans('frontend.nav.addresses') }}</a></li>
                                <li><a href="{{ route('orders.index') }}">{{ trans('frontend.nav.my_orders') }}</a></li>
                                <li><a href="{{ route('products.favorites') }}">{{ trans('frontend.nav.my_favorites') }}</a></li>
                                <li><a href="{{ route('support.feedbacks.replies') }}">问题回复</a></li>
                                <li>
                                    <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                        {{ trans('frontend.nav.logout') }}
                                    </a>
                                    <form id="logout-form" class="logout-form" action="{{ route('logout') }}" method="POST">
                                        {{ csrf_field() }}
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @endguest

                    @if(!is_site_mode_b())
                        <button type="button" class="mini-cart-toggle" data-mini-cart-toggle aria-label="{{ trans('frontend.nav.cart') }}">
                            <span class="mini-cart__icon" aria-hidden="true">
                                <span class="glyphicon glyphicon-shopping-cart"></span>
                                <span class="mini-cart__badge" data-mini-cart-count data-cart-count="0">0</span>
                            </span>
                            <span class="mini-cart__text">{{ trans('frontend.nav.cart') }}</span>
                        </button>
                    @endif

                    <div class="header-language dropdown">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">
                            {{ trans('frontend.nav.language') }} <span class="caret"></span>
                        </a>
                        <ul class="dropdown-menu" role="menu">
                            <li><a href="#">{{ trans('frontend.nav.language') }}</a></li>
                        </ul>
                    </div>
                </div>

                <div class="site-header__notice-search">
                    <form class="site-header__search" action="{{ route('products.index') }}" method="GET">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ trans('frontend.nav.search_placeholder') }}">
                        <button type="submit" aria-label="{{ trans('frontend.nav.search_aria') }}">
                            <span class="glyphicon glyphicon-search" aria-hidden="true"></span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="site-header__nav-wrap">
        <div class="site-header__container">
            @php $currentCategoryId = (int) request('category', 0); @endphp
            <nav class="site-header__nav" aria-label="{{ trans('frontend.nav.main_categories_aria') }}">
                @forelse($categories as $category)
                    @php
                        $childIds = $category->children->pluck('id')->map(function ($id) {
                            return (int) $id;
                        })->all();
                        $isParentActive = $currentCategoryId === (int) $category->id;
                        $isChildActive = in_array($currentCategoryId, $childIds, true);
                    @endphp
                    <div class="top-category-item{{ ($isParentActive || $isChildActive) ? ' is-active' : '' }}">
                        <a class="top-category-link{{ $isParentActive ? ' is-active' : '' }}" href="{{ route('products.index', ['category' => $category->id]) }}">
                            {{ $category->name }}
                        </a>
                        @if($category->children->count() > 0)
                            <div class="top-category-submenu" role="menu">
                                @foreach($category->children as $child)
                                    @php $isChildCurrent = $currentCategoryId === (int) $child->id; @endphp
                                    <a class="top-category-sublink{{ $isChildCurrent ? ' is-active' : '' }}" href="{{ route('products.index', ['category' => $child->id]) }}">
                                        <span>{{ $child->name }}</span>
                                        <span class="top-category-badge">直邮</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <a class="top-category-link is-empty" href="{{ route('products.index') }}">{{ trans('frontend.nav.all_products') }}</a>
                @endforelse
            </nav>
        </div>
    </div>
</header>