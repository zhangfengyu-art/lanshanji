@extends('layouts.app')
@section('title', trans('frontend.order.order_list'))

@section('content')
<style>
  .order-status-pill { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
  .order-status-pill.status-warning { background: #fff4e5; color: #b45309; }
  .order-status-pill.status-info { background: #e8f1ff; color: #1d4ed8; }
  .order-status-pill.status-success { background: #e8f8ef; color: #0f7a3f; }
  .order-status-pill.status-neutral { background: #f3f4f6; color: #374151; }
</style>
<div class="row">
<div class="col-lg-10 col-lg-offset-1">
<div class="panel panel-default">
  <div class="panel-heading">{{ trans('frontend.order.order_list') }}</div>
  <div class="panel-body">
    <ul class="list-group">
      @foreach($orders as $order)
      <li class="list-group-item">
        <div class="panel panel-default">
          <div class="panel-heading">
            {{ trans('frontend.order.order_no') }}：{{ $order->no }}
            <span class="pull-right">{{ $order->created_at->format('Y-m-d H:i:s') }}</span>
          </div>
          <div class="panel-body">
            <table class="table">
              <thead>
                <tr>
                  <th>{{ trans('frontend.order.products') }}</th>
                  <th class="text-center">{{ trans('frontend.cart.unit_price') }}</th>
                  <th class="text-center">{{ trans('frontend.cart.quantity') }}</th>
                  <th class="text-center">{{ trans('frontend.order.total_price') }}</th>
                  <th class="text-center">{{ trans('frontend.order.status') }}</th>
                  <th class="text-center">{{ trans('frontend.order.operation') }}</th>
                </tr>
              </thead>
              @foreach($order->items as $index => $item)
              <tr>
                <td class="product-info">
                  @if($item->product)
                  <div class="preview">
                    <a target="_blank" href="{{ route('products.show', [$item->product_id]) }}">
                      <img src="{{ $item->product->image_url }}">
                    </a>
                  </div>
                  <div>
                    <span class="product-title">
                       <a target="_blank" href="{{ route('products.show', [$item->product_id]) }}">{{ $item->product->title }}</a>
                     </span>
                    <span class="sku-title">{{ $item->productSku->title }}</span>
                  </div>
                  @else
                  <div class="preview">
                    <img src="{{ asset('images/brand-logo.svg') }}">
                  </div>
                  <div>
                    <span class="product-title text-muted">{{ trans('frontend.product.product_deleted') }}</span>
                  </div>
                  @endif
                </td>
                <td class="sku-price text-center">￥{{ $item->price }}</td>
                <td class="sku-amount text-center">{{ $item->amount }}</td>
                @if($index === 0)
                <td rowspan="{{ count($order->items) }}" class="text-center total-amount">￥{{ $order->total_amount }}</td>
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
                <td rowspan="{{ count($order->items) }}" class="text-center">
                  <span class="order-status-pill {{ $statusClass }}">{{ $displayStatus }}</span>
                </td>
                <td rowspan="{{ count($order->items) }}" class="text-center">
                  <a class="btn btn-primary btn-xs" href="{{ route('orders.show', ['order' => $order->id]) }}">{{ trans('frontend.order.view_order') }}</a>
                  @if($order->paid_at)
                  <a class="btn btn-success btn-xs" href="{{ route('orders.review.show', ['order' => $order->id]) }}">
                  {{ $order->reviewed ? trans('frontend.order.view_review') : trans('frontend.order.review') }}
                  </a>
                  @endif
                </td>
                @endif
              </tr>
              @endforeach
            </table>
          </div>
        </div>
      </li>
      @endforeach
    </ul>
    <div class="pull-right">{{ $orders->render() }}</div>
  </div>
</div>
</div>
</div>
@endsection