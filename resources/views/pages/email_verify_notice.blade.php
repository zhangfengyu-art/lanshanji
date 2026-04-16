@extends('layouts.app')
@section('title', trans('frontend.pages.notice'))

@section('content')
<div class="panel panel-default">
    <div class="panel-heading">{{ trans('frontend.pages.notice') }}</div>
    <div class="panel-body text-center">
        <h1>{{ trans('frontend.pages.verify_email_first') }}</h1>
        <a class="btn btn-primary" href="{{ route('email_verification.send') }}">{{ trans('frontend.pages.resend_verification') }}</a>
    </div>
</div>
@endsection