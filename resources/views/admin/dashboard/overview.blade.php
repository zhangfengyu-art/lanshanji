<div class="row">
    @foreach($stats as $label => $value)
        <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom: 12px;">
            <div class="small-box bg-aqua">
                <div class="inner">
                    <h3>{{ $value }}</h3>
                    <p>{{ $label }}</p>
                </div>
                <div class="icon">
                    <i class="fa fa-bar-chart"></i>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title">系统信息</h3>
    </div>
    <div class="box-body table-responsive no-padding">
        <table class="table table-bordered table-hover">
            <tbody>
            @foreach($systemInfo as $label => $value)
                <tr>
                    <th style="width: 220px;">{{ $label }}</th>
                    <td>{{ $value ?: '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">快捷入口</h3>
    </div>
    <div class="box-body">
        <a href="/admin/products" class="btn btn-sm btn-primary" style="margin-right:8px; margin-bottom:8px;">商品管理</a>
        <a href="/admin/orders" class="btn btn-sm btn-info" style="margin-right:8px; margin-bottom:8px;">订单调度</a>
        <a href="/admin/support-feedbacks" class="btn btn-sm btn-warning" style="margin-right:8px; margin-bottom:8px;">客服反馈</a>
        <a href="/admin/auth/logs" class="btn btn-sm btn-default" style="margin-right:8px; margin-bottom:8px;">操作日志</a>
    </div>
</div>
