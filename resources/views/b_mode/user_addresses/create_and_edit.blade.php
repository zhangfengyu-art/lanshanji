@extends('b_mode.layouts.app')
@section('title', (($address->id ? '编辑' : '新增') . '国内转寄地址'))

@section('content')
<div class="row">
<div class="col-lg-10 col-lg-offset-1">
<div class="panel panel-default">
  <div class="panel-heading">
    <h2 class="text-center">
      {{ ($address->id ? '编辑' : '新增') . '国内转寄地址（Domestic Forwarding Address）' }}
    </h2>
  </div>
  <div class="panel-body">
    @if (session('success'))
      <div class="alert alert-success">
        {{ session('success') }}
      </div>
    @endif
    @if (count($errors) > 0)
      <div class="alert alert-danger">
        <h4>{{ trans('frontend.common.errors_occurred') }}</h4>
        <ul>
          @foreach ($errors->all() as $error)
            <li><i class="glyphicon glyphicon-remove"></i> {{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif
    <user-addresses-create-and-edit inline-template>
      @if($address->id)
        <form class="form-horizontal" role="form" action="{{ route('user_addresses.update', ['user_address' => $address->id]) }}" method="post">
          {{ method_field('PUT') }}
      @else
        <form class="form-horizontal" role="form" action="{{ route('user_addresses.store') }}" method="post">
      @endif
        {{ csrf_field() }}
        <input type="hidden" name="redirect" value="{{ old('redirect', $redirectTo ?? request('redirect', '')) }}">
        <select-district :init-value="{{ json_encode([$address->province, $address->city, $address->district]) }}" @change="onDistrictChanged" inline-template>
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
        <input type="hidden" name="zip" value="{{ old('zip', $address->zip ?: 0) }}">
        <input type="hidden" name="province" v-model="province">
        <input type="hidden" name="city" v-model="city">
        <input type="hidden" name="district" v-model="district">
        <div class="form-group">
          <label class="control-label col-sm-2">国内转寄地址</label>
          <div class="col-sm-9">
            <input type="text" class="form-control" name="address" minlength="5" value="{{ old('address', $address->address) }}" placeholder="{{ trans('frontend.address.detail_placeholder') }}">
            <p class="help-block">{{ trans('frontend.address.mainland_only') }}</p>
            <p class="help-block">请确保填写准确，代购人在完成境外代买并入境后，将通过国内顺丰/邮政转寄至此地址。</p>
          </div>
        </div>
        <div class="form-group">
          <label class="control-label col-sm-2">{{ trans('frontend.address.name') }}</label>
          <div class="col-sm-9">
            <input type="text" class="form-control" name="contact_name" value="{{ old('contact_name', $address->contact_name) }}">
          </div>
        </div>
        <div class="form-group">
          <label class="control-label col-sm-2">{{ trans('frontend.address.mobile') }}</label>
          <div class="col-sm-9">
            <input type="text" class="form-control" name="contact_phone" maxlength="11" pattern="^1[3-9]\d{9}$" value="{{ old('contact_phone', $address->contact_phone) }}" placeholder="{{ trans('frontend.address.mobile_placeholder') }}">
          </div>
        </div>
        <div class="form-group">
          <label class="control-label col-sm-2">{{ trans('frontend.address.id_card') }}</label>
          <div class="col-sm-9">
            <input type="text" class="form-control" name="id_card" maxlength="18" value="{{ old('id_card', $address->id_card) }}" placeholder="{{ trans('frontend.address.id_card_placeholder') }}">
          </div>
        </div>
        <div class="form-group">
          <label class="control-label col-sm-2">{{ trans('frontend.address.default_address') }}</label>
          <div class="col-sm-9">
            <label style="font-weight: normal; margin-top: 7px;">
              <input type="checkbox" name="is_default" value="1" {{ old('is_default', $address->is_default) ? 'checked' : '' }}> {{ trans('frontend.address.set_default') }}
            </label>
          </div>
        </div>
        <div class="form-group text-center">
          <button type="submit" class="btn btn-primary">{{ trans('frontend.common.save_address') }}</button>
          @if(old('redirect', $redirectTo ?? request('redirect', '')))
            <button type="submit" name="return_to_checkout" value="1" class="btn btn-success" style="margin-left: 10px;">保存并返回支付页</button>
          @endif
        </div>
      </form>
    </user-addresses-create-and-edit>
  </div>
</div>
</div>
</div>
@endsection