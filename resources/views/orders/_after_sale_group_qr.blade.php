@if(!empty($afterSaleGroupQrUrl))
  <div class="after-sale-group-qr" role="region" aria-label="{{ $afterSaleGroupNotice }}">
    <p class="after-sale-group-qr__title">{{ $afterSaleGroupNotice }}</p>
    <p class="after-sale-group-qr__hint">{{ trans('frontend.order.after_sale_group_hint') }}</p>
    <img class="after-sale-group-qr__image" src="{{ $afterSaleGroupQrUrl }}" alt="{{ $afterSaleGroupNotice }}">
  </div>
@endif
