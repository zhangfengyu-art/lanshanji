<div class="box box-info">
  <div class="box-header with-border">
    <h3 class="box-title">用户 #{{ $user->id }}：{{ $user->name ?: '—' }}</h3>
    <div class="box-tools">
      <div class="btn-group pull-right" style="margin-right: 10px">
        <a href="{{ url(config('admin.route.prefix').'/users') }}" class="btn btn-sm btn-default"><i class="fa fa-list"></i> 返回列表</a>
      </div>
    </div>
  </div>
  <div class="box-body">
    <table class="table table-bordered">
      <tbody>
        <tr>
          <td width="140">用户 ID</td>
          <td>{{ $user->id }}</td>
          <td width="140">注册时间</td>
          <td>{{ $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : '—' }}</td>
        </tr>
        <tr>
          <td>用户名</td>
          <td>{{ $user->name ?: '—' }}</td>
          <td>邮箱</td>
          <td>{{ $user->email }}</td>
        </tr>
        <tr>
          <td>已验证邮箱</td>
          <td>{{ $user->email_verified ? '是' : '否' }}</td>
          <td>账号状态</td>
          <td>
            @if($user->is_enabled)
              <span class="label label-success">正常</span>
            @else
              <span class="label label-danger">已封禁</span>
            @endif
          </td>
        </tr>
        <tr>
          <td>登录态版本</td>
          <td>{{ (int) $user->session_version }}</td>
          <td>操作</td>
          <td>
            @php
              $toggleUrl = $user->is_enabled
                ? route('admin.users.ban', ['id' => $user->id])
                : route('admin.users.unban', ['id' => $user->id]);
              $toggleLabel = $user->is_enabled ? '封禁' : '解封';
              $toggleClass = $user->is_enabled ? 'btn-danger' : 'btn-success';
            @endphp
            <form action="{{ $toggleUrl }}" method="post" style="display:inline-block;margin-right:4px;" onsubmit="return confirm('确认{{ $toggleLabel }}该用户？');">
              @csrf
              <button type="submit" class="btn btn-xs {{ $toggleClass }}">{{ $toggleLabel }}</button>
            </form>
            <form action="{{ route('admin.users.reset_session', ['id' => $user->id]) }}" method="post" style="display:inline-block;" onsubmit="return confirm('确认重置该用户的登录态？');">
              @csrf
              <button type="submit" class="btn btn-xs btn-warning">重置登录态</button>
            </form>
          </td>
        </tr>
      </tbody>
    </table>

    <h4>收货地址（{{ $addresses->count() }}）</h4>
    @if($addresses->isEmpty())
      <p class="text-muted">暂无收货地址。</p>
    @else
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th>收件人</th>
            <th>电话</th>
            <th>身份证号</th>
            <th>地址</th>
            <th>默认</th>
            <th>最近使用</th>
          </tr>
        </thead>
        <tbody>
          @foreach($addresses as $address)
            <tr>
              <td>{{ $address->contact_name }}</td>
              <td>{{ $address->contact_phone }}</td>
              <td>{{ $address->id_card ?: '—' }}</td>
              <td>{{ $address->full_address }} @if($address->zip)（{{ $address->zip }}）@endif</td>
              <td>{{ $address->is_default ? '是' : '否' }}</td>
              <td>{{ $address->last_used_at ? $address->last_used_at->format('Y-m-d H:i') : '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif

    <h4>最近订单（最多 10 条）</h4>
    @if($recentOrders->isEmpty())
      <p class="text-muted">暂无订单。</p>
    @else
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th>订单号</th>
            <th>金额</th>
            <th>支付时间</th>
            <th>状态</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          @foreach($recentOrders as $order)
            <tr>
              <td>{{ $order->no }}</td>
              <td>￥{{ number_format((float) $order->total_amount, 2, '.', '') }}</td>
              <td>{{ $order->paid_at ? $order->paid_at->format('Y-m-d H:i') : '未支付' }}</td>
              <td>{{ $order->display_status }}</td>
              <td>
                <a class="btn btn-xs btn-primary" href="{{ route('admin.orders.show', ['order' => $order->id]) }}">查看订单</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif

    <h4>最近客服反馈（最多 10 条）</h4>
    @if($recentFeedbacks->isEmpty())
      <p class="text-muted">暂无反馈记录。</p>
    @else
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th>时间</th>
            <th>类型</th>
            <th>订单号</th>
            <th>状态</th>
            <th>内容摘要</th>
          </tr>
        </thead>
        <tbody>
          @foreach($recentFeedbacks as $feedback)
            <tr>
              <td>{{ $feedback->created_at ? $feedback->created_at->format('Y-m-d H:i') : '—' }}</td>
              <td>{{ $feedback->question_type_label }}</td>
              <td>{{ $feedback->order_no ?: '—' }}</td>
              <td>{{ $feedback->status_label }}</td>
              <td>{{ \Illuminate\Support\Str::limit($feedback->message, 80) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>
