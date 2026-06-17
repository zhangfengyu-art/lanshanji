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
      <i class="fa fa-cubes"></i> 进入备货/打包（→S3）
    </button>
    <button type="button" class="btn btn-sm btn-default" data-order-batch="unlock">
      <i class="fa fa-undo"></i> 退回上一阶段（S3）
    </button>
    <button type="button" class="btn btn-sm btn-info" data-order-batch="logistics-warehouse">
      <i class="fa fa-truck"></i> 标记送往物流仓库
    </button>
  </div>
  <p class="text-muted" style="margin: 8px 0 0; font-size: 12px;">
    S1 待处理 · S2 处理中 · S3 备货/打包（含锁定/实拍图/送仓）· S4 已发货。各按钮仅对当前阶段符合条件的订单生效。列表「实拍图」列可逐单上传；顶部「批量导入实拍图」支持多选图片，文件名需含订单流水号（如 <code>20260618004454849165.jpg</code>）。
  </p>
</div>
@endif
