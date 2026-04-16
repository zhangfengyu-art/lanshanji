@extends('layouts.app')
@section('title', '支付结果确认')

@section('content')
<style>
  .payment-return-shell {
    max-width: 860px;
    margin: 8px auto 24px;
    padding: 0 6px;
  }

  .payment-return-card {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05);
    overflow: hidden;
  }

  .payment-return-head {
    padding: 16px 18px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    font-weight: 700;
  }

  .payment-return-body {
    padding: 18px;
  }

  .payment-return-status {
    font-size: 26px;
    font-weight: 800;
    color: #111827;
    margin-bottom: 8px;
  }

  .payment-return-muted {
    color: #6b7280;
    margin-bottom: 14px;
  }

  .payment-return-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px 16px;
    margin-top: 14px;
  }

  .payment-return-item {
    padding: 12px 14px;
    border-radius: 12px;
    background: #f9fafb;
    border: 1px solid rgba(15, 23, 42, 0.06);
  }

  .payment-return-label {
    display: block;
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 4px;
  }

  .payment-return-value {
    font-size: 14px;
    color: #111827;
    word-break: break-all;
  }

  .payment-return-preview {
    width: 100%;
    max-width: 100%;
    border-radius: 12px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    transition: transform .2s ease;
  }

  .payment-return-preview:hover {
    transform: scale(1.01);
  }

  .payment-return-photo-link {
    display: inline-block;
    max-width: 360px;
    width: 100%;
    cursor: zoom-in;
  }

  .payment-return-note {
    display: block;
    margin-top: 6px;
    color: #6b7280;
    font-size: 12px;
  }

  .payment-return-loading {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: 999px;
    background: rgba(59, 130, 246, 0.08);
    color: #1d4ed8;
    font-weight: 700;
  }

  .payment-return-actions {
    margin-top: 18px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }

  @media (max-width: 768px) {
    .payment-return-grid {
      grid-template-columns: 1fr;
    }

    .payment-return-status {
      font-size: 22px;
    }

    .payment-return-body {
      padding: 14px;
    }

    .payment-return-item {
      padding: 11px 12px;
    }
  }
</style>

<div class="payment-return-shell" data-payment-return data-poll-url="{{ $pollUrl }}" data-is-paid="{{ $isPaid ? 1 : 0 }}">
  <div class="payment-return-card">
    <div class="payment-return-head">支付结果确认</div>
    <div class="payment-return-body">
      <div id="pending-state" style="display: {{ $isPaid ? 'none' : 'block' }};">
        <div class="payment-return-status">正在确认支付结果...</div>
        <div class="payment-return-muted">Webhook 可能还在路上，页面将自动轮询更新状态。</div>
        <div class="payment-return-loading">
          <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>
          <span>等待支付结果同步中</span>
        </div>
      </div>

      <div id="final-state" style="display: {{ $isPaid ? 'block' : 'none' }};">
        <div class="payment-return-status">已支付</div>
        <div class="payment-return-muted">支付结果已确认，以下为 9 项履约信息。</div>

        <div class="payment-return-grid">
          <div class="payment-return-item">
            <span class="payment-return-label">下单时间</span>
            <span class="payment-return-value" id="value-created-at">{{ $orderSnapshot['created_at'] ?: '待补充' }}</span>
          </div>
          <div class="payment-return-item">
            <span class="payment-return-label">支付时间</span>
            <span class="payment-return-value" id="value-paid-at">{{ $orderSnapshot['paid_at_display'] ?: '待补充' }}</span>
          </div>
          <div class="payment-return-item">
            <span class="payment-return-label">订单号</span>
            <span class="payment-return-value" id="value-order-no">{{ $orderSnapshot['order_no'] }}</span>
          </div>
          <div class="payment-return-item">
            <span class="payment-return-label">收件人</span>
            <span class="payment-return-value" id="value-contact-name">{{ $orderSnapshot['contact_name'] ?: '待补充' }}</span>
          </div>
          <div class="payment-return-item">
            <span class="payment-return-label">证件号</span>
            <span class="payment-return-value" id="value-id-card">{{ $orderSnapshot['id_card'] }}</span>
          </div>
          <div class="payment-return-item">
            <span class="payment-return-label">手机号</span>
            <span class="payment-return-value" id="value-contact-phone">{{ $orderSnapshot['contact_phone'] }}</span>
          </div>
          <div class="payment-return-item">
            <span class="payment-return-label">收货地址</span>
            <span class="payment-return-value" id="value-address">{{ $orderSnapshot['address'] ?: '待补充' }}</span>
          </div>
          <div class="payment-return-item" style="grid-column: 1 / -1;">
            <span class="payment-return-label">实物照片</span>
            <div id="photo-block">
              @if(!empty($orderSnapshot['has_fulfillment_photo']) && !empty($orderSnapshot['fulfillment_photo_url']))
                <a class="payment-return-photo-link" href="{{ $orderSnapshot['fulfillment_photo_url'] }}" target="_blank" rel="noopener noreferrer" title="点击查看原图">
                  <img class="payment-return-preview" src="{{ $orderSnapshot['fulfillment_photo_url'] }}" alt="发货实物图">
                </a>
                <span class="payment-return-note">图片已通过安全代理受控展示，点击可放大查看。</span>
              @else
                <span class="payment-return-value">仓库正在为您配货，稍后将由驻日买手上传实拍照片。</span>
              @endif
            </div>
          </div>
          <div class="payment-return-item">
            <span class="payment-return-label">物流单号</span>
            <span class="payment-return-value" id="value-tracking-no">{{ $orderSnapshot['tracking_no'] ?: '待填写' }}</span>
            <div id="jp-post-wrap" style="margin-top: 8px; {{ !empty($orderSnapshot['tracking_no']) && !empty($orderSnapshot['jp_post_tracking_url']) ? '' : 'display:none;' }}">
              <a class="btn btn-xs btn-primary" id="jp-post-link" href="{{ $orderSnapshot['jp_post_tracking_url'] ?: '#' }}" target="_blank" rel="noopener noreferrer">日本邮政 EMS 追踪</a>
            </div>
          </div>
        </div>

        <div class="payment-return-actions">
          <a class="btn btn-primary" href="{{ $orderUrl }}">查看订单详情</a>
          <a class="btn btn-default" href="{{ route('orders.index') }}">返回订单列表</a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scriptsAfterJs')
