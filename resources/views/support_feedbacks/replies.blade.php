@extends('layouts.app')
@section('title', '问题回复')

@section('content')
<div class="row">
  <div class="col-lg-10 col-lg-offset-1">
    <div class="panel panel-default support-replies-page">
      <div class="panel-heading">
        <div class="clearfix">
          <h4 class="pull-left" style="margin: 8px 0 0;">问题回复</h4>
          @if($submitPolicy['can_submit'])
            <a class="btn btn-primary btn-sm pull-right" href="{{ route('support.feedbacks.create') }}">发起咨询</a>
          @else
            <button type="button" class="btn btn-default btn-sm pull-right" disabled title="{{ implode('；', $submitPolicy['block_reasons']) }}">发起咨询（暂不可用）</button>
          @endif
        </div>
      </div>
      <div class="panel-body">
        <div class="alert alert-info" style="margin-bottom: 12px;">
          今日已提交 {{ $submitPolicy['daily_count'] }}/{{ $submitPolicy['max_daily_submissions'] }} 次，
          待处理 {{ $submitPolicy['pending_count'] }}/{{ $submitPolicy['max_pending_feedbacks'] }} 条，
          单次提交间隔 {{ $submitPolicy['submit_cooldown_minutes'] }} 分钟。
        </div>

        @if(!$submitPolicy['can_submit'])
          <div class="alert alert-warning" style="margin-bottom: 12px;">
            @foreach($submitPolicy['block_reasons'] as $reason)
              <div>• {{ $reason }}</div>
            @endforeach
          </div>
        @endif

        @if($feedbacks->count() === 0)
          <div class="alert alert-info">你还没有提交过客服问题，提交后可在这里查看处理进度与回复。</div>
          @if($submitPolicy['can_submit'])
            <a class="btn btn-primary" href="{{ route('support.feedbacks.create') }}">去提交问题</a>
          @endif
        @else
          <div class="table-responsive">
            <table class="table table-bordered table-hover support-replies-table">
              <thead>
                <tr>
                  <th>提交时间</th>
                  <th>订单编号</th>
                  <th>问题类型</th>
                  <th>处理状态</th>
                  <th>客服回复</th>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody>
                @foreach($feedbacks as $feedback)
                  @php
                    $statusText = $statusMap[$feedback->status] ?? $feedback->status;
                    $replyText = trim((string) $feedback->admin_reply);
                    $replyPreview = strlen($replyText) > 60 ? substr($replyText, 0, 60).'...' : $replyText;
                  @endphp
                  <tr>
                    <td>{{ optional($feedback->created_at)->format('Y-m-d H:i') }}</td>
                    <td>{{ $feedback->order_no }}</td>
                    <td>{{ $questionTypeMap[$feedback->question_type] ?? $feedback->question_type }}</td>
                    <td>
                      <span class="label label-default support-status-label support-status-label--{{ strtolower($feedback->status) }}">
                        {{ $statusText }}
                      </span>
                    </td>
                    <td>
                      @if($replyText === '')
                        <span class="text-muted">客服暂未回复</span>
                      @else
                        {{ $replyPreview }}
                      @endif
                    </td>
                    <td>
                      <a class="btn btn-xs btn-default" href="{{ route('support.feedbacks.reply_detail', ['support_feedback' => $feedback->id]) }}">查看详情</a>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <div class="text-right">
            {{ $feedbacks->links() }}
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
