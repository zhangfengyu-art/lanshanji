<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2C7BE5">
    <title>@yield('title', trans('frontend.site.title')) - {{ trans('frontend.site.subtitle') }}</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/site-mode-b.css') }}?v={{ @filemtime(public_path('css/site-mode-b.css')) }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="site-mode-b">
    <div id="app" class="b-mode-shell {{ route_class() }}-page">
        <header class="b-mode-topbar">
            @includeIf('b_mode.layouts._header')
        </header>

        <main class="b-mode-main">
            @yield('content')
        </main>

        <footer class="b-mode-footer">
            @includeIf('b_mode.layouts._footer')
        </footer>
    </div>

    <aside class="b-mode-floatbar" aria-label="快捷工具栏">
        <a class="b-mode-floatbar__item" href="{{ route('b_mode.faq_guidelines') }}" title="帮助中心">
            <span data-lucide="book-open"></span>
            <span>指南</span>
        </a>
        <a class="b-mode-floatbar__item" href="{{ route('orders.index') }}" title="我的任务">
            <span data-lucide="list-checks"></span>
            <span>任务</span>
        </a>
        <a class="b-mode-floatbar__item" href="{{ route('procurement.create') }}" title="发起求购">
            <span data-lucide="plus-circle"></span>
            <span>发布</span>
        </a>
        <button class="b-mode-floatbar__item" type="button" data-b-mode-back-to-top title="回到顶部">
            <span data-lucide="arrow-up"></span>
            <span>顶部</span>
        </button>
    </aside>

    <script>
        window.AppI18n = @json(trans('frontend.js'));
        window.AppI18nCart = @json(trans('frontend.cart'));
    </script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.lucide && window.lucide.createIcons) {
                window.lucide.createIcons();
            }

            var backToTop = document.querySelector('[data-b-mode-back-to-top]');
            if (backToTop) {
                backToTop.addEventListener('click', function () {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }
        });
    </script>
    @stack('scripts')
    @yield('scriptsAfterJs')
</body>
</html>
