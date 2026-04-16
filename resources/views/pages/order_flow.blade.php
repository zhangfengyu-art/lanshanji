@extends('layouts.app')

@section('title', '下单流程')

@section('styles')
<style>
    .order-flow-wrap {
        max-width: 980px;
        margin: 16px auto 24px;
        color: #000;
    }

    .order-flow-wrap,
    .order-flow-wrap * {
        color: #000 !important;
    }

    .order-flow-wrap h1 {
        font-size: 44px;
        color: #1f6feb !important;
        font-weight: 700;
        margin-bottom: 18px;
    }

    .order-flow-wrap h2 {
        font-size: 28px;
        color: #000 !important;
        margin: 14px 0 10px;
        border-bottom: 1px solid #d6dde6;
        padding-bottom: 8px;
        font-weight: 700;
    }

    .order-flow-body {
        background: #f4f6f9;
        border-radius: 8px;
        padding: 16px 18px;
        line-height: 1.9;
        margin-bottom: 16px;
        font-size: 16px;
    }

    .order-flow-note {
        background: #eef6ff;
        border: 1px solid #d8e9ff;
        border-radius: 8px;
        padding: 12px 14px;
        margin-bottom: 16px;
        font-size: 16px;
        line-height: 1.8;
    }

    .order-flow-wrap p,
    .order-flow-wrap li {
        margin-bottom: 12px;
    }

    .order-flow-wrap p:last-child,
    .order-flow-wrap li:last-child {
        margin-bottom: 0;
    }

    .order-flow-wrap ul,
    .order-flow-wrap ol {
        margin-bottom: 0;
        padding-left: 26px;
    }

    .order-flow-wrap a {
        color: #0f4ca8 !important;
        text-decoration: underline;
    }

    .order-flow-wrap .text-danger {
        color: #dc3545 !important;
    }

    .order-flow-wrap .text-success {
        color: #198754 !important;
    }

    @media (max-width: 768px) {
        .order-flow-wrap {
            margin-top: 10px;
            margin-bottom: 16px;
            padding: 0 12px;
        }

        .order-flow-wrap h1 {
            font-size: 32px;
            line-height: 1.35;
            margin-bottom: 14px;
        }

        .order-flow-wrap h2 {
            font-size: 22px;
        }

        .order-flow-body,
        .order-flow-note {
            font-size: 15px;
            line-height: 1.8;
            padding: 12px 14px;
        }
    }
</style>
@endsection

@section('content')
<div class="container order-flow-wrap">
    <h1 class="text-center">下单流程说明</h1>

    <div class="order-flow-note">
        本店目前提供“EMS自缴税”、“顺丰含税”两种寄送模式。
        <br>
        <span>这里的“税”指收件人所在地海关收取的税费，商品本身都是日本国内完税版本，非机场免税店商品。</span>
    </div>

    <h2>EMS自缴税寄送</h2>
    <div class="order-flow-body">
        <p>大分类明确标注EMS的商品，采用“EMS自缴税”方式发货，此模式下商品报价不含运费，运费在结算的时候单独自动计算，有助于减少您所在地海关收取的税费（绝大部分地区海关认可征税排除运费，大部分地区海关认可征税排除服务费）。</p>
        <p>因需要下单购买的人数太多，超出本地与周边邮局每日揽收量，故需要提前报名参与下单名额的随机抽取（前往下单名额报名）（可直接下单的烟草套餐大分类除外）。</p>
        <p>获得下单名额并完成订单支付后，并不能立刻发货，因为还有大量更早时间已下单但未被查阅受理的订单，所以需要先排队等候。</p>
        <p>2026年起，EMS发货订单拆分为A、B两个排队等候队列，每日包裹揽收额度平均分配给这2个队列，B队列为烟草套餐与服务费为0的订单，A队列为其它订单，若B队列已无等待发货的订单则当日剩余包裹揽收额度会给予A队列。</p>
        <p>排到受理您的订单后，会确认订单中所有商品是否有库存，有货会直接发货，反之需要向烟草公司订货并等待配送到店（尤其热门烟丝，极易被更靠前订单用完，经常数百个订单等待新到货，烟草公司一周配送2-3次，但烟草公司存量也并不充足，经常实际配送少于订货数量），订货后一般在7天内能发货（遇日本节假日顺延）。</p>
        <p><strong>请注意：</strong></p>
        <p><span class="text-danger">商品可下单仅代表本店愿意接单，不代表店内有现货。</span></p>
        <p>排队轮到受理您的订单后，若日本烟草公司能在接下来7天内供货不被视为缺货。</p>
        <p>缺货订单提供可全额退款故不会为缺货订单预留订单内其它商品，热门品种多的订单有可能会反复缺货。</p>
        <p>同一订单的所有商品需要等待一起发货，不提供拆分订单、不接受增减商品。</p>
        <p>实在觉得太久等不了可以申请退款（查看退款相关规则），催促没有用，不会因为您催促就立刻有货，也不会因为您催促就搁着前面的订单不管先给您发货，如果您实在太着急，本店能主动实现的只有主动退款给您，并今后不再承接您的订单。</p>
    </div>

    <h2>顺丰含税包邮寄送</h2>
    <div class="order-flow-body">
        <p>大分类明确标注顺丰的商品，采用“顺丰含税”方式发货，此模式下商品报价已包含中国海关收取的税费，也包含运费，发货后除遇海关通知您在线核验身份需要自行配合以外，等着收货即可，无其它费用产生。</p>
        <p><strong>加热设备</strong>为“顺丰含税”方式发货。</p>
        <p>烟草商品的含税发货名额比较稀缺，不定期会在站内推出特定品种烟草商品“含税包邮”活动，没有预告，没有通知，需要正好赶上才能参与购买。</p>
        <p><strong>请注意：</strong></p>
        <p>“顺丰含税”方式发货，手续繁琐，且通关后为陆运，全程需要至少十几天，极端情况甚至需要二十天以上，肯定比“EMS自缴税”慢，且通过海关前由日本顺丰承运，完成清关通过海关后才会交付中国顺丰，请勿向中国顺丰催促，如果中国顺丰向日本顺丰投诉可能导致证件号码今后被本店这边物流拉黑（会导致EMS也发不了，是同一家本地物流在承接本店所有包裹的发货），接受不了速度慢请一定不要下单“含税包邮”商品，性子急的建议购买“EMS自缴税”方式寄送的商品，可以随便催邮政，邮政不会找日本这边告状……</p>
        <p>中国西藏地址，顺丰含税不可用，会换用申通含税发货，需注意事项同顺丰。其它地区，如遇特殊情况无法顺丰含税发货，可能临时换用其它可含税发货的物流发货。</p>
        <p>含税包邮购买仅限中国大陆18位身份证。港/澳/台居民来往大陆通行证、港/澳/台居民居住证不可用，物流会拒收（非歧视，是物流公司无权限为这些证件办理代缴税）。</p>
    </div>
</div>
@endsection
