@extends('layouts.app')
@section('title', '提交问题反馈')

@section('content')
<div class="row">
  <div class="col-lg-8 col-lg-offset-2">
    <div class="panel panel-default">
      <div class="panel-heading">
        提交问题反馈
        <a href="{{ route('support.feedbacks.index') }}" class="pull-right">查看我的反馈与回复</a>
      </div>
      <div class="panel-body">
        @if(session('status'))
          <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if(!empty($submitBlockedMessage))
          <div class="alert alert-warning">{{ $submitBlockedMessage }}</div>
        @endif

        <p class="text-muted" style="margin-bottom: 12px;">
          每 {{ $minIntervalMinutes }} 分钟最多提交 1 条，每日最多 {{ $dailyMax }} 条。
        </p>

        @if(isset($errors) && $errors->any())
          <div class="alert alert-danger">
            <ul style="margin-bottom: 0;">
              @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <form method="POST" action="{{ route('support.feedbacks.store') }}" @if(!empty($submitBlockedMessage)) onsubmit="return false;" @endif>
          {{ csrf_field() }}
          <div class="form-group">
            <label for="contact_name">联系人 <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="contact_name" name="contact_name" value="{{ old('contact_name', $defaultName) }}" required maxlength="64">
          </div>
          <div class="form-group">
            <label for="contact_phone">联系电话 <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $defaultPhone) }}" required maxlength="32">
          </div>
          <div class="form-group">
            <label for="order_no">相关订单号（选填）</label>
            <input type="text" class="form-control" id="order_no" name="order_no" value="{{ old('order_no', $defaultOrderNo ?? '') }}" placeholder="如有订单问题请填写订单流水号" maxlength="64">
          </div>
          <div class="form-group">
            <label for="question_type">问题类型 <span class="text-danger">*</span></label>
            <select class="form-control" id="question_type" name="question_type" required>
              <option value="">请选择</option>
              @foreach($questionTypes as $value => $label)
                <option value="{{ $value }}" {{ old('question_type', $defaultQuestionType ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label for="message">反馈内容 <span class="text-danger">*</span></label>
            <textarea class="form-control" id="message" name="message" rows="6" required maxlength="2000" placeholder="请描述您的问题，如有需要可注明签收时间、商品情况等">{{ old('message', $defaultMessage ?? '') }}</textarea>
          </div>
          <button type="submit" class="btn btn-primary" @if(!empty($submitBlockedMessage)) disabled @endif>提交反馈</button>
          <a href="{{ route('pages.faq') }}" class="btn btn-default">返回常见问题</a>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
