<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', site_brand_zh()) - {{ site_page_subtitle() }}</title>
    @include('layouts._favicon')
    <!-- 样式 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}@if(file_exists(public_path('css/app.css')))?v={{ filemtime(public_path('css/app.css')) }}@endif" rel="stylesheet">
        <style>
            .mini-cart-drawer {
                width: 320px !important;
                max-width: calc(100vw - 90px) !important;
                overflow: hidden !important;
            }

            .mini-cart-drawer .mini-cart-item__img,
            .mini-cart-drawer [class*="item__img"] {
                width: 52px !important;
                height: 52px !important;
                overflow: hidden !important;
                flex: 0 0 52px !important;
            }

            .mini-cart-drawer .mini-cart-item__img img,
            .mini-cart-drawer [class*="item__img"] img,
            .mini-cart-drawer img {
                display: block !important;
                width: 52px !important;
                height: 52px !important;
                max-width: 52px !important;
                max-height: 52px !important;
                object-fit: contain !important;
                position: static !important;
            }
        </style>
</head>
<body>
    @php
        $isCartAvailable = auth()->check();
    @endphp
    @php
        $routeClass = route_class();
        $pageClasses = $routeClass.'-page';
        if (in_array($routeClass, ['payment-wechat', 'payment-alipay'], true)) {
            $pageClasses .= ' orders-show-page payment-checkout-page';
        }
        if (is_site_mode_b()) {
            $pageClasses .= ' site-mode-b';
        }
    @endphp
    <div id="app" class="{{ $pageClasses }}">
        @include('layouts._header')
        <div class="container">
            @yield('content')
        </div>
        @include('layouts._footer')

        @if(!is_site_mode_b())
            <div class="mini-cart-fab" data-mini-cart data-auth="{{ $isCartAvailable ? '1' : '0' }}" data-summary-url="{{ $isCartAvailable ? route('cart.summary') : '' }}" data-checkout-url="{{ $isCartAvailable ? route('cart.index') : route('login') }}" data-login-url="{{ route('login') }}">
                <button type="button" class="mini-cart-fab__button" data-mini-cart-toggle aria-label="{{ trans('frontend.nav.cart') }}">
                    <span class="glyphicon glyphicon-shopping-cart" aria-hidden="true"></span>
                    <span class="mini-cart-fab__badge" data-mini-cart-count>0</span>
                </button>
                <div class="mini-cart-drawer" data-mini-cart-drawer>
                    <div class="mini-cart-drawer__header">{{ trans('frontend.cart.title') }}</div>
                    <div class="mini-cart-drawer__body" data-mini-cart-items></div>
                    <div class="mini-cart-drawer__footer">
                        <a href="{{ $isCartAvailable ? route('cart.index') : route('login') }}" class="mini-cart-drawer__checkout" data-mini-cart-checkout>{{ trans('frontend.cart.checkout') }}</a>
                    </div>
                </div>
            </div>

            <div class="mini-toast" data-mini-toast></div>
        @endif
    </div>
    <!-- JS 脚本 -->
    <script>
        window.AppI18n = @json(trans('frontend.js'));
        window.AppI18nCart = @json(trans('frontend.cart'));
        window.AppSiteModeA = @json(is_site_mode_a());
    </script>
    <script src="{{ asset('js/app.js') }}@if(file_exists(public_path('js/app.js')))?v={{ filemtime(public_path('js/app.js')) }}@endif"></script>
    @yield('scriptsAfterJs')
</body>
</html>