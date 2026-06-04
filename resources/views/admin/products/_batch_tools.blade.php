@php
  use App\Models\Product;
  use App\Models\ProductSku;
  use App\Services\ShippingModeService;
  use App\Services\OrderTobaccoLimitService;
@endphp
<div class="product-batch-toolbar" style="margin: 10px 0 14px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
  <div style="font-weight: 600; margin-bottom: 10px; color: #334155;">
    <i class="fa fa-check-square-o"></i> 批量操作（请先勾选下方商品）
  </div>

  <div class="row" style="margin-bottom: 8px;">
    <div class="col-md-12" style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
      <select class="form-control input-sm batch-category-select" style="width: 160px;">
        <option value="">批量改分类</option>
        @foreach($categories as $id => $name)
          <option value="{{ $id }}">{{ $name }}</option>
        @endforeach
      </select>
      <button type="button" class="btn btn-sm btn-primary" data-batch-action="category">应用分类</button>

      <select class="form-control input-sm batch-shipping-mode" style="width: 140px;">
        <option value="">寄送模式</option>
        @foreach(Product::shippingModeOptions() as $k => $v)
          <option value="{{ $k }}">{{ $v }}</option>
        @endforeach
      </select>
      <button type="button" class="btn btn-sm btn-primary" data-batch-action="shipping-mode">应用</button>

      <select class="form-control input-sm batch-tobacco-type" style="width: 130px;">
        <option value="">烟草分类</option>
        @foreach(Product::tobaccoTypeOptions() as $k => $v)
          <option value="{{ $k }}">{{ $v }}</option>
        @endforeach
      </select>
      <button type="button" class="btn btn-sm btn-primary" data-batch-action="tobacco-type">应用</button>

      <select class="form-control input-sm batch-sale-status" style="width: 120px;">
        <option value="">销售状态</option>
        @foreach(Product::saleStatusOptions() as $k => $v)
          <option value="{{ $k }}">{{ $v }}</option>
        @endforeach
      </select>
      <input type="number" class="form-control input-sm batch-purchase-limit" placeholder="限购数" min="1" style="width: 72px; display: none;">
      <button type="button" class="btn btn-sm btn-primary" data-batch-action="sale-status">应用</button>

      <button type="button" class="btn btn-sm btn-success" data-batch-action="on-sale" data-on-sale="1">批量上架</button>
      <button type="button" class="btn btn-sm btn-default" data-batch-action="on-sale" data-on-sale="0">批量下架</button>
    </div>
  </div>

  <div class="row" style="margin-bottom: 8px;">
    <div class="col-md-12" style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
      <input type="number" class="form-control input-sm batch-weight-grams" placeholder="重量(g)" min="1" style="width: 90px;">
      <input type="number" class="form-control input-sm batch-unit-sticks" placeholder="支数" min="1" style="width: 72px;">
      <label style="font-weight: normal; margin: 0;">
        <input type="checkbox" class="batch-only-empty" value="1"> 仅填空项
      </label>
      <button type="button" class="btn btn-sm btn-primary" data-batch-action="logistics">批量填物流</button>

      <input type="number" class="form-control input-sm batch-limit-only" placeholder="限购件/单" min="1" style="width: 90px;">
      <button type="button" class="btn btn-sm btn-warning" data-batch-action="purchase-limit">批量限购</button>

      <button type="button" class="btn btn-sm btn-info" data-batch-action="inherit-category">继承分类寄送模式</button>
    </div>
  </div>

  <div class="row">
    <div class="col-md-12" style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
      <select class="form-control input-sm batch-price-mode" style="width: 100px;">
        <option value="percent">调价 %</option>
        <option value="fixed">调价 ±日元</option>
      </select>
      <input type="number" class="form-control input-sm batch-price-value" placeholder="数值" step="0.01" style="width: 90px;">
      <button type="button" class="btn btn-sm btn-danger" data-batch-action="adjust-price">批量调价</button>
      <span class="text-muted" style="font-size: 12px;">调价作用于所有 SKU，请谨慎操作</span>

      <a href="{{ route('admin.products.export_incomplete_logistics') }}" class="btn btn-sm btn-default" target="_blank" style="margin-left: auto;">
        <i class="fa fa-download"></i> 导出物流未完备商品
      </a>
    </div>
  </div>
</div>
