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
        <td>订单金额：</td>
        <td>￥{{ $order->total_amount }}</td>
        <td>{{ is_site_mode_b() ? '履行状态：' : '发货状态：' }}</td>
        <td>{{ is_site_mode_b() ? $order->display_status : \App\Models\Order::$shipStatusMap[$order->ship_status] }}</td>
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
      @if($order->paid_at && !is_site_mode_b())
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
            <form action="{{ route('admin.orders.start_processing', $order) }}" method="post" style="display:inline-block; margin-right: 8px;">
              <input type="hidden" name="_token" value="{{ csrf_token() }}">
              <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('确认开始处理？用户将无法再自助改址。');">开始处理（S1→S2）</button>
            </form>
          @endif
          @if(!$lockedAt && !in_array($fulfillmentStage, [\App\Services\OrderFulfillmentService::STAGE_S4], true))
            <form action="{{ route('admin.orders.lock', $order) }}" method="post" style="display:inline-block; margin-right: 8px;">
              <input type="hidden" name="_token" value="{{ csrf_token() }}">
              <button type="submit" class="btn btn-default btn-sm">锁定订单（→S3）</button>
            </form>
          @endif
          @if($lockedAt && !$order->hasFulfillmentPhoto())
            <form action="{{ route('admin.orders.unlock', $order) }}" method="post" style="display:inline-block; margin-right: 8px;">
              <input type="hidden" name="_token" value="{{ csrf_token() }}">
              <button type="submit" class="btn btn-default btn-sm">解除锁定</button>
            </form>
          @endif
          <span class="help-block" style="margin: 6px 0 0;">上传履约实拍图也会进入 S3。已发货为 S4。</span>
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
          <form action="{{ route('admin.orders.ship', [$order->id]) }}" method="post" class="form-inline" style="flex-wrap: wrap; gap: 8px 12px;">
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
      @if($order->paid_at)
      <tr>
        <td colspan="4">
          <h4 style="margin-top: 8px;">购物凭据（EMS 明细书，用户端可下载）</h4>
          @if($order->hasShoppingReceipt())
            <div style="margin-bottom: 12px;">
              <a href="{{ route('admin.orders.shopping_receipt', $order) }}" target="_blank" class="btn btn-xs btn-default">查看/下载凭据</a>
            </div>
          @else
            <p class="text-muted">尚未上传购物凭据。</p>
          @endif
          <form action="{{ route('admin.orders.shopping_receipt.upload', $order) }}" method="post" enctype="multipart/form-data" class="form-inline" style="margin-bottom: 8px;">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <div class="form-group">
              <label for="shopping_receipt" class="control-label">上传/更换（PDF/JPG/PNG）</label>
              <input type="file" id="shopping_receipt" name="receipt" accept=".pdf,image/jpeg,image/png" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">上传凭据</button>
          </form>
          @if($order->hasShoppingReceipt())
          <form action="{{ route('admin.orders.shopping_receipt.delete', $order) }}" method="post" style="display:inline;" onsubmit="return confirm('确认删除购物凭据？');">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="btn btn-danger btn-sm">删除凭据</button>
          </form>
          @endif
        </td>
      </tr>
      @endif
      @if($order->refund_status !== \App\Models\Order::REFUND_STATUS_PENDING)
      <tr>
        <td>退款状态：</td>
        <td colspan="2">{{ \App\Models\Order::$refundStatusMap[$order->refund_status] }}，理由：{{ data_get($order->extra, 'refund_reason', '—') }}</td>
        <td>
        @if($order->refund_status === \App\Models\Order::REFUND_STATUS_APPLIED)
          <button class="btn btn-sm btn-success" id="btn-refund-agree">同意</button>
          <button class="btn btn-sm btn-danger" id="btn-refund-disagree">不同意</button>
        @endif
        </td>
      </tr>
      @endif
      </tbody>
    </table>
  </div>
</div>

<script>
$(document).ready(function() {
  $('#btn-refund-disagree').click(function() {
    swal({
      title: '输入拒绝退款理由',
      type: 'input',
      showCancelButton: true,
      closeOnConfirm: false,
      confirmButtonText: "确认",
      cancelButtonText: "取消",
    }, function(inputValue){
      if (inputValue === false) {
        return;
      }
      if (!inputValue) {
        swal('理由不能为空', '', 'error')
        return;
      }
      $.ajax({
        url: '{{ route('admin.orders.handle_refund', [$order->id]) }}',
        type: 'POST',
        data: JSON.stringify({
          agree: false,
          reason: inputValue,
          _token: LA.token,
        }),
        contentType: 'application/json',
        success: function (data) {
          swal({
            title: '操作成功',
            type: 'success'
          }, function() {
            location.reload();
          });
        }
      });
    });
  });

  $('#btn-refund-agree').click(function() {
    swal({
      title: '确认要将款项退还给用户？',
      type: 'warning',
      showCancelButton: true,
      closeOnConfirm: false,
      confirmButtonText: "确认",
      cancelButtonText: "取消",
    }, function(ret){
      if (!ret) {
        return;
      }
      $.ajax({
        url: '{{ route('admin.orders.handle_refund', [$order->id]) }}',
        type: 'POST',
        data: JSON.stringify({
          agree: true,
          _token: LA.token,
        }),
        contentType: 'application/json',
        success: function (data) {
          swal({
            title: '操作成功',
            type: 'success'
          }, function() {
            location.reload();
          });
        }
      });
    });
  });

});
</script>
