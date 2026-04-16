@extends('b_mode.layouts.app')
@section('title', '国内转寄地址列表')

@section('content')
<style>
  body.site-mode-b .b-address-wrap {
    max-width: 1040px;
    margin: 6px auto 22px;
    padding: 0 6px;
  }

  body.site-mode-b .b-address-intro {
    margin-bottom: 10px;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid rgba(44, 123, 229, 0.18);
    background: linear-gradient(135deg, #ffffff 0%, #f7fafc 100%);
  }

  body.site-mode-b .b-address-intro p {
    margin: 0;
    color: #64748b;
    font-size: 12px;
  }

  body.site-mode-b .b-address-wrap .panel {
    border-radius: 16px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05);
  }

  body.site-mode-b .b-address-wrap .panel-heading {
    padding: 16px 18px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
  }

  body.site-mode-b .b-address-wrap .panel-body {
    padding: 16px 18px 18px;
  }

  body.site-mode-b .b-address-wrap .table {
    margin-bottom: 0;
  }

  body.site-mode-b .b-address-wrap .table > thead > tr > th {
    background: #f8fafc;
    color: #64748b;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
  }

  body.site-mode-b .b-address-wrap .table > tbody > tr > td {
    border-top: 1px solid rgba(15, 23, 42, 0.08);
    vertical-align: middle;
    font-size: 13px;
  }

  body.site-mode-b .b-address-wrap .btn {
    border-radius: 10px;
    padding: 6px 12px;
    font-size: 12px;
  }

  body.site-mode-b .b-address-row-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
  }

  @media (max-width: 768px) {
    body.site-mode-b .b-address-wrap {
      margin-bottom: 14px;
    }

    body.site-mode-b .b-address-wrap .panel-heading {
      padding: 12px 14px;
      font-size: 14px;
      align-items: flex-start;
      flex-direction: column;
    }

    body.site-mode-b .b-address-wrap .panel-body {
      padding: 10px;
    }

    body.site-mode-b .b-address-wrap .table,
    body.site-mode-b .b-address-wrap .table thead,
    body.site-mode-b .b-address-wrap .table tbody,
    body.site-mode-b .b-address-wrap .table tr,
    body.site-mode-b .b-address-wrap .table td {
      display: block;
      width: 100%;
    }

    body.site-mode-b .b-address-wrap .table thead {
      display: none;
    }

    body.site-mode-b .b-address-wrap .table tr {
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 12px;
      margin-bottom: 10px;
      overflow: hidden;
      background: #fff;
    }

    body.site-mode-b .b-address-wrap .table td {
      border-top: 1px dashed rgba(15, 23, 42, 0.08);
      padding: 9px 10px;
    }

    body.site-mode-b .b-address-wrap .table td:first-child {
      border-top: 0;
      font-weight: 700;
    }
  }
</style>

<div class="b-address-wrap">
  <div class="b-address-intro">
    <p>请维护可用的国内转寄地址，订单履约后会按默认地址优先发货。</p>
  </div>
  <div class="row">
    <div class="col-lg-10 col-lg-offset-1">
      <div class="panel panel-default">
        <div class="panel-heading">
          <span>国内转寄地址列表（Domestic Forwarding Address）</span>
          <a href="{{ route('user_addresses.create') }}" class="btn btn-primary">{{ trans('frontend.address.add_new') }}</a>
        </div>
        <div class="panel-body">
          <table class="table table-bordered table-striped">
            <thead>
            <tr>
              <th>{{ trans('frontend.address.consignee') }}</th>
              <th>国内转寄地址</th>
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
                <div class="b-address-row-actions">
                  <a href="{{ route('user_addresses.edit', ['user_address' => $address->id]) }}" class="btn btn-primary">{{ trans('frontend.common.edit') }}</a>
                  <button class="btn btn-danger btn-del-address" type="button" data-id="{{ $address->id }}">{{ trans('frontend.common.delete') }}</button>
                </div>
              </td>
            </tr>
            @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scriptsAfterJs')
<script>
$(document).ready(function() {
  $('.btn-del-address').click(function() {
    var id = $(this).data('id');
    swal({
        title: "{{ trans('frontend.address.confirm_delete') }}",
        icon: "warning",
        buttons: ['{{ trans('frontend.common.cancel') }}', '{{ trans('frontend.common.confirm') }}'],
        dangerMode: true,
      })
    .then(function(willDelete) {
      if (!willDelete) {
        return;
      }
      axios.delete('/user_addresses/' + id)
        .then(function () {
          location.reload();
        })
    });
  });
});
</script>
@endsection