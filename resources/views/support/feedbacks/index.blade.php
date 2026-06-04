@extends('layouts.app')
@section('title', '客服反馈')

@section('content')
<div class="row">
  <div class="col-lg-10 col-lg-offset-1">
    <div class="panel panel-default">
      <div class="panel-heading">
        我的反馈与客服回复
        <a href="{{ route('support.feedbacks.create') }}" class="pull-right btn btn-xs btn-primary" style="margin-top: 2px;">提交新反馈</a>
      </div>
      <div class="panel-body">
        @if(session('status'))
          <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if(!empty($rateLimitMessage))
          <div class="alert alert-warning">{{ $rateLimitMessage }}</div>
        @endif

        <p class="text-muted" style="margin-bottom: 16px;">
          提交后请在此查看处理进度；客服回复后状态会变为「已回复」。为防止滥用，每位用户每 {{ $minIntervalMinutes }} 分钟最多提交 1 条，每日最多 {{ $dailyMax }} 条。
        </p>

        @if($feedbacks->isEmpty())
          <p class="text-muted">您还没有提交过反馈。</p>
          <a href="{{ route('support.feedbacks.create') }}" class="btn btn-primary">去提交反馈</a>
        @else
          @foreach($feedbacks as $feedback)
            <div class="well" style="margin-bottom: 16px;">
              <div class="clearfix" style="margin-bottom: 8px;">
                <strong>#{{ $feedback->id }}</strong>
                <span class="label label-{{ $feedback->status === \App\Models\SupportFeedback::STATUS_HANDLED ? 'success' : 'warning' }}" style="margin-left: 8px;">
                  {{ $feedback->status_label }}
                </span>
                <span class="pull-right text-muted">{{ $feedback->created_at->format('Y-m-d H:i') }}</span>
              </div>
              <p style="margin-bottom: 6px;"><strong>类型：</strong>{{ $feedback->question_type_label }}</p>
              @if($feedback->order_no)
                <p style="margin-bottom: 6px;"><strong>订单号：</strong>{{ $feedback->order_no }}</p>
              @endif
              <p style="margin-bottom: 6px;"><strong>我的反馈：</strong></p>
              <p style="white-space: pre-wrap; background: #f9f9f9; padding: 10px; border-radius: 4px;">{{ $feedback->message }}</p>
              @if($feedback->status === \App\Models\SupportFeedback::STATUS_HANDLED && $feedback->admin_reply)
                <p style="margin-top: 12px; margin-bottom: 6px;"><strong>客服回复：</strong>
                  @if($feedback->handled_at)
                    <span class="text-muted">（{{ $feedback->handled_at->format('Y-m-d H:i') }}）</span>
                  @endif
                </p>
                <p style="white-space: pre-wrap; background: #eefaf3; padding: 10px; border-radius: 4px; border-left: 3px solid #3c763d;">{{ $feedback->admin_reply }}</p>
              @else
                <p class="text-muted" style="margin-top: 10px;">客服正在处理，请稍后查看回复。</p>
              @endif
            </div>
          @endforeach
          {{ $feedbacks->links() }}
        @endif
      </div>
    </div>
  </div>
</div>
@endsection