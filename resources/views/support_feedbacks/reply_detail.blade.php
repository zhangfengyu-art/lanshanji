@extends('layouts.app')
@section('title', '问题回复详情')

@section('content')
<div class="row">
  <div class="col-lg-10 col-lg-offset-1">
    <div class="panel panel-default support-reply-detail-page">
      <div class="panel-heading clearfix">
        <h4 class="pull-left">问题回复详情</h4>
        <a class="btn btn-default btn-sm pull-right" href="{{ route('support.feedbacks.replies') }}">返回问题回复</a>
      </div>
      <div class="panel-body">
        @php
          $statusText = $statusMap[$feedback->status] ?? $feedback->status;
          $images = is_array($feedback->images) ? $feedback->images : [];
        @endphp

        <table class="table table-bordered support-reply-detail-table">
          <tbody>
            <tr>
              <th width="140">订单编号</th>
              <td>{{ $feedback->order_no }}</td>
            </tr>
            <tr>
              <th>问题类型</th>
              <td>{{ $questionTypeMap[$feedback->question_type] ?? $feedback->question_type }}</td>
            </tr>
            <tr>
              <th>提交时间</th>
              <td>{{ optional($feedback->created_at)->format('Y-m-d H:i:s') }}</td>
            </tr>
            <tr>
              <th>处理状态</th>
              <td>
                <span class="label label-default support-status-label support-status-label--{{ strtolower($feedback->status) }}">
                  {{ $statusText }}
                </span>
              </td>
            </tr>
            <tr>
              <th>问题描述</th>
              <td class="support-message-cell">{{ $feedback->message }}</td>
            </tr>
            <tr>
              <th>上传凭证</th>
              <td>
                @if(empty($images))
                  <span class="text-muted">未上传图片</span>
                @else
                  <div class="support-image-list">
                    @foreach($images as $image)
                      <a href="{{ Storage::disk('public')->url($image) }}" target="_blank" rel="noopener noreferrer">
                        <img src="{{ Storage::disk('public')->url($image) }}" alt="凭证图片">
                      </a>
                    @endforeach
                  </div>
                @endif
              </td>
            </tr>
            <tr>
              <th>客服回复</th>
              <td class="support-message-cell">
                @if(trim((string) $feedback->admin_reply) === '')
                  <span class="text-muted">客服暂未回复，请耐心等待。</span>
                @else
                  {{ $feedback->admin_reply }}
                @endif
              </td>
            </tr>
            <tr>
              <th>处理时间</th>
              <td>{{ optional($feedback->handled_at)->format('Y-m-d H:i:s') ?: '暂未处理' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
