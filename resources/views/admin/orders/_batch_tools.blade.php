@if(is_site_mode_a())
<div class="order-batch-toolbar" style="margin: 10px 0 14px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
  <div style="font-weight: 600; margin-bottom: 10px; color: #334155;">
    <i class="fa fa-check-square-o"></i> 批量履约操作（请先勾选下方订单）
  </div>
  <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
    <button type="button" class="btn btn-sm btn-warning" data-order-batch="start-processing">
      <i class="fa fa-play"></i> 开始处理（S1→S2）
    </button>
    <button type="button" class="btn btn-sm btn-primary" data-order-batch="lock">
      <i class="fa fa-lock"></i> 锁定（→S3）
    </button>
    <button type="button" class="btn btn-sm btn-default" data-order-batch="unlock">
      <i class="fa fa-unlock"></i> 解除锁定
    </button>
    <button type="button" class="btn btn-sm btn-info" data-order-batch="logistics-warehouse">
      <i class="fa fa-truck"></i> 标记送往物流仓库
    </button>
  </div>
  <p class="text-muted" style="margin: 8px 0 0; font-size: 12px;">
    S1 待处理 · S2 处理中 · S3 备货/打包 · S4 已发货。各按钮仅对当前阶段符合条件的订单生效，其余自动跳过。
  </p>
</div>
@endif
