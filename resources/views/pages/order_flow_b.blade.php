@extends('layouts.app')

@section('title', '交易流程说明')

@section('content')
<div class="panel panel-default policy-page">
    <div class="panel-heading">交易流程说明</div>
    <div class="panel-body policy-page-body">
        <p class="lead">岚山集互助代购大厅提供<strong>求购发布、资金托管、代购承接与国内转寄</strong>服务。以下为典型交易流程。</p>

        <h4>1. 发起求购</h4>
        <p>登录后填写商品需求、预算金额与国内转寄地址。求购信息将展示在大厅，供代购师浏览与承接。</p>

        <h4>2. 托管支付</h4>
        <p>发起求购后请尽快完成托管支付，以锁定预算。支付进入平台托管账户，履约确认后再结算给代购方，降低双方风险。</p>

        <h4>3. 代购承接与采购</h4>
        <p>代购师承接后按约定在日本当地采购。履行进度可在「我的订单」中查看；如有异常请通过客户反馈联系平台。</p>

        <h4>4. 跨境寄送与国内转寄</h4>
        <p>商品经 EMS 等跨境渠道寄出后，平台会更新物流信息。入境后按您填写的国内转寄地址继续派送，具体时效因口岸与属地海关而异。</p>

        <h4>5. 签收确认</h4>
        <p>收到转寄物品后，请在订单页点击「确认签收」。签收后托管资金按规则结算，交易完成。</p>

        <div class="policy-notice">
            <p><strong>温馨提示：</strong>跨境物流受海关、节假日等因素影响，请勿仅凭催促加快进度。急单请谨慎下单。</p>
        </div>

        <p>改址、退款规则请参见 <a href="{{ url('/pages/change-exchange-return.html') }}">售后与退款规则</a>；更多问题见 <a href="{{ url('/pages/faq.html') }}">常见问题</a>。</p>
    </div>
</div>
@endsection
