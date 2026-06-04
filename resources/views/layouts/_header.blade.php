@if(is_site_mode_b())
  @include('layouts._header_site_b')
@else
<header class="site-header">
    <div class="site-header__container">
        <div class="site-header__main">
            <div class="site-header__logo-wrap">
                <a class="site-header__logo" href="{{ site_home_url() }}" aria-label="{{ site_brand_zh() }}">
                    @php $headerLogoUrl = site_logo_url() ?: ($siteLogoUrl ?? null); @endphp
                    @if(!empty($headerLogoUrl))
                        <img src="{{ $headerLogoUrl }}" alt="{{ site_brand_zh() }}" style="height: 152px; width: auto; max-height: none;">
                    @endif
                </a>
            </div>

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
                                <li><a href="{{ route('support.feedbacks.index') }}">客服反馈</a></li>
                                <li><a href="{{ route('support.feedbacks.create') }}">提交反馈</a></li>
                                <li>
                                    <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                        {{ trans('frontend.nav.logout') }}
                                    </a>
                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
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
            @php
                $currentCategoryId = (int) request('category', 0);
                $isAllProductsActive = $currentCategoryId === 0;
                $reservedCategoryNames = ['所有商品', '全部商品'];
            @endphp
            <nav class="site-header__nav" aria-label="{{ trans('frontend.nav.main_categories_aria') }}">
                <div class="top-category-item{{ $isAllProductsActive ? ' is-active' : '' }}">
                    <a class="top-category-link{{ $isAllProductsActive ? ' is-active' : '' }}" href="{{ route('products.index') }}">
                        {{ trans('frontend.nav.all_products') }}
                    </a>
                </div>
                @foreach($categories as $category)
                    @if(in_array($category->name, $reservedCategoryNames, true))
                        @continue
                    @endif
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
                                        <span class="top-category-badge">EMS直邮</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </nav>
        </div>
    </div>
</header>
@endif