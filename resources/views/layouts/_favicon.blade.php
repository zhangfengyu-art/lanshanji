@php
    $faviconUrl = site_favicon_url();
    $faviconVersion = site_favicon_version();
@endphp
<link rel="icon" href="{{ url('/favicon.ico') }}?v={{ $faviconVersion }}">
@if($faviconUrl)
    <link rel="icon" type="image/png" sizes="32x32" href="{{ $faviconUrl }}">
    <link rel="shortcut icon" href="{{ $faviconUrl }}">
    <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
@endif
