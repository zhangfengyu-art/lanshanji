<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', trans('frontend.site.title')) - {{ trans('frontend.site.subtitle') }}</title>
    <!-- 样式 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    @if(is_site_mode_b())
        <link href="{{ asset('css/site-mode-b.css') }}?v={{ @filemtime(public_path('css/site-mode-b.css')) }}" rel="stylesheet">
    @endif
        @yield('styles')
</head>
<body class="site-mode-{{ strtolower(site_mode()) }}">
    @php
        $isCartAvailable = auth()->check();
    @endphp
    <div id="app" class="{{ route_class() }}-page">
        @if(is_site_mode_b())
            @include('b_mode.layouts._header')
        @else
            @include('layouts._header')
        @endif
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
    </script>
    <script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}"></script>
    @yield('scriptsAfterJs')
</body>
</html>