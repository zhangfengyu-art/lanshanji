@php $faviconUrl = site_favicon_url() ?: ($siteLogoUrl ?? null); @endphp
@if($faviconUrl)
    <link rel="icon" href="{{ $faviconUrl }}">
    <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
@endif
