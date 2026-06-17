@php
  $b = $breakdown ?? [];
  $fmtJpy = function ($amount) {
      return '￥'.number_format((float) $amount, 2, '.', '');
  };
  $fmtCny = function ($amount) {
      return '￥'.number_format((float) $amount, 2, '.', '');
  };
@endphp
<div class="order-fee-breakdown">
  <h4 style="margin: 0 0 12px; font-size: 15px;">金额明细</h4>
  <table class="table table-condensed" style="margin-bottom: 0; max-width: 520px; background: #f8fafc;">
    <tbody>
      <tr>
        <td style="width: 42%; border-color: #e8edf2;">商品原价小计</td>
        <td style="border-color: #e8edf2;">{{ $fmtJpy($b['items_subtotal'] ?? 0) }}</td>
      </tr>
      @if(!empty($b['has_coupon']))
      <tr>
        <td style="border-color: #e8edf2;">
          优惠券抵扣
          @if(!empty($b['coupon_code']))
            <br><span class="text-muted" style="font-size: 12px;">券码 {{ $b['coupon_code'] }} · {{ $b['coupon_description'] }}</span>
          @endif
        </td>
        <td style="border-color: #e8edf2; color: #e74c3c;">
          @if((float) ($b['coupon_discount'] ?? 0) > 0)
            -{{ $fmtJpy($b['coupon_discount']) }}
          @else
            <span class="text-muted">已使用优惠券</span>
          @endif
        </td>
      </tr>
      <tr>
        <td style="border-color: #e8edf2;">折后商品费</td>
        <td style="border-color: #e8edf2;">{{ $fmtJpy($b['goods_after_discount'] ?? 0) }}</td>
      </tr>
      @endif
      @if(is_site_mode_a())
      <tr>
        <td style="border-color: #e8edf2;">劳务费（{{ (int) round(config('site.service_fee_rate', 0.15) * 100) }}%）</td>
        <td style="border-color: #e8edf2;">{{ $fmtJpy($b['service_fee'] ?? 0) }}</td>
      </tr>
      <tr>
        <td style="border-color: #e8edf2;">
          国际运费（EMS）
          @if(!empty($b['ems_weight_grams']))
            <br><span class="text-muted" style="font-size: 12px;">
              计费重量 {{ (int) $b['ems_weight_grams'] }}g
              @if(!empty($b['ems_zone']))
                · {{ $b['ems_zone'] }}
              @endif
            </span>
          @endif
        </td>
        <td style="border-color: #e8edf2;">{{ $fmtJpy($b['ems_shipping_fee'] ?? 0) }}</td>
      </tr>
      <tr>
        <td style="border-color: #e8edf2;">打包费</td>
        <td style="border-color: #e8edf2;">{{ $fmtJpy($b['packaging_fee'] ?? 0) }}</td>
      </tr>
      @if(!empty($b['shipping_mode_label']))
      <tr>
        <td style="border-color: #e8edf2;">寄送模式</td>
        <td style="border-color: #e8edf2;">{{ $b['shipping_mode_label'] }}</td>
      </tr>
      @endif
      @endif
      <tr style="background: #fff;">
        <td style="border-color: #e8edf2;"><strong>应付总额（日元）</strong></td>
        <td style="border-color: #e8edf2;"><strong>{{ $fmtJpy($b['total_jpy'] ?? 0) }}</strong></td>
      </tr>
      @if($order->paid_at && !empty($b['payment_cny']))
      <tr style="background: #fff8e6;">
        <td style="border-color: #f0d9a8;"><strong>实付金额（人民币）</strong></td>
        <td style="border-color: #f0d9a8;"><strong style="color: #c0392b;">{{ $fmtCny($b['payment_cny']) }}</strong></td>
      </tr>
      @if(!empty($b['exchange_rate']))
      <tr>
        <td style="border-color: #e8edf2;">结算汇率</td>
        <td style="border-color: #e8edf2;">1 人民币 = {{ number_format((float) $b['exchange_rate'], 4, '.', '') }} 日元</td>
      </tr>
      @endif
      @endif
    </tbody>
  </table>
  @if(empty($b['has_fee_details']))
    <p class="help-block" style="margin: 8px 0 0;">该订单未保存完整费用快照，部分明细为根据商品行推算。</p>
  @endif
</div>
