@extends('layouts.app')
@section('title', trans('frontend.order.order_list'))

@section('content')
<div class="row orders-index-shell">
  <div class="col-lg-10 col-lg-offset-1">
    <div class="panel panel-default orders-index-panel">
      <div class="panel-heading">{{ trans('frontend.order.order_list') }}</div>
      <div class="panel-body">
        <ul class="list-group orders-index-list">
          @foreach($orders as $order)
            @php
              $displayStatus = $order->display_status;
              $statusClass = 'status-neutral';
              if (is_site_mode_b() && strpos($displayStatus, '待履行') !== false) {
                $statusClass = 'status-warning';
              } elseif (is_site_mode_b() && strpos($displayStatus, '已入关') !== false) {
                $statusClass = 'status-info';
              } elseif (is_site_mode_b() && strpos($displayStatus, '已签收') !== false) {
                $statusClass = 'status-success';
              } elseif (strpos($displayStatus, '已支付') !== false || strpos($displayStatus, '退款成功') !== false) {
                $statusClass = 'status-success';
              }
            @endphp
            <li class="list-group-item">
              <article class="order-list-card">
                <header class="order-list-card__head">
                  <span class="order-list-card__no">{{ trans('frontend.order.order_no') }}：{{ $order->no }}</span>
                  <time class="order-list-card__time">{{ $order->created_at->format('Y-m-d H:i:s') }}</time>
                </header>

                <div class="order-list-card__table-head" aria-hidden="true">
                  <span>{{ trans('frontend.order.products') }}</span>
                  <span>{{ trans('frontend.cart.unit_price') }}</span>
                  <span>{{ trans('frontend.cart.quantity') }}</span>
                </div>

                <div class="order-list-card__items">
                  @foreach($order->items as $item)
                    <div class="order-item-row">
                      <div class="order-item-row__product product-info">
                        @if($item->product)
                          <div class="preview">
                            <a target="_blank" href="{{ route('products.show', [$item->product_id]) }}">
                              <img src="{{ $item->product->image_url }}" alt="{{ $item->product->title }}">
                            </a>
                          </div>
                          <div class="order-item-row__text">
                            <span class="product-title">
                              <a target="_blank" href="{{ route('products.show', [$item->product_id]) }}">{{ $item->product->title }}</a>
                            </span>
                            <span class="sku-title">{{ $item->productSku->title }}</span>
                          </div>
                        @else
                          <div class="preview">
                            <img src="{{ asset('images/brand-logo.svg') }}" alt="">
                          </div>
                          <div class="order-item-row__text">
                            <span class="product-title text-muted">{{ trans('frontend.product.product_deleted') }}</span>
                          </div>
                        @endif
                      </div>

                      <div class="order-item-row__price">
                        <span class="order-item-row__label">{{ trans('frontend.cart.unit_price') }}</span>
                        <span class="order-item-row__value">{{ format_shop_price($item->price) }}</span>
                      </div>

                      <div class="order-item-row__qty">
                        <span class="order-item-row__label">{{ trans('frontend.cart.quantity') }}</span>
                        <span class="order-item-row__value">{{ $item->amount }}</span>
                      </div>
                    </div>
                  @endforeach
                </div>

                <footer class="order-list-card__foot">
                  <div class="order-list-card__total">
                    <span class="order-list-card__label">{{ trans('frontend.order.total_price') }}</span>
                    <strong class="total-amount">{{ format_shop_price($order->total_amount) }}</strong>
                  </div>
                  <div class="order-list-card__status">
                    <span class="order-list-card__label">{{ trans('frontend.order.status') }}</span>
                    <span class="order-status-pill {{ $statusClass }}">{{ $displayStatus }}</span>
                  </div>
                  <div class="order-list-card__actions">
                    <span class="order-list-card__label">{{ trans('frontend.order.operation') }}</span>
                    <div class="order-list-card__action-btns">
                      <a class="btn btn-primary btn-xs" href="{{ route('orders.show', ['order' => $order->id]) }}">{{ trans('frontend.order.view_order') }}</a>
                      @if($order->paid_at)
                        <a class="btn btn-success btn-xs" href="{{ route('orders.review.show', ['order' => $order->id]) }}">
                          {{ $order->reviewed ? trans('frontend.order.view_review') : trans('frontend.order.review') }}
                        </a>
                      @endif
                    </div>
                  </div>
                </footer>
              </article>
            </li>
          @endforeach
        </ul>
        <div class="orders-index-pagination">{{ $orders->render() }}</div>
      </div>
    </div>
  </div>
</div>
@endsection
