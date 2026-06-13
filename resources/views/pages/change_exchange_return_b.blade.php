@extends('layouts.app')

@section('title', '售后与退款规则')

@section('content')
<div class="panel panel-default policy-page">
    <div class="panel-heading">售后与退款规则</div>
    <div class="panel-body policy-page-body">
        <h4>1. 更改国内转寄地址</h4>
        <ul>
            <li><strong>已托管支付、尚未开始履行：</strong>可在订单内自助修改国内转寄地址（每笔订单最多 2 次）。</li>
            <li><strong>代购师已承接或采购中：</strong>请在<a href="{{ auth()->check() ? route('support.feedbacks.create') : route('login') }}">客户反馈</a>中申请，平台将视物流进度协助处理。</li>
            <li><strong>已产生跨境或国内物流单号：</strong>一般无法保证改址成功，请下单时仔细核对地址与手机号。</li>
        </ul>

        <h4>2. 取消订单与退款</h4>
        <p><strong>尚未开始履行（待承接/待处理）：</strong>可在订单页申请取消，按支付渠道原路退款。同一账号 24 小时内自助退款次数有限，请审慎下单。</p>
        <p><strong>代购师已承接或采购中：</strong>需在<a href="{{ auth()->check() ? route('support.feedbacks.create') : route('login') }}">客户反馈</a>中申请。是否可退及退款比例取决于是否已发生采购、寄送等成本，由平台审核后处理。</p>
        <p><strong>已寄出：</strong>原则上不支持取消；因平台或代购方原因导致无法履约的，按实际情况协商处理。</p>

        <h4>3. 缺货与无法履约</h4>
        <p>若确认无法按求购内容完成采购，平台将通知您并安排全额退款或协商替代方案。</p>

        <h4>4. 签收与纠纷</h4>
        <p>收到物品后请及时查验并在订单页确认签收。如有破损、错发、少发，请在签收后尽快通过<a href="{{ auth()->check() ? route('support.feedbacks.create') : route('login') }}">客户反馈</a>举证说明，平台将协助协调。</p>

        <p style="margin-top: 24px;">
            <a class="btn btn-default" href="{{ url('/pages/order-flow.html') }}">交易流程</a>
            <a class="btn btn-default" href="{{ url('/pages/faq.html') }}" style="margin-left: 8px;">常见问题</a>
        </p>
    </div>
</div>
@endsection
