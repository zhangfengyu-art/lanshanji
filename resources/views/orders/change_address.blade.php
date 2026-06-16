@extends('layouts.app')
@section('title', '变更收件信息')

@section('content')
<div class="row">
  <div class="col-lg-10 col-lg-offset-1">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h2 class="text-center" style="margin: 0;">变更收件信息</h2>
        <p class="text-center text-muted" style="margin: 8px 0 0; font-size: 13px;">
          订单号 {{ $order->no }} · 剩余可自助改址 <strong>{{ $remainingChanges }}</strong> 次
        </p>
      </div>
      <div class="panel-body">
        @if(!empty($legacyAddressOnly))
          <div class="alert alert-warning">
            此订单保存时未拆分省市区，请在下拉框中<strong>重新选择省、市、区</strong>，并核对下方详细地址。
          </div>
        @endif

        @include('user_addresses._identity_notice')

        @if (count($errors) > 0)
          <div class="alert alert-danger">
            <ul style="margin: 0; padding-left: 1.2em;">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <user-addresses-create-and-edit inline-template>
          <form class="form-horizontal" method="post" action="{{ route('orders.update_address', $order) }}">
            {{ csrf_field() }}
            {{ method_field('PUT') }}
            <input type="hidden" id="address_init_zip" value="{{ old('zip', \App\Services\ChinaAreaZipService::normalizeZip($addressForm['zip']) ?: \App\Services\ChinaAreaZipService::zipFromNames($addressForm['province'], $addressForm['city'], $addressForm['district'])) }}">

            <select-district
              :init-value="{{ json_encode([$addressForm['province'], $addressForm['city'], $addressForm['district']]) }}"
              @change="onDistrictChanged"
              inline-template>
              <div class="form-group">
                <label class="control-label col-sm-2">{{ trans('frontend.address.region') }}</label>
                <div class="col-sm-3">
                  <select class="form-control" v-model="provinceId">
                    <option value="">{{ trans('frontend.address.select_province') }}</option>
                    <option v-for="(name, id) in provinces" :value="id">@{{ name }}</option>
                  </select>
                </div>
                <div class="col-sm-3">
                  <select class="form-control" v-model="cityId">
                    <option value="">{{ trans('frontend.address.select_city') }}</option>
                    <option v-for="(name, id) in cities" :value="id">@{{ name }}</option>
                  </select>
                </div>
                <div class="col-sm-3">
                  <select class="form-control" v-model="districtId">
                    <option value="">{{ trans('frontend.address.select_district') }}</option>
                    <option v-for="(name, id) in districts" :value="id">@{{ name }}</option>
                  </select>
                </div>
              </div>
            </select-district>

            <input type="hidden" name="zip" v-model="zip">
            <input type="hidden" name="province" v-model="province">
            <input type="hidden" name="city" v-model="city">
            <input type="hidden" name="district" v-model="district">

            <div class="form-group">
              <label class="control-label col-sm-2">{{ trans('frontend.address.detail_address') }}</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" name="address" minlength="5" value="{{ old('address', $addressForm['address']) }}" placeholder="{{ trans('frontend.address.detail_placeholder') }}">
                <p class="help-block">{{ trans('frontend.address.mainland_only') }}</p>
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2">{{ trans('frontend.address.name') }}</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" name="contact_name" value="{{ old('contact_name', $addressForm['contact_name']) }}">
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2">{{ trans('frontend.address.mobile') }}</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" name="contact_phone" maxlength="11" value="{{ old('contact_phone', $addressForm['contact_phone']) }}" placeholder="{{ trans('frontend.address.mobile_placeholder') }}">
              </div>
            </div>

            <div class="form-group text-center">
              <button type="submit" class="btn btn-primary">保存变更</button>
              <a href="{{ route('orders.show', $order) }}" class="btn btn-default" style="margin-left: 10px;">返回订单</a>
            </div>
          </form>
        </user-addresses-create-and-edit>

        <p class="text-muted" style="font-size: 13px; margin-top: 12px;">
          请通过下拉框重新选择省、市、区；仅修改详细地址时也要确认省市区选择正确。后台点击「开始处理」后将无法再自助改址。
        </p>
      </div>
    </div>
  </div>
</div>
@endsection
