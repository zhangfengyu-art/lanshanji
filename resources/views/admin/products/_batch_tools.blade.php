<div class="product-batch-toolbar" style="margin: 10px 0 14px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
  <div style="font-weight: 600; margin-bottom: 10px; color: #334155;">
    <i class="fa fa-check-square-o"></i> 批量操作（请先勾选下方商品）
  </div>

  <div style="display: flex; flex-wrap: wrap; gap: 12px 16px; align-items: center;">
    <span class="text-muted" style="font-size: 12px;">调价</span>
    <select class="form-control input-sm batch-price-mode" style="width: 100px;">
      <option value="percent">按 %</option>
      <option value="fixed">±日元</option>
    </select>
    <input type="number" class="form-control input-sm batch-price-value" placeholder="数值" step="0.01" style="width: 90px;">
    <button type="button" class="btn btn-sm btn-danger" data-batch-action="adjust-price">批量调价</button>

    <span style="width: 1px; height: 24px; background: #e2e8f0;"></span>

    <span class="text-muted" style="font-size: 12px;">调重</span>
    <input type="number" class="form-control input-sm batch-weight-grams" placeholder="重量(g)" min="1" style="width: 90px;">
    <button type="button" class="btn btn-sm btn-primary" data-batch-action="logistics">批量填物流</button>

    <span style="width: 1px; height: 24px; background: #e2e8f0;"></span>

    <span class="text-muted" style="font-size: 12px;">限购</span>
    <input type="number" class="form-control input-sm batch-limit-only" placeholder="件/单" min="1" style="width: 80px;">
    <button type="button" class="btn btn-sm btn-warning" data-batch-action="purchase-limit">批量限购</button>
  </div>
</div>
