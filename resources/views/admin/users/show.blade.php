<style>
  .admin-user-panel {
    margin-bottom: 16px;
  }

  .admin-user-card {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    overflow: hidden;
    margin-bottom: 16px;
  }

  .admin-user-card .card-head {
    padding: 12px 14px;
    background: #fafafa;
    border-bottom: 1px solid #e5e7eb;
    font-weight: 700;
  }

  .admin-user-card .card-body {
    padding: 14px;
  }

  .admin-user-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 18px;
  }

  .admin-user-meta .item {
    padding: 10px 12px;
    border: 1px solid #f0f0f0;
    border-radius: 8px;
    background: #fcfcfc;
  }

  .admin-user-meta .label-text {
    display: block;
    color: #6b7280;
    font-size: 12px;
    margin-bottom: 4px;
  }

  .admin-user-meta .value-text {
    display: block;
    color: #111827;
    font-size: 14px;
    word-break: break-all;
  }

  .admin-user-table td,
  .admin-user-table th {
    vertical-align: middle !important;
  }

  .tag-default {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    background: #eef2ff;
    color: #4338ca;
    font-size: 12px;
  }

  .tag-muted {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    background: #f3f4f6;
    color: #6b7280;
    font-size: 12px;
  }
</style>

<div class="admin-user-panel">
  <div class="admin-user-card">
    <div class="card-head">基础信息</div>
    <div class="card-body">
      <div class="admin-user-meta">
        <div class="item"><span class="label-text">用户ID</span><span class="value-text">{{ $user->id }}</span></div>
        <div class="item"><span class="label-text">用户名</span><span class="value-text">{{ $user->name }}</span></div>
        <div class="item"><span class="label-text">邮箱</span><span class="value-text">{{ $user->email }}</span></div>
        <div class="item"><span class="label-text">邮箱验证</span><span class="value-text">{{ $user->email_verified ? '已验证' : '未验证' }}</span></div>
        <div class="item"><span class="label-text">账号状态</span><span class="value-text">{{ data_get($user, 'is_enabled', 1) ? '启用' : '禁用' }}</span></div>
        <div class="item"><span class="label-text">登录版本</span><span class="value-text">{{ data_get($user, 'session_version', 0) }}</span></div>
        <div class="item"><span class="label-text">注册时间</span><span class="value-text">{{ optional($user->created_at)->format('Y-m-d H:i:s') }}</span></div>
      </div>
    </div>
  </div>

  <div class="admin-user-card">
    <div class="card-head">权限操作</div>
    <div class="card-body">
      <p class="text-muted" style="margin-top:0;">封禁后用户将无法继续正常访问前台；重置登录态会让当前会话失效。</p>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        @if($user->is_enabled)
          <form action="{{ route('admin.users.ban', ['id' => $user->id]) }}" method="post" onsubmit="return confirm('确认封禁该用户？');">
            {{ csrf_field() }}
            <button type="submit" class="btn btn-danger">封禁用户</button>
          </form>
        @else
          <form action="{{ route('admin.users.unban', ['id' => $user->id]) }}" method="post" onsubmit="return confirm('确认解封该用户？');">
            {{ csrf_field() }}
            <button type="submit" class="btn btn-success">解封用户</button>
          </form>
        @endif

        <form action="{{ route('admin.users.reset_session', ['id' => $user->id]) }}" method="post" onsubmit="return confirm('确认重置该用户登录态？');">
          {{ csrf_field() }}
          <button type="submit" class="btn btn-warning">重置登录态</button>
        </form>
      </div>
    </div>
  </div>

  <div class="admin-user-card">
    <div class="card-head">默认地址与联系方式</div>
    <div class="card-body">
      @if($addresses->count())
        <table class="table table-bordered admin-user-table">
          <thead>
            <tr>
              <th>类型</th>
              <th>收货人</th>
              <th>联系方式</th>
              <th>地址</th>
              <th>邮编</th>
              <th>最后使用</th>
            </tr>
          </thead>
          <tbody>
            @foreach($addresses as $address)
              <tr>
                <td>{!! $address->is_default ? '<span class="tag-default">默认</span>' : '<span class="tag-muted">普通</span>' !!}</td>
                <td>{{ $address->contact_name }}</td>
                <td>{{ $address->contact_phone }}</td>
                <td>{{ trim($address->full_address . ' ' . $address->address) }}</td>
                <td>{{ $address->zip ?: '-' }}</td>
                <td>{{ optional($address->last_used_at)->format('Y-m-d H:i:s') ?: '-' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @else
        <div class="alert alert-info" style="margin-bottom:0;">该用户暂无收货地址记录。</div>
      @endif
    </div>
  </div>

  <div class="admin-user-card">
    <div class="card-head">最近订单</div>
    <div class="card-body">
      @if($recentOrders->count())
        <table class="table table-bordered admin-user-table">
          <thead>
            <tr>
              <th>订单号</th>
              <th>订单状态</th>
              <th>物流状态</th>
              <th>运单号</th>
              <th>金额</th>
              <th>下单时间</th>
            </tr>
          </thead>
          <tbody>
            @foreach($recentOrders as $order)
              @php
                $trackingNo = trim((string) ($order->tracking_no ?: data_get($order->ship_data, 'express_no', '')));
                $shipStatus = $trackingNo !== ''
                  ? \App\Models\Order::$shipStatusMap[\App\Models\Order::SHIP_STATUS_DELIVERED]
                  : (\App\Models\Order::$shipStatusMap[$order->ship_status] ?? '-');
              @endphp
              <tr>
                <td><a href="{{ route('orders.show', ['order' => $order->id]) }}" target="_blank">{{ $order->no }}</a></td>
                <td>{{ $order->display_status }}</td>
                <td>{{ $shipStatus }}</td>
                <td>{{ $trackingNo !== '' ? $trackingNo : '待上传' }}</td>
                <td>￥{{ number_format($order->total_amount, 2, '.', '') }}</td>
                <td>{{ optional($order->created_at)->format('Y-m-d H:i:s') }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @else
        <div class="alert alert-info" style="margin-bottom:0;">该用户暂无订单记录。</div>
      @endif
    </div>
  </div>

  <div class="admin-user-card">
    <div class="card-head">最近客服咨询</div>
    <div class="card-body">
      @if($recentFeedbacks->count())
        <table class="table table-bordered admin-user-table">
          <thead>
            <tr>
              <th>提交时间</th>
              <th>订单编号</th>
              <th>问题类型</th>
              <th>状态</th>
              <th>联系方式</th>
            </tr>
          </thead>
          <tbody>
            @foreach($recentFeedbacks as $feedback)
              <tr>
                <td>{{ optional($feedback->created_at)->format('Y-m-d H:i:s') }}</td>
                <td>{{ $feedback->order_no }}</td>
                <td>{{ \App\Models\SupportFeedback::$questionTypeMap[$feedback->question_type] ?? $feedback->question_type }}</td>
                <td>{{ \App\Models\SupportFeedback::$statusMap[$feedback->status] ?? $feedback->status }}</td>
                <td>{{ $feedback->contact_phone ?: 'N/A' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @else
        <div class="alert alert-info" style="margin-bottom:0;">该用户暂无客服咨询记录。</div>
      @endif
    </div>
  </div>
</div>