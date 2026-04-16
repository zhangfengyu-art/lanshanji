@extends(is_site_mode_b() ? 'b_mode.layouts.app' : 'layouts.app')
@section('title', trans('frontend.order.order_list'))

@section('content')
<style>
  body.site-mode-b .b-orders-wrap {
    max-width: 1040px;
    margin: 6px auto 22px;
    padding: 0 6px;
  }

  body.site-mode-b .b-orders-intro {
    margin-bottom: 10px;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid rgba(44, 123, 229, 0.18);
    background: linear-gradient(135deg, #ffffff 0%, #f7fafc 100%);
  }

  body.site-mode-b .b-orders-intro p {
    margin: 0;
    color: #64748b;
    font-size: 12px;
  }

  body.site-mode-b .b-orders-wrap .panel {
    border-radius: 16px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05);
  }

  body.site-mode-b .b-orders-wrap .panel-heading {
    padding: 14px 16px;
    font-size: 15px;
    font-weight: 700;
  }

  body.site-mode-b .b-orders-wrap .panel-body {
    padding: 14px 16px 16px;
  }

  body.site-mode-b .b-orders-wrap .table > thead > tr > th {
    background: #f8fafc;
    color: #64748b;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
  }

  body.site-mode-b .b-orders-wrap .table > tbody > tr > td {
    border-top: 1px solid rgba(15, 23, 42, 0.08);
    vertical-align: middle;
  }

  body.site-mode-b .b-orders-wrap .list-group-item {
    border: 0;
    padding: 0;
    margin-bottom: 12px;
    background: transparent;
  }

  body.site-mode-b .b-orders-wrap .list-group-item:last-child {
    margin-bottom: 0;
  }

  body.site-mode-b .b-orders-wrap .list-group-item .panel {
    box-shadow: none;
    border-radius: 12px;
  }

  body.site-mode-b .b-orders-empty {
    padding: 18px 14px;
  }

  body.site-mode-b .b-orders-wrap .pagination > li > a,
  body.site-mode-b .b-orders-wrap .pagination > li > span {
    border-radius: 9px;
    margin: 0 2px;
    border-color: rgba(15, 23, 42, 0.12);
    color: #334155;
  }

  body.site-mode-b .b-orders-wrap .pagination > .active > a,
  body.site-mode-b .b-orders-wrap .pagination > .active > span {
    background: rgba(44, 123, 229, 0.14);
    border-color: rgba(44, 123, 229, 0.24);
    color: #1d4ed8;
  }

  .order-status-pill { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
  .order-status-pill.status-warning { background: #fff4e5; color: #b45309; }
  .order-status-pill.status-info { background: #e8f1ff; color: #1d4ed8; }
  .order-status-pill.status-success { background: #e8f8ef; color: #0f7a3f; }
  .order-status-pill.status-neutral { background: #f3f4f6; color: #374151; }

  .order-budget-chip {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    background: rgba(44, 123, 229, 0.12);
    color: #1e3a8a;
    font-weight: 700;
    font-size: 12px;
    letter-spacing: 0.01em;
  }

  .order-tracking-cell {
    min-width: 150px;
  }

  .order-tracking-wrap {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    justify-content: center;
    flex-wrap: wrap;
  }

  .order-tracking-no {
    display: inline-block;
    max-width: 220px;
    padding: 3px 8px;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid rgba(15, 23, 42, 0.08);
    color: #1f2937;
    font-size: 12px;
    word-break: break-all;
  }

  .order-tracking-empty {
    color: #94a3b8;
    font-size: 12px;
  }

  .btn-copy-tracking {
    padding: 2px 8px;
    font-size: 12px;
    line-height: 1.4;
    border-radius: 8px;
    transition: all 0.2s ease;
  }

  .btn-copy-tracking:disabled {
    background-color: #e5e7eb;
    border-color: #d1d5db;
    color: #9ca3af;
    cursor: not-allowed;
    opacity: 0.6;
  }

  .btn-copy-tracking:disabled:hover {
    background-color: #e5e7eb;
    border-color: #d1d5db;
    color: #9ca3af;
  }
</style>
<div class="b-orders-wrap">
@if(is_site_mode_b())
<div class="b-orders-intro">
  <p>这里汇总你的普通订单与求购委托，支持快速查看状态与明细。</p>
</div>
@endif
<div class="row">
<div class="col-lg-10 col-lg-offset-1">
  @if(is_site_mode_b() && isset($procurementOrders) && $procurementOrders->count())
  <div class="panel panel-default">
    <div class="panel-heading">我的求购委托</div>
    <div class="panel-body">
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>委托单号</th>
              <th>求购物品</th>
              <th class="text-center">预算金额</th>
              <th class="text-center">状态</th>
              <th class="text-center">创建时间</th>
              <th class="text-center">操作</th>
            </tr>
          </thead>
          <tbody>
            @foreach($procurementOrders as $procurementOrder)
              @php
                $statusMap = [
                  \App\Models\ProcurementOrder::STATUS_PENDING => ['text' => '等待接单', 'class' => 'status-warning'],
                  \App\Models\ProcurementOrder::STATUS_ACCEPTED => ['text' => '已接单', 'class' => 'status-info'],
                  \App\Models\ProcurementOrder::STATUS_SOURCING => ['text' => '采购中', 'class' => 'status-info'],
                  \App\Models\ProcurementOrder::STATUS_SHIPPED => ['text' => '已发货', 'class' => 'status-success'],
                ];
                $status = data_get($statusMap, $procurementOrder->proxy_status, ['text' => '等待接单', 'class' => 'status-warning']);
                $snapshotName = trim((string) data_get($procurementOrder, 'extra.reference_snapshot.item_name', ''));
                $displayItemName = trim((string) data_get($procurementOrder, 'item_name', ''));
                if ($displayItemName === '' || preg_match('/^\d+$/', $displayItemName)) {
                  $displayItemName = $snapshotName !== '' ? $snapshotName : '待匹配代购商品';
                }
                $detailUrl = route('procurement.detail', [
                  'item_name' => $displayItemName,
                  'item_image' => $procurementOrder->item_image,
                  'budget_amount' => $procurementOrder->budget_amount,
                  'narrative' => $procurementOrder->order_narrative,
                  'native_request' => 1,
                ]);
              @endphp
              <tr>
                <td>{{ $procurementOrder->no }}</td>
                <td>{{ $displayItemName }}</td>
                <td class="text-center"><span class="order-budget-chip">JPY ¥{{ number_format((float) $procurementOrder->budget_amount, 0) }}</span></td>
                <td class="text-center"><span class="order-status-pill {{ $status['class'] }}">{{ $status['text'] }}</span></td>
                <td class="text-center">{{ optional($procurementOrder->created_at)->format('Y-m-d H:i:s') }}</td>
                <td class="text-center"><a class="btn btn-primary btn-xs" href="{{ $detailUrl }}">查看详情</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
  @endif

<div class="panel panel-default">
  <div class="panel-heading">{{ trans('frontend.order.order_list') }}</div>
  <div class="panel-body">
    @if($orders->count())
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
                  <th class="text-center">运单号</th>
                  <th class="text-center">{{ trans('frontend.order.operation') }}</th>
                </tr>
              </thead>
              @foreach($order->items as $index => $item)
              <tr>
                <td class="product-info">
                  @php
                    $product = $item->product;
                    $productImageUrl = $product ? $product->image_url : asset('images/brand-logo.svg');
                    $productTitle = $product ? $product->title : trans('frontend.product.product_deleted');
                  @endphp
                  <div class="preview">
                    <a target="_blank" href="{{ route('products.show', [$item->product_id]) }}">
                      <img src="{{ $productImageUrl }}">
                    </a>
                  </div>
                  <div>
                    <span class="product-title">
                       <a target="_blank" href="{{ route('products.show', [$item->product_id]) }}">{{ $productTitle }}</a>
                     </span>
                    <span class="sku-title">{{ optional($item->productSku)->title }}</span>
                  </div>
                </td>
                <td class="sku-price text-center">{{ number_format($item->price, 2, '.', '') }}日元</td>
                <td class="sku-amount text-center">{{ $item->amount }}</td>
                @if($index === 0)
                @php
                  $displayStatus = $order->display_status;
                  $trackingNo = trim((string) ($order->tracking_no ?: data_get($order->ship_data, 'express_no', '')));
                  $fulfillmentPhotoUrl = trim((string) $order->fulfillment_photo) !== ''
                    ? route('order.photo.fulfillment', ['order_no' => $order->no])
                    : '';
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
                <td rowspan="{{ count($order->items) }}" class="text-center total-amount">{{ number_format($order->total_amount, 2, '.', '') }}日元</td>
                <td rowspan="{{ count($order->items) }}" class="text-center">
                  <span class="order-status-pill {{ $statusClass }}">{{ $displayStatus }}</span>
                </td>
                <td rowspan="{{ count($order->items) }}" class="text-center order-tracking-cell">
                  <div class="order-tracking-wrap">
                    @if($trackingNo !== '')
                      <span class="order-tracking-no">{{ $trackingNo }}</span>
                      <button type="button" class="btn btn-default btn-xs btn-copy-tracking" data-tracking-no="{{ $trackingNo }}">复制</button>
                    @else
                      <span class="order-tracking-empty">待上传</span>
                      <button type="button" class="btn btn-default btn-xs btn-copy-tracking" disabled>复制</button>
                    @endif
                  </div>
                </td>
                <td rowspan="{{ count($order->items) }}" class="text-center">
                  <a class="btn btn-primary btn-xs" href="{{ route('orders.show', ['order' => $order->id]) }}">{{ trans('frontend.order.view_order') }}</a>
                  @if($fulfillmentPhotoUrl !== '')
                    <a class="btn btn-info btn-xs" href="{{ $fulfillmentPhotoUrl }}" target="_blank">查看实拍</a>
                  @else
                    <button type="button" class="btn btn-default btn-xs" disabled>实拍待传</button>
                  @endif
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
    @else
      <div class="b-orders-empty b-empty-state">
        <p style="margin:0;">{{ is_site_mode_b() ? '你还没有可展示的订单。' : '你还没有烟草订单。' }}</p>
        <a href="{{ route('products.index') }}" class="btn btn-primary btn-sm">{{ is_site_mode_b() ? '去逛逛求购广场' : '去逛逛商品列表' }}</a>
      </div>
    @endif
    <div class="pull-right">{{ $orders->render() }}</div>
  </div>
</div>
</div>
</div>
</div>
@endsection

@section('scriptsAfterJs')
<script>
  $(function () {
    function fallbackCopyText(text) {
      var $temp = $('<textarea readonly></textarea>');
      $temp.css({ position: 'fixed', top: '-9999px', left: '-9999px' });
      $temp.val(text);
      $('body').append($temp);
      $temp[0].focus();
      $temp[0].select();
      var copied = false;
      try {
        copied = document.execCommand('copy');
      } catch (e) {
        copied = false;
      }
      $temp.remove();
      return copied;
    }

    function notifyCopyResult(success) {
      if (typeof swal === 'function') {
        if (success) {
          swal('运单号已复制', '', 'success');
        } else {
          swal('复制失败，请手动复制', '', 'error');
        }
        return;
      }
      window.alert(success ? '运单号已复制' : '复制失败，请手动复制');
    }

    $('.btn-copy-tracking').on('click', function () {
      if ($(this).prop('disabled')) {
        return;
      }
      
      var trackingNo = String($(this).data('tracking-no') || '').trim();
      if (!trackingNo) {
        notifyCopyResult(false);
        return;
      }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(trackingNo).then(function () {
          notifyCopyResult(true);
        }).catch(function () {
          notifyCopyResult(fallbackCopyText(trackingNo));
        });
        return;
      }

      notifyCopyResult(fallbackCopyText(trackingNo));
    });
  });
</script>
@endsection