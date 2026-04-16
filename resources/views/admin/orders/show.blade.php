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
      @php
        $currentTrackingNo = trim((string) ($order->tracking_no ?: data_get($order->ship_data, 'express_no', '')));
      @endphp
      @if($order->paid_at && $currentTrackingNo === '')
      <tr>
        <td>运单提醒</td>
        <td colspan="3">
          <div class="alert alert-danger" style="margin-bottom: 0; padding: 8px 12px;">
            当前订单尚未填写运单号。用户端订单列表会显示“待上传”，请尽快在上方“履约操作”中补充物流单号并保存。
          </div>
        </td>
      </tr>
      @endif
      <tr>
        <td>履约操作</td>
        <td colspan="3">
          <form action="{{ route('admin.orders.update_fulfillment', [$order->id]) }}" method="post" enctype="multipart/form-data" class="form-horizontal">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <div class="form-group">
              <label class="col-sm-2 control-label">实物照片</label>
              <div class="col-sm-10">
                <input type="file" name="fulfillment_photo" accept="image/*" class="form-control" style="padding-top: 3px;">
                <p class="help-block" style="margin-bottom: 0;">上传商品实拍图，文件将保存到 storage/app/private/orders。</p>
                @if(!empty($order->fulfillment_photo))
                  <p class="help-block" style="margin-top: 6px;">当前已上传：{{ $order->fulfillment_photo }}</p>
                @endif
              </div>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
              <div class="col-sm-offset-2 col-sm-10">
                <button type="submit" class="btn btn-success">保存履约信息</button>
              </div>
            </div>
          </form>
        </td>
      </tr>
        <td>{{ $order->user->name }}</td>
        <td>支付时间：</td>
        <td>{{ $order->paid_at->format('Y-m-d H:i:s') }}</td>
      </tr>
      <tr>
        <td>支付方式：</td>
        <td>{{ $order->payment_method }}</td>
        <td>支付渠道单号：</td>
        <td>{{ $order->payment_no }}</td>
      </tr>
      <tr>
        <td>{{ is_site_mode_b() ? '国内转寄地址' : '收货地址' }}</td>
        <td colspan="3">{{ $order->address['address'] }} {{ $order->address['zip'] }} {{ $order->address['contact_name'] }} {{ $order->address['contact_phone'] }}</td>
      </tr>
      <tr>
        <td>受理状态</td>
        <td>
          @php
            $acceptanceStatus = $order->acceptance_status;
            $acceptanceText = data_get(\App\Models\Order::$acceptanceStatusMap, $acceptanceStatus, '未受理');
            $acceptanceLabelClass = $acceptanceStatus === \App\Models\Order::ACCEPTANCE_STATUS_ACCEPTED ? 'label-success' : 'label-warning';
          @endphp
          <span class="label {{ $acceptanceLabelClass }}">{{ $acceptanceText }}</span>
        </td>
        <td colspan="2">
          @if($order->paid_at)
            <button type="button" class="btn btn-xs btn-success" id="btn-mark-accepted">标注为已受理</button>
            <button type="button" class="btn btn-xs btn-warning" id="btn-mark-pending" style="margin-left: 6px;">标注为未受理</button>
          @else
            <span class="text-muted">未支付订单不可标注</span>
          @endif
        </td>
      </tr>
      @php
        $changeHistory = collect(data_get($order->extra, 'change_info_history', []))->values()->reverse();
      @endphp
      @if($changeHistory->count() > 0)
      <tr>
        <td>信息变更历史</td>
        <td colspan="3">
          <div style="max-height: 420px; overflow-y: auto;">
            @foreach($changeHistory as $index => $entry)
              @php
                $beforeAddress = data_get($entry, 'before.address', []);
                $afterAddress = data_get($entry, 'after.address', []);
                $beforeRemark = data_get($entry, 'before.remark', null);
                $afterRemark = data_get($entry, 'after.remark', null);

                if (empty($beforeAddress) && empty($afterAddress) && isset($entry['address'])) {
                    $afterAddress = (array) data_get($entry, 'address', []);
                    $afterRemark = data_get($entry, 'remark', null);
                }

                $beforeText = trim(implode(' ', array_filter([
                    data_get($beforeAddress, 'address'),
                    data_get($beforeAddress, 'zip'),
                    data_get($beforeAddress, 'contact_name'),
                    data_get($beforeAddress, 'contact_phone'),
                ])));
                $afterText = trim(implode(' ', array_filter([
                    data_get($afterAddress, 'address'),
                    data_get($afterAddress, 'zip'),
                    data_get($afterAddress, 'contact_name'),
                    data_get($afterAddress, 'contact_phone'),
                ])));
              @endphp

              <div style="border: 1px solid #f0f0f0; border-radius: 6px; padding: 10px; margin-bottom: 10px; background: #fafafa;">
                <div style="font-weight: 600; margin-bottom: 8px;">
                  变更 #{{ $changeHistory->count() - $index }}
                  <span style="font-weight: 400; color: #888; margin-left: 8px;">
                    时间：{{ data_get($entry, 'changed_at', '-') }}
                  </span>
                  <span style="font-weight: 400; color: #888; margin-left: 8px;">
                    用户ID：{{ data_get($entry, 'changed_by', '-') }}
                  </span>
                </div>

                <table class="table table-condensed" style="margin-bottom: 0; background: #fff;">
                  <thead>
                    <tr>
                      <th style="width: 90px;">字段</th>
                      <th>变更前</th>
                      <th>变更后</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>收货信息</td>
                      <td>{{ $beforeText !== '' ? $beforeText : '-' }}</td>
                      <td>{{ $afterText !== '' ? $afterText : '-' }}</td>
                    </tr>
                    <tr>
                      <td>备注</td>
                      <td>{{ $beforeRemark !== null && $beforeRemark !== '' ? $beforeRemark : '-' }}</td>
                      <td>{{ $afterRemark !== null && $afterRemark !== '' ? $afterRemark : '-' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            @endforeach
          </div>
        </td>
      </tr>
      @endif
      @php
        $swapHistory = collect(data_get($order->extra, 'swap_item_history', []))->values()->reverse();
      @endphp
      @if($swapHistory->count() > 0)
      <tr>
        <td>商品调换历史</td>
        <td colspan="3">
          <div style="max-height: 420px; overflow-y: auto;">
            @foreach($swapHistory as $index => $entry)
              @php
                $before = (array) data_get($entry, 'before', []);
                $after = (array) data_get($entry, 'after', []);
              @endphp

              <div style="border: 1px solid #f0f0f0; border-radius: 6px; padding: 10px; margin-bottom: 10px; background: #fafafa;">
                <div style="font-weight: 600; margin-bottom: 8px;">
                  调换 #{{ $swapHistory->count() - $index }}
                  <span style="font-weight: 400; color: #888; margin-left: 8px;">时间：{{ data_get($entry, 'changed_at', '-') }}</span>
                  <span style="font-weight: 400; color: #888; margin-left: 8px;">用户ID：{{ data_get($entry, 'changed_by', '-') }}</span>
                </div>

                <table class="table table-condensed" style="margin-bottom: 0; background: #fff;">
                  <thead>
                    <tr>
                      <th style="width: 90px;">字段</th>
                      <th>变更前</th>
                      <th>变更后</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>商品</td>
                      <td>{{ trim((string) data_get($before, 'product_title', '')) }} {{ trim((string) data_get($before, 'sku_title', '')) }} / {{ data_get($before, 'price', '-') }}</td>
                      <td>{{ trim((string) data_get($after, 'product_title', '')) }} {{ trim((string) data_get($after, 'sku_title', '')) }} / {{ data_get($after, 'price', '-') }}</td>
                    </tr>
                    <tr>
                      <td>数量</td>
                      <td>{{ data_get($before, 'amount', '-') }}</td>
                      <td>{{ data_get($after, 'amount', '-') }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            @endforeach
          </div>
        </td>
      </tr>
      @endif
      <tr>
        <td rowspan="{{ $order->items->count() + 1 }}">商品列表</td>
        <td>商品名称</td>
        <td>单价</td>
        <td>数量</td>
      </tr>
      @foreach($order->items as $item)
      <tr>
        <td>{{ $item->product->title }} {{ $item->productSku->title }}</td>
        <td>{{ number_format($item->price, 2, '.', '') }}日元</td>
        <td>{{ $item->amount }}</td>
      </tr>
      @endforeach
      <tr>
        <td>订单金额：</td>
        <td>{{ number_format($order->total_amount, 2, '.', '') }}日元</td>
        <!-- 这里也新增了一个发货状态 -->
        <td>{{ is_site_mode_b() ? '履行状态：' : '发货状态：' }}</td>
        <td>{{ is_site_mode_b() ? $order->display_status : \App\Models\Order::$shipStatusMap[$order->ship_status] }}</td>
      </tr>
      <!-- 订单发货开始 -->
      <!-- 如果订单未发货，展示发货表单 -->
      @if($order->ship_status === \App\Models\Order::SHIP_STATUS_PENDING)
      @if($order->refund_status !== \App\Models\Order::REFUND_STATUS_SUCCESS)
      <tr>
        <td colspan="4">
          <form action="{{ route('admin.orders.ship', [$order->id]) }}" method="post" class="form-inline">
            <!-- 别忘了 csrf token 字段 -->
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            @if(is_site_mode_b())
            <div class="form-group {{ $errors->has('express_company') ? 'has-error' : '' }}">
              <label for="express_company" class="control-label">代购人</label>
              <input type="text" id="express_company" name="express_company" value="" class="form-control" placeholder="输入代购人">
              @if($errors->has('express_company'))
                @foreach($errors->get('express_company') as $msg)
                  <span class="help-block">{{ $msg }}</span>
                @endforeach
              @endif
            </div>
            @else
            <div class="form-group {{ $errors->has('express_company') ? 'has-error' : '' }}">
              <label for="express_company" class="control-label">物流公司</label>
              <select id="express_company" name="express_company" class="form-control" required>
                <option value="">-- 请选择 --</option>
                <option value="EMS">EMS（日本邮政）</option>
                <option value="顺丰">顺丰</option>
              </select>
              @if($errors->has('express_company'))
                @foreach($errors->get('express_company') as $msg)
                  <span class="help-block">{{ $msg }}</span>
                @endforeach
              @endif
            </div>
            @endif
            <div class="form-group {{ $errors->has('express_company') ? 'has-error' : '' }}">
              <label for="express_no" class="control-label">{{ is_site_mode_b() ? '转寄单号' : '物流单号' }}</label>
              <input type="text" id="express_no" name="express_no" value="" class="form-control" placeholder="{{ is_site_mode_b() ? '输入转寄单号' : '输入物流单号' }}">
              @if($errors->has('express_no'))
                @foreach($errors->get('express_no') as $msg)
                  <span class="help-block">{{ $msg }}</span>
                @endforeach
              @endif
            </div>
            <button type="submit" class="btn btn-success" id="ship-btn">{{ is_site_mode_b() ? '标记进入转寄' : '发货' }}</button>
          </form>
        </td>
      </tr>
      @endif
      @else
      <!-- 否则展示物流公司和物流单号 -->
      <tr>
        <td>{{ is_site_mode_b() ? '代购人：' : '物流公司：' }}</td>
        <td>{{ $order->ship_data['express_company'] }}</td>
        <td>{{ is_site_mode_b() ? '转寄单号：' : '物流单号：' }}</td>
        <td>{{ $order->ship_data['express_no'] }}</td>
      </tr>
      @endif
      <!-- 订单发货结束 -->
      @if($order->refund_status !== \App\Models\Order::REFUND_STATUS_PENDING)
      <tr>
        <td>退款状态：</td>
        <td colspan="2">{{ \App\Models\Order::$refundStatusMap[$order->refund_status] }}，理由：{{ $order->extra['refund_reason'] }}</td>
        <td>
        <!-- 如果订单退款状态是已申请，则展示处理按钮 -->
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
  function markAcceptance(status) {
    $.ajax({
      url: '{{ route('admin.orders.mark_acceptance', [$order->id]) }}',
      type: 'POST',
      data: JSON.stringify({
        status: status,
        _token: LA.token,
      }),
      contentType: 'application/json',
      success: function () {
        swal({
          title: '操作成功',
          type: 'success'
        }, function() {
          location.reload();
        });
      },
      error: function (xhr) {
        var msg = '受理状态更新失败';
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        swal(msg, '', 'error');
      }
    });
  }

  $('#btn-mark-accepted').click(function() {
    markAcceptance('accepted');
  });

  $('#btn-mark-pending').click(function() {
    markAcceptance('pending');
  });

  // 不同意 按钮的点击事件
  $('#btn-refund-disagree').click(function() {
  // 注意：Laravel-Admin 的 swal 是 v1 版本，参数和 v2 版本的不太一样
    swal({
      title: '输入拒绝退款理由',
      type: 'input',
      showCancelButton: true,
      closeOnConfirm: false,
      confirmButtonText: "确认",
      cancelButtonText: "取消",
    }, function(inputValue){
      // 用户点击了取消，inputValue 为 false
      // === 是为了区分用户点击取消还是没有输入
      if (inputValue === false) {
        return;
      }
      if (!inputValue) {
        swal('理由不能为空', '', 'error')
        return;
      }
      // Laravel-Admin 没有 axios，使用 jQuery 的 ajax 方法来请求
      $.ajax({
        url: '{{ route('admin.orders.handle_refund', [$order->id]) }}',
        type: 'POST',
        data: JSON.stringify({   // 将请求变成 JSON 字符串
          agree: false,  // 拒绝申请
          reason: inputValue,
          // 带上 CSRF Token
          // Laravel-Admin 页面里可以通过 LA.token 获得 CSRF Token
          _token: LA.token,
        }),
        contentType: 'application/json',  // 请求的数据格式为 JSON
        success: function (data) {  // 返回成功时会调用这个函数
          swal({
            title: '操作成功',
            type: 'success'
          }, function() {
            // 用户点击 swal 上的 按钮时刷新页面
            location.reload();
          });
        }
      });
    });
  });

  // 同意按钮的点击事件
  $('#btn-refund-agree').click(function() {
    swal({
      title: '确认要将款项退还给用户？',
      type: 'warning',
      showCancelButton: true,
      closeOnConfirm: false,
      confirmButtonText: "确认",
      cancelButtonText: "取消",
    }, function(ret){
      // 用户点击取消，不做任何操作
      if (!ret) {
        return;
      }
      $.ajax({
        url: '{{ route('admin.orders.handle_refund', [$order->id]) }}',
        type: 'POST',
        data: JSON.stringify({
          agree: true, // 代表同意退款
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