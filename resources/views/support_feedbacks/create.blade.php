@extends('layouts.app')
@section('title', '联系客服')

@section('content')
<div class="row">
  <div class="col-lg-10 col-lg-offset-1">
    <div class="panel panel-default support-feedback-page">
      <div class="panel-heading">
        <h4>客服咨询</h4>
      </div>
      <div class="panel-body">
        @if (session('success'))
          <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if (count($errors) > 0)
          <div class="alert alert-danger">
            <h4>提交失败：</h4>
            <ul>
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <div class="alert alert-warning support-feedback-notice">
          请如实填写咨询内容。订单相关问题请务必核对订单编号，可上传最多 5 张凭证图片辅助客服排查。
        </div>

        <div class="alert alert-info" style="margin-bottom: 12px;">
          今日剩余可提交 {{ $submitPolicy['daily_remaining'] }} 次（上限 {{ $submitPolicy['max_daily_submissions'] }} 次），
          当前待处理 {{ $submitPolicy['pending_count'] }}/{{ $submitPolicy['max_pending_feedbacks'] }} 条，
          连续提交需间隔 {{ $submitPolicy['submit_cooldown_minutes'] }} 分钟。
          @if($submitPolicy['cooldown_seconds'] > 0 && $submitPolicy['next_available_at'])
            下次可提交时间：{{ $submitPolicy['next_available_at']->format('H:i:s') }}。
          @endif
        </div>

        @if(!$submitPolicy['can_submit'])
          <div class="alert alert-danger" style="margin-bottom: 12px;">
            <strong>当前暂不可提交新咨询：</strong>
            <ul style="margin-top: 8px; margin-bottom: 0;">
              @foreach($submitPolicy['block_reasons'] as $reason)
                <li>{{ $reason }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <form class="form-horizontal" method="post" action="{{ route('support.feedbacks.store') }}" enctype="multipart/form-data">
          {{ csrf_field() }}
          <input type="hidden" name="locked_order_no" value="{{ $isLocked ? '1' : '0' }}">

          <div class="form-group">
            <label class="control-label col-sm-2">订单编号</label>
            <div class="col-sm-8">
              <input
                type="text"
                class="form-control"
                name="order_no"
                value="{{ old('order_no', $orderNo) }}"
                {{ $isLocked ? 'readonly' : '' }}
                placeholder="请输入订单编号"
              >
              @if($isLocked)
                <p class="help-block">该订单编号由订单页自动带入，已锁定不可修改。</p>
              @endif
            </div>
          </div>

          <div class="form-group">
            <label class="control-label col-sm-2">账户信息</label>
            <div class="col-sm-8">
              <input type="text" class="form-control" value="{{ auth()->user()->name }}（{{ auth()->user()->email }}）" readonly>
              <p class="help-block">系统将自动使用你的注册信息作为联系方式，无需重复填写。</p>
            </div>
          </div>

          <div class="form-group">
            <label class="control-label col-sm-2">问题类型</label>
            <div class="col-sm-8">
              <select class="form-control" name="question_type">
                @foreach($questionTypeMap as $typeCode => $typeText)
                  <option value="{{ $typeCode }}" {{ old('question_type') === $typeCode ? 'selected' : '' }}>{{ $typeText }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="control-label col-sm-2">问题描述</label>
            <div class="col-sm-8">
              <textarea class="form-control" name="message" rows="6" maxlength="1000" placeholder="请尽量描述清楚问题现象、时间和诉求（至少 10 字）">{{ old('message') }}</textarea>
            </div>
          </div>

          <div class="form-group">
            <label class="control-label col-sm-2">上传图片</label>
            <div class="col-sm-8">
              <input type="file" class="form-control" name="images[]" accept="image/*" multiple>
              <p class="help-block">最多可上传 5 张图片，每张不超过 5MB（jpg/png/gif/webp 等）。</p>
            </div>
          </div>

          <div class="form-group">
            <div class="col-sm-8 col-sm-offset-2">
              <button type="submit" class="btn btn-primary" {{ $submitPolicy['can_submit'] ? '' : 'disabled' }}>提交咨询</button>
              <a href="{{ url()->previous() }}" class="btn btn-default">返回</a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
