@php
  $canShip = $order->paid_at && $order->refund_status !== \App\Models\Order::REFUND_STATUS_SUCCESS;
  $shipNo = trim((string) data_get($order->ship_data, 'express_no', ''));
  $shipCompany = trim((string) data_get($order->ship_data, 'express_company', ''));
  $isPending = $order->ship_status === \App\Models\Order::SHIP_STATUS_PENDING;
  $carriers = site_express_carrier_options();
  $defaultCarrier = $carriers[0];
  $mode = trim((string) data_get($order->extra, 'fee_details.shipping_mode', data_get($order->extra, 'shipping_mode', '')));
  if ($mode === \App\Services\ShippingModeService::MODE_EMS) {
      foreach ($carriers as $carrier) {
          if (stripos($carrier, 'EMS') !== false) {
              $defaultCarrier = $carrier;
              break;
          }
      }
  }
@endphp
<div class="order-quick-ship-cell" style="min-width:132px;">
  @if(!$canShip)
    <span class="text-muted">—</span>
  @elseif(!$isPending && $shipNo !== '')
    <div style="font-size:11px;color:#64748b;">{{ e($shipCompany) }}</div>
    <div style="font-size:12px;font-weight:600;word-break:break-all;">{{ e($shipNo) }}</div>
    <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-xs btn-default" style="margin-top:4px;">修改</a>
  @elseif(!$isPending)
    <span class="text-muted">已发货</span>
    <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-xs btn-default" style="margin-top:4px;">详情</a>
  @else
    <form action="{{ route('admin.orders.quick_ship', $order) }}" method="post" class="order-quick-ship-form" data-pjax="false" style="margin:0;">
      <input type="hidden" name="_token" value="{{ csrf_token() }}">
      @if(is_site_mode_a())
        <select name="express_company" class="form-control input-sm" style="width:100%;margin-bottom:4px;font-size:11px;padding:2px 6px;height:26px;">
          @foreach($carriers as $carrier)
            <option value="{{ $carrier }}" {{ $carrier === $defaultCarrier ? 'selected' : '' }}>{{ $carrier }}</option>
          @endforeach
        </select>
      @else
        <input type="text" name="express_company" class="form-control input-sm" placeholder="代购人" style="width:100%;margin-bottom:4px;font-size:11px;height:26px;">
      @endif
      <input type="text" name="express_no" class="form-control input-sm" placeholder="{{ is_site_mode_b() ? '转寄单号' : '物流单号' }}" style="width:100%;margin-bottom:4px;font-size:11px;height:26px;" required>
      <button type="submit" class="btn btn-xs btn-success btn-block order-quick-ship-btn">
        <i class="fa fa-truck"></i> 保存并发货
      </button>
    </form>
  @endif
</div>
