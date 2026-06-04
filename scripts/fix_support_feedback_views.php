<?php

function u($jsonUnicode)
{
    return json_decode('"' . $jsonUnicode . '"');
}

$blade = <<<'BLADE'
@extends('layouts.app')
@section('title', '__TITLE__')

@section('content')
<div class="row">
  <div class="col-lg-10 col-lg-offset-1">
    <div class="panel panel-default">
      <div class="panel-heading">
        __HEADING__
        <a href="{{ route('support.feedbacks.create') }}" class="pull-right btn btn-xs btn-primary" style="margin-top: 2px;">__BTN_NEW__</a>
      </motion>
      <div class="panel-body">
        @if(session('status'))
          <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if(!empty($rateLimitMessage))
          <div class="alert alert-warning">{{ $rateLimitMessage }}</div>
        @endif

        <p class="text-muted" style="margin-bottom: 16px;">
          __HINT__
        </p>

        @if($feedbacks->isEmpty())
          <p class="text-muted">__EMPTY__</p>
          <a href="{{ route('support.feedbacks.create') }}" class="btn btn-primary">__BTN_GO__</a>
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
              <p style="margin-bottom: 6px;"><strong>__LBL_TYPE__</strong>{{ $feedback->question_type_label }}</p>
              @if($feedback->order_no)
                <p style="margin-bottom: 6px;"><strong>__LBL_ORDER__</strong>{{ $feedback->order_no }}</p>
              @endif
              <p style="margin-bottom: 6px;"><strong>__LBL_MSG__</strong></p>
              <p style="white-space: pre-wrap; background: #f9f9f9; padding: 10px; border-radius: 4px;">{{ $feedback->message }}</p>
              @if($feedback->status === \App\Models\SupportFeedback::STATUS_HANDLED && $feedback->admin_reply)
                <p style="margin-top: 12px; margin-bottom: 6px;"><strong>__LBL_REPLY__</strong>
                  @if($feedback->handled_at)
                    <span class="text-muted">（{{ $feedback->handled_at->format('Y-m-d H:i') }}）</span>
                  @endif
                </p>
                <p style="white-space: pre-wrap; background: #eefaf3; padding: 10px; border-radius: 4px; border-left: 3px solid #3c763d;">{{ $feedback->admin_reply }}</p>
              @else
                <p class="text-muted" style="margin-top: 10px;">__LBL_PENDING__</p>
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
BLADE;

$blade = str_replace('<motion', '<motion', $blade);
$blade = str_replace('</motion>', '</motion>', $blade);
$blade = str_replace('<motion', '<div', $blade);
$blade = str_replace('</motion>', '</div>', $blade);

$replacements = [
    '__TITLE__' => u('\u5ba2\u670d\u53cd\u9988'),
    '__HEADING__' => u('\u6211\u7684\u53cd\u9988\u4e0e\u5ba2\u670d\u56de\u590d'),
    '__BTN_NEW__' => u('\u63d0\u4ea4\u65b0\u53cd\u9988'),
    '__HINT__' => u('\u63d0\u4ea4\u540e\u8bf7\u5728\u6b64\u67e5\u770b\u5904\u7406\u8fdb\u5ea6\uff1b\u5ba2\u670d\u56de\u590d\u540e\u72b6\u6001\u4f1a\u53d8\u4e3a\u300c\u5df2\u56de\u590d\u300d\u3002\u4e3a\u9632\u6b62\u6ee5\u7528\uff0c\u6bcf\u4f4d\u7528\u6237\u6bcf {{ $minIntervalMinutes }} \u5206\u949f\u6700\u591a\u63d0\u4ea4 1 \u6761\uff0c\u6bcf\u65e5\u6700\u591a {{ $dailyMax }} \u6761\u3002'),
    '__EMPTY__' => u('\u60a8\u8fd8\u6ca1\u6709\u63d0\u4ea4\u8fc7\u53cd\u9988\u3002'),
    '__BTN_GO__' => u('\u53bb\u63d0\u4ea4\u53cd\u9988'),
    '__LBL_TYPE__' => u('\u7c7b\u578b\uff1a'),
    '__LBL_ORDER__' => u('\u8ba2\u5355\u53f7\uff1a'),
    '__LBL_MSG__' => u('\u6211\u7684\u53cd\u9988\uff1a'),
    '__LBL_REPLY__' => u('\u5ba2\u670d\u56de\u590d\uff1a'),
    '__LBL_PENDING__' => u('\u5ba2\u670d\u6b63\u5728\u5904\u7406\uff0c\u8bf7\u7a0d\u540e\u67e5\u770b\u56de\u590d\u3002'),
];

$blade = str_replace(array_keys($replacements), array_values($replacements), $blade);

$path = __DIR__ . '/../resources/views/support/feedbacks/index.blade.php';
file_put_contents($path, $blade);

echo "OK: $path\n";
echo file_get_contents($path, false, null, 0, 120) . "\n";
