@component('admin.partials._batch_toolbar_wrap', ['entityLabel' => '优惠券'])
  <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
    <button type="button" class="btn btn-sm btn-success" data-coupon-batch="enable">批量启用</button>
    <button type="button" class="btn btn-sm btn-default" data-coupon-batch="disable">批量停用</button>
    <input type="number" class="form-control input-sm batch-coupon-add-total" placeholder="增加发放量" min="1" style="width: 110px;">
    <button type="button" class="btn btn-sm btn-primary" data-coupon-batch="add-total">应用</button>
    <input type="number" class="form-control input-sm batch-coupon-extend-days" placeholder="延长天数" min="1" style="width: 90px;">
    <button type="button" class="btn btn-sm btn-warning" data-coupon-batch="extend-expiry">延长失效</button>
  </div>
@endcomponent
