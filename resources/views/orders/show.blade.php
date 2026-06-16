@extends('layouts.app')
@section('title', trans('frontend.order.view_order'))

@section('content')
@php
  $isPaid = !is_null($order->paid_at);
  $isClosed = (bool) $order->closed;
  $isAllocationVoided = (bool) data_get($order->extra, 'allocation_voided', false);
  $createdAtText = optional($order->created_at)->format('Y-m-d H:i');
  $orderStatusText = $order->display_status;
  $isDomesticInTransit = is_site_mode_b() && $orderStatusText === '已入关/转寄中';
  $trackingNoForDetail = trim((string) ($order->tracking_no ?: data_get($order->ship_data, 'express_no', '')));
  $shipStatusTextForDisplay = $trackingNoForDetail !== ''
    ? \App\Models\Order::$shipStatusMap[\App\Models\Order::SHIP_STATUS_DELIVERED]
    : (\App\Models\Order::$shipStatusMap[$order->ship_status] ?? \App\Models\Order::$shipStatusMap[\App\Models\Order::SHIP_STATUS_PENDING]);
  $fulfillmentPhotoUrl = trim((string) $order->fulfillment_photo) !== ''
    ? route('order.photo.fulfillment', ['order_no' => $order->no])
    : '';
  $feeDetails = (array) data_get($order->extra, 'fee_details', []);
  $shoppingReceiptUrl = $order->hasShoppingReceipt()
    ? route('order.receipt.download', ['order_no' => $order->no])
    : '';
  $shippingModeLabel = \App\Services\ShippingModeService::options()[data_get($feeDetails, 'shipping_mode', data_get($order->extra, 'shipping_mode', ''))] ?? '';
  $goodsAmount = (float) data_get($feeDetails, 'base_amount', $order->items->sum(function ($item) {
    return ((float) $item->price) * ((int) $item->amount);
  }));
  $serviceFee = (float) data_get($feeDetails, 'service_fee', 0);
  $packagingFee = (float) data_get($feeDetails, 'packaging_fee', 0);
  $emsShippingFee = (float) data_get($feeDetails, 'ems_shipping_fee', 0);
  $settlementTotal = (float) $order->total_amount;
  $amountJpy = $order->getAmountJpy();
  $amountCny = $order->getPaymentAmountCny();
  $exchangeRate = $order->getExchangeRateJpyPerCny();
  $fulfillmentStage = $order->fulfillment_stage;
  $fulfillmentStageLabel = $order->fulfillment_stage_label;
  $canChangeAddress = $order->canSelfChangeAddress();
  $remainingAddressChanges = $isPaid ? app(\App\Services\OrderFulfillmentService::class)->remainingAddressChanges($order) : 0;
  $canInstantRefund = $isPaid && $order->canSelfInstantRefund();
  $useRefundFeedback = $isPaid && $order->shouldUseRefundFeedback();
  $refundFeedbackUrl = $useRefundFeedback ? app(\App\Services\OrderRefundService::class)->refundFeedbackUrl($order) : null;
  $remainingInstantRefunds = ($canInstantRefund && auth()->check())
    ? app(\App\Services\OrderRefundService::class)->remainingInstantRefundsInWindow(auth()->user())
    : 0;
  $showLegacyRefundApply = $isPaid
    && $order->refund_status === \App\Models\Order::REFUND_STATUS_PENDING
    && !$canInstantRefund
    && !$useRefundFeedback;

  $refundPolicyHint = '';
  if ($isPaid && is_site_mode_a() && $useRefundFeedback) {
    $policyPreview = app(\App\Services\OrderRefundPolicyService::class)->previewAdminRefund($order);
    $refundPolicyHint = $policyPreview['policy_hint'] ?: $policyPreview['message'];
  }

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
  .status-pill.is-info { background: #e8f1ff; color: #1d4ed8; }
  .status-pill.is-pending { background: #fff4e5; color: #b45309; }
  .status-pill.is-success { background: #e8f8ef; color: #0f7a3f; }
</style>
<style>
  .status-pill.is-closed { background: #f4f4f5; color: #52525b; }

  .fulfillment-photo-kv {
    display: block !important;
  }

  .fulfillment-photo-kv .v {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .fulfillment-photo-thumb {
    max-width: 100%;
    width: 100%;
    height: auto;
    border-radius: 8px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
    background: #f8fafc;
  }

  .fulfillment-photo-link {
    display: block;
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }

  .fulfillment-photo-link:hover .fulfillment-photo-thumb {
    transform: scale(1.02);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
  }

  .fulfillment-photo-download {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 12px;
    border-radius: 6px;
    background: #f3f4f6;
    border: 1px solid #d1d5db;
    color: #334155;
    font-size: 12px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
  }

  .fulfillment-photo-download:hover {
    background: #e5e7eb;
    border-color: #9ca3af;
    color: #1f2937;
    text-decoration: none;
  }

  .fulfillment-photo-placeholder {
    display: inline-block;
    padding: 8px 10px;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    color: #64748b;
    font-size: 12px;
  }
</style>



@if(session('success'))
  <div class="alert alert-success" style="max-width: 860px; margin: 12px auto;">{{ session('success') }}</div>
@endif
<div class="row order-shell">
  <div class="col-lg-10 col-lg-offset-1">
    <div class="top-strip">
      <section class="top-card summary-card">
        <span class="status-pill {{ $statusClass }}">{{ $orderStatusText }}</span>
        <h2 class="order-title">{{ trans('frontend.order.order_details') }}</h2>
        <div class="meta-lines">{{ trans('frontend.order.order_no') }}：{{ $order->no }}</div>
        <div class="meta-lines">{{ trans('frontend.order.placed_at') }}：{{ $createdAtText }}</div>
        <div class="meta-lines">{{ is_site_mode_b() ? '履行进度' : trans('frontend.order.shipping_status') }}：{{ is_site_mode_b() ? $orderStatusText : $shipStatusTextForDisplay }}</div>
        @if(is_site_mode_a() && $isPaid && !$isClosed)
          <div class="meta-lines">履约阶段：{{ $fulfillmentStageLabel }}（{{ $fulfillmentStage }}）</div>
        @endif
        @if(is_site_mode_a() && $shippingModeLabel !== '')
          <div class="meta-lines">寄送模式：{{ $shippingModeLabel }}</div>
        @endif
        @if(is_site_mode_a() && $isPaid && $shoppingReceiptUrl !== '')
          <div class="meta-lines" style="margin-top: 8px;">
            <a class="btn btn-default btn-sm" href="{{ $shoppingReceiptUrl }}" target="_blank" download>下载购物凭据（明细书）</a>
          </div>
        @endif
      </section>

      <section class="top-card pay-card">
        @if(!$isPaid && !$isClosed && isset($expiresInSeconds))
          <div class="countdown-box">
            <span class="countdown-label">{{ trans('frontend.order.allocation_expires_in') }}</span>
            <span class="countdown-timer" id="countdown-timer" data-expires-in="{{ $expiresInSeconds }}">--:--</span>
          </div>
        @endif

        <div class="amount-label">{{ trans('frontend.cart.payable_amount') }}@if(is_site_mode_a())（日元）@endif</div>
        <div class="amount-value">{{ format_shop_price($amountJpy) }}</div>
        @if(is_site_mode_a() && !$isPaid && !$isClosed)
          <div class="meta-lines" style="margin-top: 6px;">约合 ￥{{ number_format($amountCny, 2, '.', '') }}（汇率 1 人民币 = {{ number_format($exchangeRate, 2, '.', '') }} 日元）</div>
          <div class="meta-lines">{{ trans('frontend.order.payment_redirect_hint') }}</div>
          <div class="payment-redirect-notice" role="status">
            <span class="payment-redirect-notice__icon" aria-hidden="true">!</span>
            <span class="payment-redirect-notice__text">{{ trans('frontend.order.payment_redirect_duration_notice') }}</span>
          </div>
        @endif

        @if(!$isPaid && !$isClosed)
          <div class="action-guide">{{ is_site_mode_a() ? trans('frontend.order.next_step_pay_gateway') : trans('frontend.order.next_step_pay') }}</div>
        @else
          <div class="action-guide">{{ trans('frontend.order.in_settlement') }}</div>
        @endif

        @if(!$isPaid && !$isClosed)
          <div class="pay-actions">
            <a class="pay-btn alipay js-pay-link" data-loading-text="跳转中..." href="{{ route('payment.alipay', ['order' => $order->id]) }}">支付宝支付</a>
            <a class="pay-btn wechat js-pay-link" data-loading-text="跳转中..." href="{{ route('payment.wechat', ['order' => $order->id]) }}">微信支付</a>
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

              <div class="metric price">{{ format_shop_price($item->price) }}</div>
              <div class="metric qty">x {{ (int) $item->amount }}</div>
              <div class="metric subtotal">{{ format_shop_price($item->price * $item->amount) }}</div>
            </article>
          @endforeach
        </div>
        <div class="scroll-hint">滚动可查看更多商品</div>
      </section>

      <div>
        <section class="card" style="margin-bottom: 12px;">
          <div class="card-head">结算明细</div>
          <div class="side-body settlement-breakdown">
            <div class="settlement-row">
              <span class="settlement-label">商品费</span>
              <span class="value">{{ format_shop_price($goodsAmount) }}</span>
            </div>
            <div class="settlement-row">
              <span class="settlement-label">劳务费</span>
              <span class="value">{{ format_shop_price($serviceFee) }}</span>
            </div>
            <div class="settlement-row">
              <span class="settlement-label">国际运费</span>
              <span class="value">{{ format_shop_price($emsShippingFee) }}</span>
            </div>
            <div class="settlement-row">
              <span class="settlement-label">打包费</span>
              <span class="value">{{ format_shop_price($packagingFee) }}</span>
            </div>
            <div class="settlement-row settlement-total">
              <span class="settlement-label">应付总额</span>
              <span class="value">{{ format_shop_price($amountJpy) }}</span>
            </div>
          </div>
        </section>

        <section class="card" style="margin-bottom: 12px;">
          <div class="card-head">{{ $isDomesticInTransit ? '国内段转寄信息' : (is_site_mode_b() ? '履行与转寄信息' : trans('frontend.order.shipping_section')) }}</div>
          <div class="side-body">
            <div class="kv"><span class="k">{{ is_site_mode_b() ? '国内转寄地址：' : '收货地址：' }}</span><span class="v">{{ $order->formatted_shipping_address }} {{ data_get($order->address, 'contact_name') }} {{ data_get($order->address, 'contact_phone') }} {{ data_get($order->address, 'zip') ? '邮编'.data_get($order->address, 'zip') : '' }}</span></div>
            <div class="kv"><span class="k">订单备注：</span><span class="v">{{ $order->remark ?: '-' }}</span></div>
            <div class="kv"><span class="k">运单号：</span><span class="v">{{ $trackingNoForDetail !== '' ? $trackingNoForDetail : '待上传' }}</span></div>
            @if($order->ship_data)
              <div class="kv"><span class="k">{{ is_site_mode_b() ? '国内段转寄信息：' : '物流信息：' }}</span><span class="v">{{ is_site_mode_b() ? '代购人：' : '' }}{{ data_get($order->ship_data, 'express_company', '-') }} {{ is_site_mode_b() ? '转寄单号：' : '' }}{{ data_get($order->ship_data, 'express_no', '-') }}</span></div>
            @endif

            <div class="kv fulfillment-photo-kv">
              <span class="k">实拍照片：</span>
              <span class="v">
                @if($fulfillmentPhotoUrl !== '')
                  <a href="{{ $fulfillmentPhotoUrl }}" class="fulfillment-photo-link" target="_blank">
                    <img src="{{ $fulfillmentPhotoUrl }}" alt="实拍照片" class="fulfillment-photo-thumb">
                  </a>
                  <a href="{{ $fulfillmentPhotoUrl }}" class="fulfillment-photo-download" download>查看原图</a>
                @else
                  <span class="fulfillment-photo-placeholder">客服暂未上传实拍照片</span>
                  <button type="button" class="fulfillment-photo-download" disabled>待上传</button>
                @endif
              </span>
            </div>

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
            @if(is_site_mode_a() && $isPaid)
              <div class="kv"><span class="k">履约阶段：</span><span class="v">{{ $fulfillmentStageLabel }}</span></div>
              @if($remainingAddressChanges > 0)
                <div class="kv"><span class="k">自助改址：</span><span class="v">剩余 {{ $remainingAddressChanges }} 次</span></div>
              @endif
              @if($canInstantRefund && $remainingInstantRefunds > 0)
                <div class="kv"><span class="k">自助秒退：</span><span class="v">最近 {{ (int) config('order_refund.instant.window_hours', 24) }} 小时内还可 {{ $remainingInstantRefunds }} 次</span></div>
              @endif
              @if($useRefundFeedback && $refundPolicyHint)
                <div class="warn-box" style="margin-top:8px;">取消/退款规则：{{ $refundPolicyHint }}。本阶段请通过「客户反馈」提交取消申请，由客服审核后处理。</div>
              @endif
            @endif

            @if($order->paid_at && $order->refund_status !== \App\Models\Order::REFUND_STATUS_PENDING)
              <div class="kv"><span class="k">退款状态：</span><span class="v">{{ \App\Models\Order::$refundStatusMap[$order->refund_status] }}</span></div>
              <div class="kv"><span class="k">退款理由：</span><span class="v">{{ data_get($order->extra, 'refund_reason', trans('frontend.common.none')) }}</span></div>
              @if(data_get($order->extra, 'refund_amount_cny'))
                <div class="kv"><span class="k">实退金额：</span><span class="v">￥{{ number_format((float) data_get($order->extra, 'refund_amount_cny'), 2, '.', '') }}</span></div>
              @endif
            @endif

            @if(isset($order->extra['refund_disagree_reason']))
              <div class="kv"><span class="k">拒绝退款理由：</span><span class="v">{{ $order->extra['refund_disagree_reason'] }}</span></div>
            @endif

            <div class="side-actions">
              @if($canChangeAddress)
                <a class="btn btn-default" href="{{ route('orders.change_address', $order) }}">变更信息</a>
              @endif
              @if($order->ship_status === \App\Models\Order::SHIP_STATUS_DELIVERED)
                <button type="button" class="btn btn-success js-action-receive" data-busy-text="提交中...">{{ is_site_mode_b() ? '确认签收' : '确认收货' }}</button>
              @endif

              @if($canInstantRefund)
                <button type="button" class="btn btn-danger js-action-instant-refund" data-busy-text="退款中...">取消订单（全额秒退）</button>
              @elseif($useRefundFeedback && $refundFeedbackUrl)
                <a class="btn btn-danger" href="{{ $refundFeedbackUrl }}">申请取消/退款（客户反馈）</a>
              @elseif($showLegacyRefundApply)
                <button type="button" class="btn btn-danger js-action-refund" data-busy-text="提交中...">申请退款</button>
              @endif
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</div>

@if((!$isPaid && !$isClosed) || $canChangeAddress || $order->ship_status === \App\Models\Order::SHIP_STATUS_DELIVERED || $canInstantRefund || ($useRefundFeedback && $refundFeedbackUrl) || $showLegacyRefundApply)
  <div class="mobile-sticky-actions" aria-label="移动端快捷操作">
    @if(!$isPaid && !$isClosed)
      <a class="sticky-btn alipay js-pay-link" data-loading-text="跳转中..." href="{{ route('payment.alipay', ['order' => $order->id]) }}">支付宝支付</a>
      <a class="sticky-btn wechat js-pay-link" data-loading-text="跳转中..." href="{{ route('payment.wechat', ['order' => $order->id]) }}">微信支付</a>
    @endif

    @if($canChangeAddress)
      <a class="sticky-btn" href="{{ route('orders.change_address', $order) }}">变更信息</a>
    @endif
    @if($order->ship_status === \App\Models\Order::SHIP_STATUS_DELIVERED)
      <button type="button" class="sticky-btn success js-action-receive" data-busy-text="提交中...">{{ is_site_mode_b() ? '确认签收' : '确认收货' }}</button>
    @endif

    @if($canInstantRefund)
      <button type="button" class="sticky-btn danger js-action-instant-refund" data-busy-text="退款中...">全额秒退</button>
    @elseif($useRefundFeedback && $refundFeedbackUrl)
      <a class="sticky-btn danger" href="{{ $refundFeedbackUrl }}">退款反馈</a>
    @elseif($showLegacyRefundApply)
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

    function postRefund($buttons, payload, successText) {
      setButtonsBusy($buttons, true, '提交中...');
      axios.post('{{ route('orders.apply_refund', [$order->id]) }}', payload)
        .then(function (res) {
          var msg = (res.data && res.data.message) ? res.data.message : successText;
          swal(msg, '', 'success').then(function () {
            location.reload();
          });
        })
        .catch(function (err) {
          setButtonsBusy($buttons, false);
          var msg = (err.response && err.response.data && err.response.data.message)
            ? err.response.data.message
            : '{{ trans('frontend.js.operation_failed_retry') }}';
          swal(msg, '', 'error');
        });
    }

    $('.js-action-instant-refund').click(function () {
      const $buttons = $('.js-action-instant-refund');
      if ($(this).prop('disabled')) {
        return;
      }

      swal({
        title: '确认取消订单？',
        text: '待处理阶段将立即发起全额退款（100%），款项原路退回，操作不可撤销。',
        icon: 'warning',
        buttons: ['取消', '确认秒退'],
        dangerMode: true,
      }).then(function (ret) {
        if (!ret) {
          return;
        }
        postRefund($buttons, {reason: '用户自助秒退'}, '已提交全额退款');
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
        postRefund($refundButtons, {reason: input}, '{{ trans('frontend.order.refund_requested_success') }}');
      });
    });
  });
</script>
@endsection
