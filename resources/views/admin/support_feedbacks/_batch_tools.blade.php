@component('admin.partials._batch_toolbar_wrap', ['entityLabel' => '反馈'])
  <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 8px;">
    <button type="button" class="btn btn-sm btn-success" data-feedback-batch="mark-handled">批量标为已回复</button>
    <button type="button" class="btn btn-sm btn-default" data-feedback-batch="mark-pending">批量改回待处理</button>
  </div>
  <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
    <input type="text" class="form-control input-sm batch-feedback-reply" placeholder="统一回复内容" style="width: 320px; max-width: 100%;">
    <button type="button" class="btn btn-sm btn-primary" data-feedback-batch="reply">批量回复并结案</button>
    <span class="text-muted" style="font-size: 12px;">将写入管理员回复并标记为已回复</span>
  </div>
@endcomponent