<script>
(function () {
  var root = document.querySelector('[data-payment-return]');
  if (!root) {
    return;
  }

  var pollUrl = root.getAttribute('data-poll-url');
  var isPaid = root.getAttribute('data-is-paid') === '1';
  var pendingState = document.getElementById('pending-state');
  var finalState = document.getElementById('final-state');
  var pollTimer = null;

  function setText(id, value, fallback) {
    var el = document.getElementById(id);
    if (!el) {
      return;
    }
    el.textContent = (value && String(value).trim() !== '') ? String(value) : fallback;
  }

  function escapeHtml(text) {
    return String(text || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderSnapshot(snapshot) {
    if (!snapshot || typeof snapshot !== 'object') {
      return;
    }

    setText('value-created-at', snapshot.created_at, '待补充');
    setText('value-paid-at', snapshot.paid_at_display || snapshot.paid_at, '待补充');
    setText('value-order-no', snapshot.order_no, '-');
    setText('value-contact-name', snapshot.contact_name, '待补充');
    setText('value-id-card', snapshot.id_card, '待补充');
    setText('value-contact-phone', snapshot.contact_phone, '待补充');
    setText('value-address', snapshot.address, '待补充');
    setText('value-tracking-no', snapshot.tracking_no, '待填写');

    var photoBlock = document.getElementById('photo-block');
    if (photoBlock) {
      if (snapshot.has_fulfillment_photo && snapshot.fulfillment_photo_url) {
        var photoUrl = escapeHtml(snapshot.fulfillment_photo_url);
        photoBlock.innerHTML = '' +
          '<a class="payment-return-photo-link" href="' + photoUrl + '" target="_blank" rel="noopener noreferrer" title="点击查看原图">' +
          '  <img class="payment-return-preview" src="' + photoUrl + '" alt="发货实物图">' +
          '</a>' +
          '<span class="payment-return-note">图片已通过安全代理受控展示，点击可放大查看。</span>';
      } else {
        photoBlock.innerHTML = '<span class="payment-return-value">仓库正在为您配货，稍后将由驻日买手上传实拍照片。</span>';
      }
    }

    var jpPostWrap = document.getElementById('jp-post-wrap');
    var jpPostLink = document.getElementById('jp-post-link');
    if (jpPostWrap && jpPostLink) {
      if (snapshot.tracking_no && snapshot.jp_post_tracking_url) {
        jpPostLink.setAttribute('href', snapshot.jp_post_tracking_url);
        jpPostWrap.style.display = 'block';
      } else {
        jpPostWrap.style.display = 'none';
      }
    }
  }

  function showFinal(snapshot) {
    renderSnapshot(snapshot);
    pendingState.style.display = 'none';
    finalState.style.display = 'block';
    root.setAttribute('data-is-paid', '1');
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  if (isPaid) {
    return;
  }

  function pollStatus() {
    axios.get(pollUrl)
      .then(function (response) {
        if (response && response.data && response.data.paid) {
          showFinal(response.data.snapshot || {});
        }
      })
      .catch(function () {
        // 保持“处理中”状态，下一轮继续轮询
      });
  }

  pollTimer = setInterval(pollStatus, 3000);
  pollStatus();
})();
</script>
@endsection