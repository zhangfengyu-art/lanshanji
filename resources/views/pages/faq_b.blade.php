@extends('layouts.app')

@section('title', '常见问题')

@section('content')
<div class="panel panel-default policy-page">
    <div class="panel-heading">常见问题</div>
    <div class="panel-body policy-page-body">
        <h4>岚山集是什么？</h4>
        <p>岚山集是跨境互助代购撮合与资金托管平台。买家发布求购需求并托管支付，代购师在日本当地采购后寄送，平台协调履约与国内转寄。</p>

        <h4>什么是资金托管？</h4>
        <p>您支付的款项先进入平台托管账户，待您确认签收或按规则自动结算后，再支付给代购方。这样可降低「付了款对方不发货」或「发了货买家不认账」的风险。</p>

        <h4>求购后多久会有人承接？</h4>
        <p>取决于需求品类、预算与当前活跃代购师数量。完成托管支付后需求会优先展示在大厅；无人承接时可调整预算或需求描述后重新发布。</p>

        <h4>物流要多久？</h4>
        <p>跨境段通常需数日至十余日，入境后国内转寄另计。具体受采购进度、EMS 排期、海关查验等因素影响，订单页状态与物流信息为准。</p>

        <h4>可以改地址或退款吗？</h4>
        <p>未开始履行的订单可按规定自助改址或申请退款；已进入采购或寄送阶段的规则见 <a href="{{ url('/pages/change-exchange-return.html') }}">售后与退款规则</a>。</p>

        <h4>如何联系客服？</h4>
        <p>登录后可通过<a href="{{ auth()->check() ? route('support.feedbacks.create') : route('login') }}">客户反馈</a>提交问题，工作日 7×12 小时处理。</p>

        <p style="margin-top: 24px;">
            <a class="btn btn-primary" href="{{ route('procurement.create') }}">发起求购</a>
            <a class="btn btn-default" href="{{ url('/pages/order-flow.html') }}" style="margin-left: 8px;">交易流程</a>
        </p>
    </div>
</div>
@endsection
