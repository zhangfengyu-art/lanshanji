<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '支付收银台') - {{ site_page_subtitle() }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/site-b.css') }}" rel="stylesheet">
</head>
<body>
@php
    $routeClass = route_class();
    $pageClasses = $routeClass.'-page orders-show-page payment-checkout-page payment-gateway-page site-mode-b';
    $gatewayOrder = $gatewayOrder ?? request()->route('order');
    $gatewayOrderId = is_object($gatewayOrder) ? $gatewayOrder->id : (int) $gatewayOrder;
@endphp
<div id="app" class="{{ $pageClasses }}">
    @include('layouts._payment_gateway_header', ['gatewayOrderId' => $gatewayOrderId])

    <div class="container payment-gateway-container">
        @yield('content')
    </div>

    @include('layouts._payment_gateway_footer')
</div>

<script>
    window.AppI18n = @json(trans('frontend.js'));
</script>
<script src="{{ asset('js/app.js') }}"></script>
@yield('scriptsAfterJs')
</body>
</html>
