@extends('layouts.app')

@section('title', '改/换/退')

@section('styles')
<style>
    .policy-wrap {
        max-width: 980px;
        margin: 16px auto 24px;
        color: #000;
    }

    .policy-wrap,
    .policy-wrap * {
        color: #000 !important;
    }

    .policy-wrap h1 {
        font-size: 44px;
        color: #000 !important;
        font-weight: 700;
        margin-bottom: 18px;
    }

    .policy-wrap h2 {
        font-size: 28px;
        color: #000 !important;
        margin: 10px 0 10px;
        border-bottom: 1px solid #d6dde6;
        padding-bottom: 8px;
        font-weight: 700;
    }

    .policy-body {
        background: #f4f6f9;
        border-radius: 8px;
        padding: 16px 18px;
        line-height: 1.9;
        margin-bottom: 16px;
        font-size: 16px;
        color: #000 !important;
    }

    .policy-note {
        background: #fff7dd;
        border: 1px solid #f3dd9b;
        border-radius: 8px;
        padding: 10px 14px;
        margin-bottom: 10px;
        font-size: 16px;
        color: #7a4f00;
        font-weight: 600;
    }

    .policy-wrap p,
    .policy-wrap li {
        color: #000 !important;
        margin-bottom: 12px;
    }

    .policy-wrap ul {
        margin-bottom: 0;
        padding-left: 26px;
    }

    .policy-wrap p:last-child,
    .policy-wrap li:last-child {
        margin-bottom: 0;
    }

    .policy-wrap a {
        color: #0f4ca8 !important;
        text-decoration: underline;
    }

    .policy-wrap .text-danger {
        color: #dc3545 !important;
    }

    .policy-wrap .text-success {
        color: #198754 !important;
    }

    @media (max-width: 768px) {
        .policy-wrap {
            margin-top: 10px;
            margin-bottom: 16px;
            padding: 0 12px;
        }

        .policy-wrap h1 {
            font-size: 32px;
            line-height: 1.35;
            margin-bottom: 14px;
        }

        .policy-wrap h2 {
            font-size: 22px;
        }

        .policy-body,
        .policy-note {
            font-size: 15px;
            line-height: 1.8;
            padding: 12px 14px;
        }
    }
</style>
@endsection

