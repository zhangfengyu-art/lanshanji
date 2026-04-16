@extends('b_mode.layouts.app')
@section('title', trans('frontend.order.view_order'))

@section('content')
@php
  $isPaid = !is_null($order->paid_at);
  $isClosed = (bool) $order->closed;
  $isAllocationVoided = (bool) data_get($order->extra, 'allocation_voided', false);
  $createdAtText = optional($order->created_at)->format('Y-m-d H:i');
  $orderStatusText = $order->display_status;
  $isDomesticInTransit = is_site_mode_b() && $orderStatusText === '已入关/转寄中';

  if (!$isPaid && !$isClosed) {
    $expiresInSeconds = $order->getAllocationExpiresIn();
  }

  $statusClass = 'is-neutral';
  if (is_site_mode_b() && strpos($orderStatusText, '待履行') !== false) {
    $statusClass = 'is-pending';
  } elseif (is_site_mode_b() && strpos($orderStatusText, '已入关') !== false) {
    $statusClass = 'is-info';
  } elseif (is_site_mode_b() && strpos($orderStatusText, '已签收') !== false) {
    $statusClass = 'is-success';
  } elseif (!$isPaid && !$isClosed) {
      $statusClass = 'is-pending';
  } elseif ($isPaid) {
      $statusClass = 'is-success';
  } elseif ($isClosed) {
      $statusClass = 'is-closed';
  }
@endphp

