@extends('b_mode.layouts.app')
@section('title', '代购资质申请')

@section('content')
<div class="container" style="max-width: 860px; margin: 22px auto 40px;">
  <div class="panel panel-default" style="border-radius: 12px; overflow: hidden;">
    <div class="panel-heading" style="font-weight: 700; font-size: 16px;">Verified Courier 资质申请</div>
    <div class="panel-body">
      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif
      @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
      @endif
      @if($errors->any())
        <div class="alert alert-danger">
          <ul style="margin: 0; padding-left: 18px;">
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      @if(!empty($application) && $application->status === \App\Models\CourierApplication::STATUS_PENDING)
        <div class="alert alert-info">您的代购资质正在审核中，请耐心等待。</div>
      @elseif(!empty($application) && $application->status === \App\Models\CourierApplication::STATUS_APPROVED)
        <div class="alert alert-success">您的代购资质已通过审核，可直接承接任务。</div>
      @endif

      <form method="POST" action="{{ route('procurement.apply.store') }}" enctype="multipart/form-data">
        {{ csrf_field() }}
        <div class="form-group">
          <label>真实姓名</label>
          <input type="text" name="real_name" class="form-control" value="{{ old('real_name') }}" required maxlength="60">
        </div>

        <div class="form-group">
          <label>手机号</label>
          <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" required maxlength="32">
        </div>

        <div class="form-group">
          <label>身份证号</label>
          <input type="text" name="id_card_number" class="form-control" value="{{ old('id_card_number') }}" required maxlength="64">
        </div>

        <div class="form-group">
          <label>机票凭证图片</label>
          <input type="file" name="flight_ticket" class="form-control" accept="image/*" required>
        </div>

        <div class="form-group">
          <label>证件照片</label>
          <input type="file" name="id_card_photo" class="form-control" accept="image/*" required>
        </div>

        <button type="submit" class="btn btn-primary">提交审核</button>
      </form>
    </div>
  </div>
</div>
@endsection
