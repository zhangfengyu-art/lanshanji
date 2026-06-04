<div class="admin-batch-toolbar" style="margin: 10px 0 14px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
  <div style="font-weight: 600; margin-bottom: 10px; color: #334155;">
    <i class="fa fa-check-square-o"></i> 批量操作（请先勾选下方{{ $entityLabel ?? '记录' }}）
  </div>
  {{ $slot }}
</div>
