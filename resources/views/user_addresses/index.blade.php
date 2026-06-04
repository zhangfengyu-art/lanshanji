@extends('layouts.app')
@section('title', is_site_mode_b() ? '国内转寄地址列表' : trans('frontend.address.list_title'))

@section('content')
<div class="row">
<div class="col-lg-10 col-lg-offset-1">
<div class="panel panel-default">
  <div class="panel-heading">
    {{ is_site_mode_b() ? '国内转寄地址列表（Domestic Forwarding Address）' : trans('frontend.address.list_title') }}
    <a href="{{ route('user_addresses.create') }}" class="pull-right">{{ trans('frontend.address.add_new') }}</a>
  </div>
  <div class="panel-body">
    <table class="table table-bordered table-striped">
      <thead>
      <tr>
        <th>{{ trans('frontend.address.consignee') }}</th>
        <th>{{ is_site_mode_b() ? '国内转寄地址' : trans('frontend.address.address') }}</th>
        <th>{{ trans('frontend.address.zip') }}</th>
        <th>{{ trans('frontend.address.phone') }}</th>
        <th>{{ trans('frontend.address.operation') }}</th>
      </tr>
      </thead>
      <tbody>
      @foreach($addresses as $address)
      <tr>
        <td>
          {{ $address->contact_name }}
          @if($address->is_default)
            <span class="label label-success">{{ trans('frontend.common.default_tag') }}</span>
          @endif
        </td>
        <td>{{ $address->full_address }}</td>
        <td>{{ $address->zip }}</td>
        <td>{{ $address->contact_phone }}</td>
        <td>
          <a href="{{ route('user_addresses.edit', ['user_address' => $address->id]) }}" class="btn btn-primary">{{ trans('frontend.common.edit') }}</a>
          <button class="btn btn-danger btn-del-address" type="button" data-id="{{ $address->id }}">{{ trans('frontend.common.delete') }}</button>
        </td>
      </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</div>
</div>
</div>
@endsection

@section('scriptsAfterJs')
<script>
$(document).ready(function() {
  // 删除按钮点击事件
  $('.btn-del-address').click(function() {
    // 获取按钮上 data-id 属性的值，也就是地址 ID
    var id = $(this).data('id');
    // 调用 sweetalert
    swal({
        title: "{{ trans('frontend.address.confirm_delete') }}",
        icon: "warning",
        buttons: ['{{ trans('frontend.common.cancel') }}', '{{ trans('frontend.common.confirm') }}'],
        dangerMode: true,
      })
    .then(function(willDelete) { // 用户点击按钮后会触发这个回调函数
      // 用户点击确定 willDelete 值为 true， 否则为 false
      // 用户点了取消，啥也不做
      if (!willDelete) {
        return;
      }
      // 调用删除接口，用 id 来拼接出请求的 url
      axios.delete('/user_addresses/' + id)
        .then(function () {
          // 请求成功之后重新加载页面
          location.reload();
        })
    });
  });
});
</script>
@endsection
