@component('admin.partials._batch_toolbar_wrap', ['entityLabel' => '用户'])
  <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
    <button type="button" class="btn btn-sm btn-danger" data-user-batch="ban">批量封禁</button>
    <button type="button" class="btn btn-sm btn-success" data-user-batch="unban">批量解封</button>
    <button type="button" class="btn btn-sm btn-warning" data-user-batch="reset-session">批量重置登录态</button>
    <button type="button" class="btn btn-sm btn-info" data-user-batch="verify-email">批量标记邮箱已验证</button>
  </div>
@endcomponent
