<div class="box box-info">
  <div class="box-header with-border">
    <h3 class="box-title">订单流水号：{{ $order->no }}</h3>
    <div class="box-tools">
      <div class="btn-group pull-right" style="margin-right: 10px">
        <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-default"><i class="fa fa-list"></i> 列表</a>
      </div>
    </div>
  </div>
  <div class="box-body">
    @if($errors->any())
      <div class="alert alert-danger alert-dismissible" style="margin-bottom: 12px;">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        @foreach($errors->all() as $msg)
          <div>{{ $msg }}</div>
        @endforeach
      </div>
    @endif
    <table class="table table-bordered">
      <tbody>
      <tr>
        <td>买家：</td>
        <td>{{ $order->buyer_label }}</td>
        <td>支付时间：</td>
        <td>{{ $order->paid_at ? $order->paid_at->format('Y-m-d H:i:s') : '—' }}</td>
      </tr>
      <tr>
        <td>支付方式：</td>
        <td>
          @if($order->payment_method === 'wechat')
            微信支付
          @elseif($order->payment_method === 'alipay')
            支付宝
          @else
            {{ $order->payment_method }}
          @endif
        </td>
        <td>支付渠道单号：</td>
        <td>{{ $order->payment_no }}</td>
      </tr>
      <tr>
        <td>{{ is_site_mode_b() ? '国内转寄地址' : '收货地址' }}</td>
        <td colspan="3">{{ data_get($order->address, 'address', '') }} {{ data_get($order->address, 'zip', '') }} {{ data_get($order->address, 'contact_name', '') }} {{ data_get($order->address, 'contact_phone', '') }}</td>
      </tr>
      <tr>
        <td rowspan="{{ $order->items->count() + 1 }}">商品列表</td>
        <td>商品名称</td>
        <td>单价</td>
        <td>数量</td>
      </tr>
      @foreach($order->items as $item)
      <tr>
        <td>
          {{ optional($item->product)->title ?: ($item->product_id ? '商品#'.$item->product_id.'（已下架）' : '—') }}
          @if(optional($item->productSku)->title)
            {{ optional($item->productSku)->title }}
          @endif
        </td>
        <td>￥{{ $item->price }}</td>
        <td>{{ $item->amount }}</td>
      </tr>
      @endforeach
      <tr>
        <td colspan="4" style="padding-top: 16px;">
          @include('admin.orders._fee_breakdown', ['breakdown' => $feeBreakdown ?? [], 'order' => $order])
        </td>
      </tr>
      <tr>
        <td>{{ is_site_mode_b() ? '履行状态：' : '发货状态：' }}</td>
        <td colspan="3">{{ is_site_mode_b() ? $order->display_status : \App\Models\Order::$shipStatusMap[$order->ship_status] }}</td>
      </tr>
      @php
        $shipCompany = old('express_company', data_get($order->ship_data, 'express_company', ''));
        $shipNo = old('express_no', data_get($order->ship_data, 'express_no', ''));
        $shipStatusValue = old('ship_status', $order->ship_status);
        $expressCarrierOptions = site_express_carrier_options();
        if (is_site_mode_a() && $shipCompany !== '' && !in_array($shipCompany, $expressCarrierOptions, true)) {
            $shipCompany = $expressCarrierOptions[0];
        }
        $shipStatusOptions = is_site_mode_b()
          ? [
              \App\Models\Order::SHIP_STATUS_PENDING => '待履行/采购中',
              \App\Models\Order::SHIP_STATUS_DELIVERED => '已入关/转寄中',
              \App\Models\Order::SHIP_STATUS_RECEIVED => '已签收（任务完成）',
            ]
          : \App\Models\Order::$shipStatusMap;
      @endphp
      @if($order->paid_at && !is_site_mode_b() && !$order->closed)
      @php
        $fulfillment = app(\App\Services\OrderFulfillmentService::class);
        $fulfillmentStage = $fulfillment->resolveStage($order);
        $processingStarted = trim((string) data_get($order->extra, 'processing_started_at', '')) !== '';
        $lockedAt = trim((string) data_get($order->extra, 'locked_at', '')) !== '';
      @endphp
      <tr>
        <td>履约阶段：</td>
        <td colspan="3">
          <strong>{{ $fulfillment->stageLabel($order) }}</strong>（{{ $fulfillmentStage }}）
          · 自助改址已用 {{ (int) data_get($order->extra, 'address_change_count', 0) }}/2 次
          @if($processingStarted)
            <span class="text-muted"> · 开始处理：{{ data_get($order->extra, 'processing_started_at') }}</span>
          @endif
          @if($lockedAt)
            <span class="text-muted"> · 锁定：{{ data_get($order->extra, 'locked_at') }}</span>
          @endif
        </td>
      </tr>
      <tr>
        <td>履约操作：</td>
        <td colspan="3" style="padding-bottom: 12px;">
          @if(!$processingStarted && $fulfillmentStage !== \App\Services\OrderFulfillmentService::STAGE_S4)
            <form action="{{ route('admin.orders.start_processing', ['order' => $order->id]) }}" method="post" data-pjax="false" style="display:inline-block; margin-right: 8px;">
              <input type="hidden" name="_token" value="{{ csrf_token() }}">
              <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('确认开始处理？用户将无法再自助改址。');">开始处理（S1→S2）</button>
            </form>
          @endif
          @if(!$lockedAt && !in_array($fulfillmentStage, [\App\Services\OrderFulfillmentService::STAGE_S4], true))
            <form action="{{ route('admin.orders.lock', ['order' => $order->id]) }}" method="post" data-pjax="false" style="display:inline-block; margin-right: 8px;">
              <input type="hidden" name="_token" value="{{ csrf_token() }}">
              <button type="submit" class="btn btn-default btn-sm">锁定订单（→S3）</button>
            </form>
          @endif
          @if($lockedAt && !$order->hasFulfillmentPhoto())
            <form action="{{ route('admin.orders.unlock', ['order' => $order->id]) }}" method="post" data-pjax="false" style="display:inline-block; margin-right: 8px;">
              <input type="hidden" name="_token" value="{{ csrf_token() }}">
              <button type="submit" class="btn btn-default btn-sm">解除锁定</button>
            </form>
          @endif
          @php $atWarehouse = trim((string) data_get($order->extra, 'logistics_warehouse_at', '')) !== ''; @endphp
          @if(!$atWarehouse && in_array($fulfillmentStage, [\App\Services\OrderFulfillmentService::STAGE_S2, \App\Services\OrderFulfillmentService::STAGE_S3], true))
            <form action="{{ route('admin.orders.mark_logistics_warehouse', ['order' => $order->id]) }}" method="post" data-pjax="false" style="display:inline-block; margin-right: 8px;">
              <input type="hidden" name="_token" value="{{ csrf_token() }}">
              <button type="submit" class="btn btn-default btn-sm" onclick="return confirm('确认包裹已送往物流仓库？标记后用户将无法按规则取消订单。');">标记：已送往物流仓库</button>
            </form>
          @endif
          @if($atWarehouse)
            <span class="text-danger" style="margin-left: 8px;">已送往物流仓库：{{ data_get($order->extra, 'logistics_warehouse_at') }}</span>
          @endif
          <span class="help-block" style="margin: 6px 0 0;">上传履约实拍图也会进入 S3。已发货为 S4。送往物流仓库后原则上不可取消。</span>
        </td>
      </tr>
      @endif
      @if($order->paid_at && $order->refund_status !== \App\Models\Order::REFUND_STATUS_SUCCESS)
      <tr>
        <td colspan="4">
          @if(session('success'))
            <div class="alert alert-success alert-dismissible" style="margin-bottom: 12px;">
              <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
              {{ session('success') }}
            </div>
          @endif
          <form action="{{ route('admin.orders.ship', [$order->id]) }}" method="post" data-pjax="false" class="form-inline" style="flex-wrap: wrap; gap: 8px 12px;">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <div class="form-group {{ $errors->has('ship_status') ? 'has-error' : '' }}">
              <label for="ship_status" class="control-label">{{ is_site_mode_b() ? '履行状态' : '发货状态' }}</label>
              <select id="ship_status" name="ship_status" class="form-control" required>
                @foreach($shipStatusOptions as $value => $label)
                  <option value="{{ $value }}" {{ $shipStatusValue === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
              @if($errors->has('ship_status'))
                @foreach($errors->get('ship_status') as $msg)
                  <span class="help-block">{{ $msg }}</span>
                @endforeach
              @endif
            </div>
            <div class="form-group {{ $errors->has('express_company') ? 'has-error' : '' }}">
              <label for="express_company" class="control-label">{{ is_site_mode_b() ? '代购人' : '物流公司' }}</label>
              @if(is_site_mode_a())
              <select id="express_company" name="express_company" class="form-control" required style="min-width: 180px;">
                @foreach($expressCarrierOptions as $carrier)
                  <option value="{{ $carrier }}" {{ $shipCompany === $carrier ? 'selected' : '' }}>{{ $carrier }}</option>
                @endforeach
              </select>
              @else
              <input type="text" id="express_company" name="express_company" value="{{ $shipCompany }}" class="form-control" placeholder="请输入代购人姓名或标识" style="min-width: 180px;">
              @endif
              @if($errors->has('express_company'))
                @foreach($errors->get('express_company') as $msg)
                  <span class="help-block">{{ $msg }}</span>
                @endforeach
              @endif
            </div>
            <div class="form-group {{ $errors->has('express_no') ? 'has-error' : '' }}">
              <label for="express_no" class="control-label">{{ is_site_mode_b() ? '转寄单号' : '物流单号' }}</label>
              <input type="text" id="express_no" name="express_no" value="{{ $shipNo }}" class="form-control" placeholder="{{ is_site_mode_b() ? '请输入转寄单号' : '请输入物流单号' }}" style="min-width: 220px;">
              @if($errors->has('express_no'))
                @foreach($errors->get('express_no') as $msg)
                  <span class="help-block">{{ $msg }}</span>
                @endforeach
              @endif
            </div>
            <button type="submit" class="btn btn-success" id="ship-btn">保存物流信息</button>
          </form>
          <p class="help-block" style="margin-top: 8px; margin-bottom: 0;">保存后用户订单详情页会同步显示发货状态、物流公司与运单号。</p>
        </td>
      </tr>
      @elseif($order->ship_data)
      <tr>
        <td>{{ is_site_mode_b() ? '代购人：' : '物流公司：' }}</td>
        <td>{{ data_get($order->ship_data, 'express_company', '-') }}</td>
        <td>{{ is_site_mode_b() ? '转寄单号：' : '物流单号：' }}</td>
        <td>{{ data_get($order->ship_data, 'express_no', '-') }}</td>
      </tr>
      @endif
      @if($order->paid_at)
      <tr>
        <td colspan="4">
          <h4 style="margin-top: 8px;">订单实拍图（用户端可见）</h4>
          @if($order->hasFulfillmentPhoto())
            <div style="margin-bottom: 12px;">
              <a href="{{ route('admin.orders.fulfillment_photo', $order) }}" target="_blank">
                <img src="{{ route('admin.orders.fulfillment_photo', $order) }}" alt="实拍预览" style="max-width: 240px; max-height: 180px; border: 1px solid #ddd; border-radius: 4px;">
              </a>
              <div style="margin-top: 6px;">
                <a href="{{ route('admin.orders.fulfillment_photo', $order) }}" target="_blank" class="btn btn-xs btn-default">查看原图</a>
              </div>
            </div>
          @else
            <p class="text-muted">尚未上传实拍图，用户订单页将显示「待上传」。</p>
          @endif
          <form action="{{ route('admin.orders.fulfillment_photo.upload', $order) }}" method="post" enctype="multipart/form-data" class="form-inline" style="margin-bottom: 8px;">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <div class="form-group">
              <label for="fulfillment_photo" class="control-label">上传/更换实拍图</label>
              <input type="file" id="fulfillment_photo" name="photo" accept="image/jpeg,image/png,image/webp" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">上传实拍图</button>
          </form>
          @if($order->hasFulfillmentPhoto())
          <form action="{{ route('admin.orders.fulfillment_photo.delete', $order) }}" method="post" style="display:inline;" onsubmit="return confirm('确认删除实拍图？用户端将不再显示。');">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="btn btn-danger btn-sm">删除实拍图</button>
          </form>
          @endif
        </td>
      </tr>
      @endif
      @if(is_site_mode_a() && $order->paid_at && $refundPreview)
      <tr>
        <td colspan="4">
          <h4 style="margin-top: 8px;">退款操作</h4>
          @if($order->refund_status !== \App\Models\Order::REFUND_STATUS_PENDING)
            <p><strong>退款状态：</strong>{{ \App\Models\Order::$refundStatusMap[$order->refund_status] }}
              @if(data_get($order->extra, 'manual_offline_refund'))
                <span class="label label-warning">线下私退完结</span>
              @endif
              @if(data_get($order->extra, 'refund_reason'))
                · {{ data_get($order->extra, 'refund_reason') }}
              @endif
              @if(data_get($order->extra, 'refund_amount_cny'))
                · 实退 ￥{{ number_format((float) data_get($order->extra, 'refund_amount_cny'), 2, '.', '') }}
              @endif
            </p>
            @if(data_get($order->extra, 'manual_offline_refund'))
              <p class="text-muted" style="margin-bottom: 12px;">
                线下私退标记时间：{{ data_get($order->extra, 'manual_offline_refund_at') }}
                @if(data_get($order->extra, 'manual_offline_refund_note'))
                  · 备注：{{ data_get($order->extra, 'manual_offline_refund_note') }}
                @endif
              </p>
            @endif
          @endif

          @if(
            $order->refund_status !== \App\Models\Order::REFUND_STATUS_SUCCESS
            && $order->refund_status !== \App\Models\Order::REFUND_STATUS_PROCESSING
            && !data_get($order->extra, 'manual_offline_refund')
          )
            <div class="well well-sm" style="margin-bottom: 16px; background: #fff8e6; border-color: #f0d9a8;">
              <strong>特殊情况：线下私退完结</strong>
              <p class="help-block" style="margin: 6px 0 10px;">
                适用于您已通过微信私聊等方式向客户转账退款、无法走平台「执行退款」或用户「全额秒退」的场景。
                点击后仅在本站标记订单已退款并关闭，<strong>不会</strong>向微信/支付宝发起原路退回。
              </p>
              <form action="{{ route('admin.orders.manual_offline_refund', ['order' => $order->id]) }}" method="post" data-pjax="false">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <div class="form-group" style="max-width: 480px;">
                  <label for="manual_offline_refund_note">备注（选填）</label>
                  <input type="text" name="note" id="manual_offline_refund_note" class="form-control" maxlength="500" placeholder="例如：2026-05-20 微信私聊已退 ￥100">
                </div>
                <button type="submit" class="btn btn-warning"
                  onclick="return confirm('确认标记为线下私退已完结？\n\n不会向支付渠道发起退款，仅用于您已私下完成退款的订单。');">
                  标记线下私退已完结
                </button>
              </form>
            </div>
          @endif

          @if($refundPreview['allowed'] || $order->refund_status === \App\Models\Order::REFUND_STATUS_APPLIED || $order->refund_status === \App\Models\Order::REFUND_STATUS_FAILED)
            <div class="alert alert-info" style="margin-bottom: 12px;">
              <strong>当前规则：</strong>{{ $refundPreview['policy_hint'] ?: $refundPreview['message'] }}<br>
              实付 ￥{{ number_format($refundPreview['pay_amount_cny'], 2, '.', '') }}
              @if($refundPreview['allowed'])
                → 预计退款 ￥{{ number_format($refundPreview['refund_amount_cny'], 2, '.', '') }}
                （取消费 ￥{{ number_format($refundPreview['cancellation_fee_cny'], 2, '.', '') }}）
              @endif
            </div>

            @if($order->refund_status !== \App\Models\Order::REFUND_STATUS_SUCCESS && $order->refund_status !== \App\Models\Order::REFUND_STATUS_PROCESSING)
            <form action="{{ route('admin.orders.handle_refund', ['order' => $order->id]) }}" method="post" data-pjax="false" id="admin-refund-form">
              <input type="hidden" name="_token" value="{{ csrf_token() }}">
              <div class="form-group">
                <label for="reason_code">退款原因</label>
                <select name="reason_code" id="reason_code" class="form-control" required style="max-width: 420px;">
                  <option value="">请选择</option>
                  @foreach($refundReasons as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                <label for="reason_note">备注（选填）</label>
                <input type="text" name="reason_note" id="reason_note" class="form-control" style="max-width: 420px;" placeholder="可补充说明">
              </div>
              @if($refundPreview['show_supplier_shortage'])
              <div class="checkbox">
                <label>
                  <input type="checkbox" name="supplier_cannot_supply" value="1"> 确认供应商近期无法正常供货（全额退款）
                </label>
              </div>
              @endif
              @if($refundPreview['requires_s4_approval'])
              <div class="checkbox">
                <label>
                  <input type="checkbox" name="s4_special_approval" value="1" id="s4_special_approval"> 已发货特批退款（需审批）
                </label>
              </div>
              <div class="form-group" id="s4_ratio_group" style="display:none;">
                <label for="s4_refund_ratio">特批退款比例</label>
                <select name="s4_refund_ratio" id="s4_refund_ratio" class="form-control" style="max-width: 200px;">
                  <option value="0.8">退款 80%（扣 20% 取消费）</option>
                  <option value="1">退款 100%（全额）</option>
                </select>
              </div>
              @endif
              <button type="submit" class="btn btn-danger" id="btn-execute-refund"
                @if(!$refundPreview['allowed']) disabled @endif
                onclick="return confirm('确认按当前规则执行退款？款项将原路退回，不可撤销。');">
                执行退款
              </button>
              @if(!$refundPreview['allowed'])
                <p class="help-block text-danger">{{ $refundPreview['message'] }}</p>
              @endif
            </form>
            @endif
          @else
            <p class="text-muted">{{ $refundPreview['message'] ?: '当前不可退款。' }}</p>
          @endif
        </td>
      </tr>
      @endif
      </tbody>
    </table>
  </div>
</div>

@if(is_site_mode_a() && $refundPreview && $refundPreview['requires_s4_approval'])
<script>
$(document).ready(function () {
  $('#s4_special_approval').on('change', function () {
    $('#s4_ratio_group').toggle(this.checked);
    $('#btn-execute-refund').prop('disabled', !this.checked);
  });
});
</script>
@endif