<style>
  body.site-mode-b .order-shell {
    margin-top: 2px;
    margin-bottom: 18px;
  }

  body.site-mode-b .top-strip {
    display: grid;
    grid-template-columns: 1.15fr .85fr;
    gap: 14px;
    margin-bottom: 14px;
  }

  body.site-mode-b .top-card {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
    padding: 18px;
  }

  body.site-mode-b .order-title {
    margin: 10px 0 8px;
    font-size: 24px;
    font-weight: 800;
    letter-spacing: 0.01em;
  }

  body.site-mode-b .meta-lines {
    color: #64748b;
    font-size: 13px;
    margin-bottom: 4px;
  }

  body.site-mode-b .status-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 5px 12px;
    font-size: 12px;
    font-weight: 700;
  }

  .status-pill.is-info { background: #e8f1ff; color: #1d4ed8; }
  .status-pill.is-pending { background: #fff4e5; color: #b45309; }
  .status-pill.is-success { background: #e8f8ef; color: #0f7a3f; }
  .status-pill.is-neutral { background: #f3f4f6; color: #334155; }
  .status-pill.is-closed { background: #f4f4f5; color: #52525b; }

  body.site-mode-b .countdown-box {
    display: inline-flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 12px;
    padding: 10px 12px;
    border-radius: 12px;
    background: rgba(44, 123, 229, 0.08);
  }

  body.site-mode-b .countdown-label {
    font-size: 12px;
    color: #64748b;
  }

  body.site-mode-b .countdown-timer {
    font-size: 20px;
    font-weight: 800;
    color: #1e3a8a;
  }

  body.site-mode-b .amount-label {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 4px;
  }

  body.site-mode-b .amount-value {
    font-size: 30px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.15;
  }

  body.site-mode-b .action-guide {
    margin-top: 8px;
    color: #64748b;
    font-size: 12px;
  }

  body.site-mode-b .pay-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-top: 12px;
  }

  /* Keep payment module visually consistent with A-site for cross-site jump continuity */
  body.site-mode-b .pay-card.is-a-style-pay {
    border-radius: 10px;
    border-color: #e5e7eb;
    box-shadow: none;
  }

  body.site-mode-b .pay-card.is-a-style-pay .amount-value {
    font-size: 32px;
    font-weight: 700;
    color: #d9534f;
    letter-spacing: 0;
  }

  body.site-mode-b .pay-card.is-a-style-pay .countdown-box {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
  }

  body.site-mode-b .pay-card.is-a-style-pay .countdown-timer {
    color: #334155;
  }

  body.site-mode-b .pay-card.is-a-style-pay .pay-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  body.site-mode-b .pay-card.is-a-style-pay .js-pay-link {
    min-width: 122px;
    min-height: 36px;
    border-radius: 4px;
    border: 1px solid transparent;
    box-shadow: none;
    font-size: 14px;
    font-weight: 400;
  }

  body.site-mode-b .pay-card.is-a-style-pay .js-pay-link.alipay {
    background: #337ab7;
    border-color: #2e6da4;
    color: #fff;
  }

  body.site-mode-b .pay-card.is-a-style-pay .js-pay-link.wechat {
    background: #5cb85c;
    border-color: #4cae4c;
    color: #fff;
  }

  body.site-mode-b .pay-card.is-a-style-pay .js-pay-link.is-loading,
  body.site-mode-b .pay-card.is-a-style-pay .js-pay-link.is-disabled {
    opacity: 0.8;
    pointer-events: none;
  }

  body.site-mode-b .pay-card.is-a-style-pay .action-guide {
    color: #666;
    font-size: 12px;
  }

  body.site-mode-b .pay-btn {
    border: 0;
    border-radius: 12px;
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    color: #1f2937;
    background: #f6ad55;
  }

  body.site-mode-b .main-grid {
    display: grid;
    grid-template-columns: 1.2fr .8fr;
    gap: 14px;
  }

  body.site-mode-b .card {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05);
    overflow: hidden;
  }

  body.site-mode-b .card-head {
    padding: 14px 16px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 700;
  }

  body.site-mode-b .items-wrap {
    padding: 8px 12px 12px;
  }

  body.site-mode-b .items-legend {
    display: grid;
    grid-template-columns: 1.2fr .55fr .35fr .55fr;
    gap: 10px;
    padding: 8px 8px 10px;
    font-size: 12px;
    color: #64748b;
  }

  body.site-mode-b .item-row {
    display: grid;
    grid-template-columns: 1.2fr .55fr .35fr .55fr;
    gap: 10px;
    align-items: center;
    padding: 10px 8px;
    border-top: 1px dashed rgba(15, 23, 42, 0.08);
  }

  body.site-mode-b .thumb {
    width: 64px;
    height: 64px;
    border-radius: 10px;
    overflow: hidden;
    background: #eef3fb;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
    float: left;
  }

  body.site-mode-b .thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  body.site-mode-b .thumb-fallback {
    font-size: 11px;
    color: #64748b;
  }

  body.site-mode-b .main {
    min-height: 64px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  body.site-mode-b .item-title {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
  }

  body.site-mode-b .item-sku {
    margin-top: 4px;
    font-size: 12px;
    color: #64748b;
  }

  body.site-mode-b .metric {
    font-size: 13px;
    font-weight: 600;
    color: #334155;
  }

  body.site-mode-b .metric.subtotal {
    color: #0f172a;
    font-weight: 700;
  }

  body.site-mode-b .scroll-hint {
    padding: 0 16px 14px;
    color: #94a3b8;
    font-size: 11px;
  }

  body.site-mode-b .side-body {
    padding: 14px 16px 16px;
  }

  body.site-mode-b .kv {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
    font-size: 13px;
  }

  body.site-mode-b .k {
    width: 110px;
    color: #64748b;
    flex-shrink: 0;
  }

  body.site-mode-b .v {
    color: #1f2937;
    line-height: 1.6;
  }

  body.site-mode-b .warn-box {
    margin-top: 10px;
    border-radius: 10px;
    padding: 10px 12px;
    background: #fff7ed;
    color: #9a3412;
    font-size: 12px;
  }

  body.site-mode-b .side-actions {
    margin-top: 12px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  body.site-mode-b .mobile-sticky-actions {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 62px;
    z-index: 1032;
    padding: 8px 10px calc(8px + env(safe-area-inset-bottom));
    background: rgba(244, 247, 251, 0.92);
    backdrop-filter: blur(8px);
    border-top: 1px solid rgba(15, 23, 42, 0.08);
    display: grid;
    gap: 8px;
  }

  body.site-mode-b .sticky-btn {
    border: 0;
    border-radius: 12px;
    min-height: 42px;
    font-size: 14px;
    font-weight: 700;
  }

  body.site-mode-b .mobile-sticky-actions .js-pay-link.sticky-btn {
    border-radius: 4px;
    min-height: 38px;
    font-weight: 400;
    color: #fff;
  }

  body.site-mode-b .mobile-sticky-actions .js-pay-link.alipay.sticky-btn {
    background: #337ab7;
    border: 1px solid #2e6da4;
  }

  body.site-mode-b .mobile-sticky-actions .js-pay-link.wechat.sticky-btn {
    background: #5cb85c;
    border: 1px solid #4cae4c;
  }

  @media (max-width: 980px) {
    body.site-mode-b .top-strip,
    body.site-mode-b .main-grid {
      grid-template-columns: 1fr;
    }

    body.site-mode-b .items-legend {
      display: none;
    }

    body.site-mode-b .item-row {
      grid-template-columns: 1fr;
      gap: 6px;
    }

    body.site-mode-b .metric {
      font-size: 12px;
    }
  }
</style>



<div class="row order-shell">
  <div class="col-lg-10 col-lg-offset-1">
    <div class="top-strip">
      <section class="top-card summary-card">
        <span class="status-pill {{ $statusClass }}">{{ $orderStatusText }}</span>
        <h2 class="order-title">{{ trans('frontend.order.order_details') }}</h2>
        <div class="meta-lines">{{ trans('frontend.order.order_no') }}：{{ $order->no }}</div>
        <div class="meta-lines">{{ trans('frontend.order.placed_at') }}：{{ $createdAtText }}</div>
        <div class="meta-lines">{{ is_site_mode_b() ? '履行进度' : trans('frontend.order.shipping_status') }}：{{ is_site_mode_b() ? $orderStatusText : \App\Models\Order::$shipStatusMap[$order->ship_status] }}</div>
      </section>

      <section class="top-card pay-card {{ (!$isPaid && !$isClosed) ? 'is-a-style-pay' : '' }}">
        @if(!$isPaid && !$isClosed && isset($expiresInSeconds))
          <div class="countdown-box">
            <span class="countdown-label">{{ trans('frontend.order.allocation_expires_in') }}</span>
            <span class="countdown-timer" id="countdown-timer" data-expires-in="{{ $expiresInSeconds }}">--:--</span>
          </div>
        @endif

        <div class="amount-label">{{ trans('frontend.cart.payable_amount') }}</div>
        <div class="amount-value">￥{{ number_format($order->total_amount, 2, '.', '') }}</div>

        @if(!$isPaid && !$isClosed)
          <div class="action-guide">请尽快完成支付，支付成功后将继续履约流程。</div>
        @else
          <div class="action-guide">订单支付状态已更新，可在下方查看履约进度。</div>
        @endif

        @if(!$isPaid && !$isClosed)
          <div class="pay-actions">
            <a class="pay-btn alipay js-pay-link" data-loading-text="正在跳转..." href="{{ route('payment.alipay', ['order' => $order->id]) }}">支付宝支付</a>
            <a class="pay-btn wechat js-pay-link" data-loading-text="正在跳转..." href="{{ route('payment.wechat', ['order' => $order->id]) }}">微信支付</a>
          </div>
        @endif
      </section>
    </div>

    <div class="main-grid">
      <section class="card">
        <div class="card-head">
          <span>{{ trans('frontend.order.items_list') }}</span>
          <span class="items-count">共 {{ $order->items->count() }} {{ trans('frontend.cart.item_unit') }}</span>
        </div>
        <div class="items-wrap">
          <div class="items-legend">
            <span class="legend-main">{{ trans('frontend.order.products') }}</span>
            <span class="legend-price">{{ trans('frontend.cart.unit_price') }}</span>
            <span class="legend-qty">{{ trans('frontend.cart.quantity') }}</span>
            <span class="legend-subtotal">小计</span>
          </div>

          @foreach($order->items as $item)
            @php
              $imageUrl = optional($item->product)->image_url;
              $productTitle = optional($item->product)->title ?: '商品已下架';
              $skuTitle = optional($item->productSku)->title ?: trans('frontend.common.none');
            @endphp

            <article class="item-row">
              <a class="thumb" target="_blank" href="{{ route('products.show', [$item->product_id]) }}">
                @if($imageUrl)
                  <img src="{{ $imageUrl }}" alt="{{ $productTitle }}" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                  <span class="thumb-fallback" style="display:none;">{{ trans('frontend.order.no_image') }}</span>
                @else
                  <span class="thumb-fallback">{{ trans('frontend.order.no_image') }}</span>
                @endif
              </a>

              <div class="main">
                <p class="item-title"><a target="_blank" href="{{ route('products.show', [$item->product_id]) }}">{{ $productTitle }}</a></p>
                <div class="item-sku">{{ $skuTitle }}</div>
              </div>

              <div class="metric price">{{ number_format($item->price, 2, '.', '') }}日元</div>
              <div class="metric qty">x {{ (int) $item->amount }}</div>
              <div class="metric subtotal">{{ number_format($item->price * $item->amount, 2, '.', '') }}日元</div>
            </article>
          @endforeach
        </div>
        <div class="scroll-hint">滚动可查看更多商品</div>
      </section>

      <div>
        <section class="card" style="margin-bottom: 12px;">
          <div class="card-head">{{ $isDomesticInTransit ? '国内段转寄信息' : (is_site_mode_b() ? '履行与转寄信息' : trans('frontend.order.shipping_section')) }}</div>
          <div class="side-body">
            <div class="kv"><span class="k">{{ is_site_mode_b() ? '国内转寄地址：' : '收货地址：' }}</span><span class="v">{{ join(' ', $order->address) }}</span></div>
            <div class="kv"><span class="k">订单备注：</span><span class="v">{{ $order->remark ?: '-' }}</span></div>
            @if($order->ship_data)
              <div class="kv"><span class="k">{{ is_site_mode_b() ? '国内段转寄信息：' : '物流信息：' }}</span><span class="v">{{ is_site_mode_b() ? '代购人：' : '' }}{{ data_get($order->ship_data, 'express_company', '-') }} {{ is_site_mode_b() ? '转寄单号：' : '' }}{{ data_get($order->ship_data, 'express_no', '-') }}</span></div>
            @endif

            @if($isAllocationVoided)
              <div class="warn-box">因未在规定时间内完成核账，本次配额已释放。</div>
            @endif
          </div>
        </section>

        <section class="card">
          <div class="card-head">{{ trans('frontend.order.after_sale_section') }}</div>
          <div class="side-body">
            @if($order->couponCode)
              <div class="kv"><span class="k">优惠信息：</span><span class="v">{{ $order->couponCode->description }}</span></div>
            @endif

            <div class="kv"><span class="k">订单状态：</span><span class="v">{{ $orderStatusText }}</span></div>

            @if($order->paid_at && $order->refund_status !== \App\Models\Order::REFUND_STATUS_PENDING)
              <div class="kv"><span class="k">退款状态：</span><span class="v">{{ \App\Models\Order::$refundStatusMap[$order->refund_status] }}</span></div>
              <div class="kv"><span class="k">退款理由：</span><span class="v">{{ data_get($order->extra, 'refund_reason', trans('frontend.common.none')) }}</span></div>
            @endif

            @if(isset($order->extra['refund_disagree_reason']))
              <div class="kv"><span class="k">拒绝退款理由：</span><span class="v">{{ $order->extra['refund_disagree_reason'] }}</span></div>
            @endif

            <div class="side-actions">
              @if($order->ship_status === \App\Models\Order::SHIP_STATUS_DELIVERED)
                <button type="button" class="btn btn-success js-action-receive" data-busy-text="提交中...">{{ is_site_mode_b() ? '确认签收' : '确认收货' }}</button>
              @endif

              @if($order->paid_at && $order->refund_status === \App\Models\Order::REFUND_STATUS_PENDING)
                <button type="button" class="btn btn-danger js-action-refund" data-busy-text="提交中...">申请退款</button>
              @endif
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</div>

@if((!$isPaid && !$isClosed) || $order->ship_status === \App\Models\Order::SHIP_STATUS_DELIVERED || ($order->paid_at && $order->refund_status === \App\Models\Order::REFUND_STATUS_PENDING))
  <div class="mobile-sticky-actions" aria-label="移动端快捷操作">
    @if(!$isPaid && !$isClosed)
      <a class="sticky-btn alipay js-pay-link" data-loading-text="正在跳转..." href="{{ route('payment.alipay', ['order' => $order->id]) }}">支付宝支付</a>
      <a class="sticky-btn wechat js-pay-link" data-loading-text="正在跳转..." href="{{ route('payment.wechat', ['order' => $order->id]) }}">微信支付</a>
    @endif

    @if($order->ship_status === \App\Models\Order::SHIP_STATUS_DELIVERED)
      <button type="button" class="sticky-btn success js-action-receive" data-busy-text="提交中...">{{ is_site_mode_b() ? '确认签收' : '确认收货' }}</button>
    @endif

    @if($order->paid_at && $order->refund_status === \App\Models\Order::REFUND_STATUS_PENDING)
      <button type="button" class="sticky-btn danger js-action-refund" data-busy-text="提交中...">申请退款</button>
    @endif
  </div>
@endif
@endsection

@section('scriptsAfterJs')
<script>
  $(document).ready(function() {
    var orderI18n = @json(trans('frontend.order'));
    var jsI18n = @json(trans('frontend.js'));

    function disablePayActions(disabledText) {
      const text = disabledText || jsI18n.expired || '已过期';
      $('.js-pay-link').each(function() {
        const $link = $(this);
        $link.addClass('is-disabled').attr('aria-disabled', 'true');
        if ($link.is('a')) {
          $link.removeAttr('href');
        }
        $link.text(text);
      });

      const $guide = $('.action-guide');
      if ($guide.length) {
        $guide.text(jsI18n.pay_timeout_closed || '支付时效已结束，订单已关闭').addClass('is-expired');
      }
    }

    function setButtonsBusy($buttons, isBusy, fallbackBusyText) {
      $buttons.each(function() {
        const $button = $(this);
        if (!$button.data('default-text')) {
          $button.data('default-text', $.trim($button.text()));
        }
        const busyText = $button.data('busy-text') || fallbackBusyText || jsI18n.processing || '处理中...';
        $button.prop('disabled', isBusy);
        $button.toggleClass('is-loading', isBusy);
        $button.text(isBusy ? busyText : $button.data('default-text'));
      });
    }

    $('.js-pay-link').on('click', function() {
      if ($(this).hasClass('is-disabled')) {
        return false;
      }
      const $clicked = $(this);
      const loadingText = $clicked.data('loading-text') || jsI18n.loading || '跳转中...';
      $('.js-pay-link').addClass('is-loading').attr('aria-disabled', 'true');
      if (!$clicked.data('default-text')) {
        $clicked.data('default-text', $.trim($clicked.text()));
      }
      $clicked.text(loadingText);
    });

    const timerElement = document.getElementById('countdown-timer');
    if (timerElement) {
      const expiresIn = parseInt(timerElement.dataset.expiresIn, 10);
      let remainingSeconds = expiresIn;

      function updateTimer() {
        if (remainingSeconds <= 0) {
          timerElement.textContent = jsI18n.expired || '已过期';
          timerElement.classList.add('expired');
          disablePayActions(jsI18n.expired || '已过期');
          return;
        }

        const minutes = Math.floor(remainingSeconds / 60);
        const seconds = remainingSeconds % 60;
        timerElement.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        remainingSeconds--;
        setTimeout(updateTimer, 1000);
      }

      updateTimer();
    }

    $('.js-action-receive').click(function() {
      const $receiveButtons = $('.js-action-receive');
      if ($(this).prop('disabled')) {
        return;
      }

      swal({
        title: orderI18n.confirm_received_prompt || '{{ is_site_mode_b() ? '确认已经签收转寄物品？' : '确认已经收到商品？' }}',
        icon: 'warning',
        buttons: ['{{ trans('frontend.common.cancel') }}', orderI18n.confirm_received || '{{ is_site_mode_b() ? '确认签收' : '确认收到' }}'],
        dangerMode: true,
      }).then(function(ret) {
        if (!ret) {
          return;
        }
        setButtonsBusy($receiveButtons, true, '提交中...');
        axios.post('{{ route('orders.received', [$order->id]) }}')
          .then(function () {
            location.reload();
          })
          .catch(function () {
            setButtonsBusy($receiveButtons, false);
            swal('{{ trans('frontend.js.operation_failed_retry') }}', '', 'error');
          });
      });
    });

    $('.js-action-refund').click(function () {
      const $refundButtons = $('.js-action-refund');
      if ($(this).prop('disabled')) {
        return;
      }

      swal({
        text: orderI18n.input_refund_reason || '请输入退款理由',
        content: 'input',
      }).then(function (input) {
        if (!input) {
          swal('{{ trans('frontend.order.refund_reason_required') }}', '', 'error');
          return;
        }
        setButtonsBusy($refundButtons, true, '提交中...');
        axios.post('{{ route('orders.apply_refund', [$order->id]) }}', {reason: input})
          .then(function () {
            swal('{{ trans('frontend.order.refund_requested_success') }}', '', 'success').then(function () {
              location.reload();
            });
          })
          .catch(function () {
            setButtonsBusy($refundButtons, false);
            swal('{{ trans('frontend.js.operation_failed_retry') }}', '', 'error');
          });
      });
    });
  });
</script>
@endsection
