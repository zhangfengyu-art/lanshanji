@extends(is_cross_site_checkout_page() ? 'layouts.payment_gateway' : 'layouts.app')
@section('title', trans('frontend.pages.error'))

@section('content')
<div class="panel panel-default">
    <div class="panel-heading">{{ trans('frontend.pages.error') }}</div>
    <div class="panel-body text-center">
        <h1>{{ $msg }}</h1>
        <a class="btn btn-primary" href="{{ site_home_url() }}">{{ trans('frontend.common.back_to_home') }}</a>
    </div>
</div>
@endsection