@section('content')
<div class="container policy-wrap">
    <h1 class="text-center">订单改、换、退相关规则</h1>

    <h2>1、更改收件人信息</h2>
    <div class="policy-body">
        <p><strong>已支付但订单未开始被正式受理：</strong>可以更改，在订单内自行点击“变更信息”按钮。</p>
        <p><strong>订货中商品等待烟草公司配送：</strong>可以更改，但不提供自行变更功能，需在“<a href="{{ route('support.feedbacks.create') }}">联系咨询</a>”中提出变更信息申请。</p>
        <p><strong>订单已开始发货处理：</strong>需在“<a href="{{ route('support.feedbacks.create') }}">联系咨询</a>”中提出变更信息申请。</p>
        <p>EMS包裹会尽量协助更改，但如果是已经送往物流仓库，且即将将要打印纸质面单，无法承诺收件人信息一定来得及变更成功。顺丰包裹可以更改。</p>
        <p><strong>订单已发货（已产生物流单号）：</strong>EMS包裹发货后收件人信息不提供更改，可能您咨询您当地的邮政会告诉您联系寄件人修改，但实际上我们不能为您修改，在您填写地址的时候地址栏也有明确的相关提醒。如果收件人信息手机号错误无法收到海关通知短信，包裹会滞留在海关，您需要主动联系您当地海关咨询此类特殊情况如何缴纳税费。</p>
        <p>顺丰包裹发货后在未产生物流记录之前可以更改，需在“<a href="{{ route('support.feedbacks.create') }}">联系咨询</a>”中提出变更信息申请，已产生物流后虽然也可以更改但会导致中国国内段运费变成需要额外到付1次费用。</p>
    </div>

    <h2>2、更换订单中的商品</h2>
    <div class="policy-note">
        注意：订单不支持增加、减少、合并、拆分
    </div>
    <div class="policy-body">
        <p><strong>已支付但订单未开始被正式受理：</strong>同价商品可以更换，在订单内自行点击“调换商品”，更换需提前找到新商品的货号（商品名称前的数字/字母编号），仅支持同价更换（更换前后商品价格相同），不支持免服务费商品更换需收取服务费的商品组合，不支持补差价，也不支持放弃差额，必须完全同价。</p>
        <p><strong>订单已开始发货处理：</strong>不再接受商品更换申请，此阶段再更换会打乱发货的流程，极易导致您和他人的包裹出错，且专门寻找某个订单对应的纸箱人力成本过高。</p>
        <p><strong>订单已发货（已产生物流单号）：</strong>无法更换。</p>
    </div>

    <h2>3、取消订单</h2>
    <div class="policy-body">
        <p><strong>已支付但订单未开始被正式受理：</strong>可以取消（100%全额退款），自行在订单内点击申请退款按钮，本店会在1个工作日内受理，订单内显示“已退款”代表本店已退款。但请谨慎下单，勿冲动消费，因日本这边第三方收款平台有风险管控措施，<span class="text-danger">退款办理耗时会随您退款次数增多而显著加长</span>！<br>
            日本这边完成退款后通常会实时达到中国银联（非正常工作时间除外），但中国银联退款到对应银行通常需要1、2个工作日（不含周末与节假日），请耐心等待，若还未到账再联系相应银行咨询（通常银行是承诺跨境退款7天内到账）。
        </p>
        <p><strong>订货中商品等待烟草公司配送：</strong><br>
            此阶段订单内已不提供申请退款按钮，需在“<a href="{{ route('support.feedbacks.create') }}">联系咨询</a>”中提出取消订单申请。<br>
            下单时间26年3月6日24点前：若下单时商品已标注“预定”，取消订单<span class="text-danger">收取20%取消费用</span>（即80%退款）；若下单时商品未标注“预定”，取消订单<span class="text-success">免除取消费用</span>（100%全额退款）。<br>
            下单时间26年3月7日0点起：等待烟草公司配送不满7x24小时，取消订单<span class="text-danger">收取20%取消费用</span>（即80%退款）；等待烟草公司配送超过7x24小时，或本店已确认烟草公司近期无法正常供货，取消订单<span class="text-success">免除取消费用</span>（100%全额退款）。
        </p>
        <p><strong>订单已开始发货处理：</strong>原则上可以取消（需等待确认包裹是否已送往物流仓库），取消订单<span class="text-danger">收取20%取消费用</span>（即退款80%）。此阶段订单内已不提供申请退款按钮，需在“<a href="{{ route('support.feedbacks.create') }}">联系咨询</a>”中提出取消订单申请。但如果包裹已送往物流仓库，因需物流工作人员同意配合花时间找出对应包裹，无法承诺来得及取消订单，建议不要抱有太大期待。</p>
        <p><strong>订单已发货（已产生物流单号）：</strong>不支持取消订单，请务必谨慎下单，勿冲动消费。</p>
    </div>

    <h2>4、关于断货/停产</h2>
    <div class="policy-body">
        大多数时候需要等到某次新订货才会知晓该款商品烟草公司已经暂停供货或厂家永久停产，确认情况后会在1个工作日内邮件通知未发货订单涉及该商品的顾客，此类情况顾客可以选择同价更换，或全额退款。
    </div>

    <h2>5、关于退运</h2>
    <div class="policy-body">
        <ul>
            <li>跨国购物具有特殊性，勿拒收包裹，退运会被日本物流另行收取退运相关费用，加之处理退运需店内安排专人办理相关手续，故退运的综合成本会较高。</li>
            <li>退运需要的时间会远大于正常寄送（常见为1-3个月），商品新鲜度下降大，不能用于再售卖给其他顾客，故<span class="text-danger">不能退款</span>。</li>
            <li>退回完成后，若需再次寄出需联系客服支付相应的成本费用（需完成退运才能最终确定金额，但多数情况下不会超过2倍运费），也可委托他人到店自取（需提前联系客服），已完成退回的包裹未额外支付保管费用的情况下通常为您保留30天，过期视为放弃将销毁。</li>
            <li>物品退回有较小概率被日本海关当作进口物品收取高额税费，遇此情况会导致退运处理成本大于包裹本身价值，将同意日本海关销毁。</li>
            <li>请务必谨慎下单，勿冲动消费，提前向您当地海关充分了解海淘相关规定，<span class="text-success">避免产生退运给您带来损失</span>。</li>
        </ul>
    </div>

    <h2>6、关于发错货、丢件</h2>
    <div class="policy-body">
        <p><strong>包裹已收到，但不是我的订单：</strong>有极小概率物流给包裹贴错面单，请在站内“<a href="{{ route('support.feedbacks.create') }}">联系咨询</a>”功能与我们联系，我们会协调您与正确的收件人互换包裹，并承担由此产生的额外费用。</p>
        <p><strong>包裹已收到，但部分商品品种有错：</strong>有极小概率人工配货出错，请在站内“<a href="{{ route('support.feedbacks.create') }}">联系咨询</a>”功能与我们联系。</p>
        <p><strong>包裹已收到，但部分商品数量不足：</strong>请与订单中提供发货照片比对，如果发货照片也同样缺少，请在站内“<a href="{{ route('support.feedbacks.create') }}">联系咨询</a>”功能与我们联系；如果发货照片里数量正确，说明包裹通过海关后在您当地发生失窃，请尽快与您当地物流联系。</p>
        <p><strong>未收到包裹：</strong>国际包裹寄送耗时会比较久，2、3天物流记录未更新是很正常的情况，特别是如果入境口岸和您当地海关业务繁忙可能被延后处理数日，往年年底至春节中国海关甚至有过延后30日左右才轮上受理的特例存在。真正的包裹丢失概率小于万分之一，建议耐心等待物流记录更新。如果是着急使用，不建议通过海淘购买。</p>
    </div>
</div>
@endsection
