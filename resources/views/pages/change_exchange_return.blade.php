@extends('layouts.app')
@section('title', '订单改、退相关规则')

@section('content')
<div class="panel panel-default policy-page">
    <div class="panel-heading">订单改、退相关规则</div>
    <div class="panel-body policy-page-body">
        <p class="lead">以下规则与订单页显示的<strong>履约阶段</strong>一致。支付成功后订单进入<strong>待处理</strong>；后台标记开始处理后仍属待处理（订单页会标注「已开始处理」），进入备货/打包、已发货后规则随之变化。</p>

        <h4>1、更改收件人信息</h4>
        <ul>
            <li><strong>待处理（尚未开始处理）：</strong>可以更改，在订单内点击「变更信息」，通过<strong>省、市、区下拉框</strong>与详细地址表单修改（与下单时选地址方式一致）。付款后至后台点击「开始处理」之前均可自助改址，不设时间上限；每笔订单自助改址最多 <strong>2 次</strong>。</li>
            <li><strong>待处理（已开始处理，等待供应商配送）：</strong>不提供自助改址，需在<a href="{{ auth()->check() ? route('support.feedbacks.create') : route('login') }}">客户反馈</a>中提出变更信息申请。</li>
            <li><strong>备货/打包：</strong>需在<a href="{{ auth()->check() ? route('support.feedbacks.create') : route('login') }}">客户反馈</a>中提出变更信息申请。EMS 包裹会尽量协助更改，但如果是已经送往物流仓库、且即将打印纸质面单，无法承诺收件人信息一定来得及变更成功。顺丰包裹可以更改。</li>
            <li><strong>已发货（已产生物流单号）：</strong>EMS 包裹发货后收件人信息不提供更改。若您填写地址时手机号错误、无法收到海关通知短信，包裹可能滞留在海关，请主动联系当地海关咨询如何缴纳税费。顺丰包裹在未产生物流记录之前可以更改，需在<a href="{{ auth()->check() ? route('support.feedbacks.create') : route('login') }}">客户反馈</a>中提出申请；已产生物流后虽可更改，但中国国内段运费可能需额外到付一次。</li>
        </ul>

        <h4>2、更换商品说明</h4>
        <p>订单<strong>不支持</strong>在站内自助调换商品，也不支持增加、减少、合并、拆分订单。</p>
        <p>若下单后想换买其它商品，请在仍可退款的情况下<strong>申请退款后重新下单</strong>，这样更清晰，也避免同价调换带来的纠纷。确因缺货、停产等特殊情况的，请通过<a href="{{ auth()->check() ? route('support.feedbacks.create') : route('login') }}">客户反馈</a>与本店协商处理。</p>

        <h4>3、取消订单</h4>
        <p><strong>待处理（尚未开始处理）：</strong></p>
        <p>可在订单内点击<strong>「取消订单（全额秒退）」</strong>，系统将立即向支付平台发起<strong>100% 全额退款</strong>（原路退回）。支付宝一般较快到账；微信支付可能先显示「退款中」，以订单状态更新为准。</p>
        <ul>
            <li>为防止恶意刷单，同一账号在<strong>最近 {{ (int) config('order_refund.instant.window_hours', 24) }} 小时内最多可自助秒退 {{ (int) config('order_refund.instant.max_per_window', 3) }} 次</strong>，且两次秒退之间须间隔至少 <strong>{{ (int) config('order_refund.instant.min_interval_minutes', 5) }} 分钟</strong>（具体以订单页提示为准）。超出额度后请稍后再试，或通过<a href="{{ auth()->check() ? route('support.feedbacks.create') : route('login') }}">客户反馈</a>联系本站。</li>
        </ul>
        <p>请审慎下单，勿冲动消费。支付平台对频繁退款有风险管控，办理耗时可能随次数增多而延长。</p>
        <p>日本方面完成退款后通常会实时到达中国银联（非正常工作时间除外），但中国银联退款到对应银行通常需要 1～2 个工作日（不含周末与节假日），请耐心等待；若还未到账，请联系相应银行咨询（通常银行承诺跨境退款 7 天内到账）。</p>
        <ul>
            <li><strong>待处理（已开始处理，等待供应商配送）：</strong>此阶段订单内已不提供自助秒退按钮，需在<a href="{{ auth()->check() ? route('support.feedbacks.create') : route('login') }}">客户反馈</a>中提出取消订单申请。开始处理不满 {{ (int) (config('order_refund.waiting_supplier_hours', 168) / 24) }}×24 小时，取消订单收取 {{ (int) round(config('order_refund.cancellation_fee_ratio', 0.20) * 100) }}% 取消费用（即 {{ (int) round((1 - config('order_refund.cancellation_fee_ratio', 0.20)) * 100) }}% 退款）；等待超过 {{ (int) (config('order_refund.waiting_supplier_hours', 168) / 24) }}×24 小时，或本店已确认供应商近期无法正常供货，取消订单免除取消费用（100% 全额退款）。</li>
            <li><strong>备货/打包：</strong>此阶段站内已不提供自助退款。需在<a href="{{ auth()->check() ? route('support.feedbacks.create') : route('login') }}">客户反馈</a>中提出申请。系统后台此阶段<strong>原则上不支持标准自动退款</strong>，本店将视包裹是否已送往物流仓库等情况个案协商，<strong>无法承诺一定能取消</strong>；若包裹已送往物流仓库，因需物流人员配合找出对应包裹，建议不要抱有较大期待。</li>
            <li><strong>已发货（已产生物流单号）：</strong>不支持取消订单，请务必谨慎下单。</li>
        </ul>

        <h4>4、关于断货 / 停产</h4>
        <p>大多数时候需要等到某次新订货才会知晓该款商品供应商已经暂停供货或厂家永久停产。确认情况后会在 1 个工作日内邮件通知未发货订单涉及该商品的顾客。此类情况可申请<strong>全额退款</strong>，或通过<a href="{{ auth()->check() ? route('support.feedbacks.create') : route('login') }}">客户反馈</a>与本店协商其它处理方式。</p>

        <h4>5、关于退运</h4>
        <p>请务必谨慎下单，勿冲动消费，提前向您当地海关充分了解海淘相关规定，避免产生退运给您带来损失。</p>

        <h4>6、关于发错货、丢件</h4>
        <ul>
            <li>包裹已收到，但不是我的订单：有极小概率物流贴错面单，请在<a href="{{ auth()->check() ? route('support.feedbacks.create') : route('login') }}">客户反馈</a>中与我们联系，我们会协调您与正确的收件人互换包裹，并承担由此产生的额外费用。</li>
            <li>包裹已收到，但部分商品品种有错：有极小概率人工配货出错，请在<a href="{{ auth()->check() ? route('support.feedbacks.create') : route('login') }}">客户反馈</a>中与我们联系。</li>
            <li>包裹已收到，但部分商品数量不足：请与订单中提供的发货照片比对。若发货照片也同样缺少，请在<a href="{{ auth()->check() ? route('support.feedbacks.create') : route('login') }}">客户反馈</a>中与我们联系；若发货照片里数量正确，说明包裹通过海关后在您当地可能发生失窃，请尽快与当地物流联系。</li>
            <li>未收到包裹：国际包裹寄送耗时较久，2～3 天物流记录未更新是正常情况；入境口岸与海关业务繁忙时可能延后数日处理。真正的包裹丢失概率极低，建议耐心等待物流记录更新。若着急使用，不建议通过海淘购买。</li>
        </ul>

        <hr>

        <p class="policy-footnote">
            能添加购物车并下单的商品，不代表有现货，也不代表很快能发货，具体请参见
            <a href="{{ route('pages.order_flow') }}">下单流程说明</a>。急单建议另寻其它购买途径，给您带来不便非常抱歉。
        </p>

        <div class="text-center" style="margin-top: 24px;">
            @auth
                <a class="btn btn-warning" href="{{ route('support.feedbacks.create') }}">客户反馈</a>
                <a class="btn btn-default" href="{{ route('orders.index') }}" style="margin-left: 8px;">查看我的订单</a>
            @else
                <a class="btn btn-warning" href="{{ route('login') }}">登录后联系咨询</a>
            @endauth
            <a class="btn btn-primary" href="{{ route('products.index') }}" style="margin-left: 8px;">继续购物</a>
        </div>
    </div>
</div>
@endsection
