@extends('layouts.app')
@section('title', trans('frontend.pages.error'))

@section('content')
<style>
    body.site-mode-b .payment-return-wrap {
        max-width: 760px;
        margin: 12px auto;
        padding: 0 6px;
    }

    body.site-mode-b .payment-return-wrap .panel {
        border-radius: 4px;
        border-color: #ddd;
        box-shadow: none;
    }

    body.site-mode-b .payment-return-wrap .panel-heading {
        background: #f5f5f5;
        color: #333;
        border-bottom-color: #ddd;
        font-weight: 700;
    }

    body.site-mode-b .payment-return-wrap .panel-body h1 {
        font-size: 30px;
        color: #333;
        margin-bottom: 16px;
    }

    body.site-mode-b .payment-return-wrap .btn.btn-primary {
        background: #337ab7;
        border-color: #2e6da4;
        color: #fff;
        border-radius: 4px;
        font-weight: 400;
    }

    body.site-mode-b .payment-return-wrap .btn.btn-primary:hover {
        background: #286090;
        border-color: #204d74;
    }
</style>

<div class="payment-return-wrap">
    <div class="panel panel-default">
        <div class="panel-heading">{{ trans('frontend.pages.error') }}</div>
        <div class="panel-body text-center">
            <h1>{{ $msg }}</h1>
            <p class="text-muted" style="margin:0 0 12px;">可返回订单页重新发起支付，原订单信息不会丢失。</p>
            <a class="btn btn-primary" href="{{ route('root') }}">{{ trans('frontend.common.back_to_home') }}</a>
    </div>
    </div>
</div>
@endsection
