@extends('b_mode.layouts.app')
@section('title', '代购任务确认')

@php
  $unitPrice = (float) $defaultSku->price;
@endphp

@push('styles')
<style>
  body.site-mode-b .pa-page {
    max-width: 1240px;
    margin: 14px auto 26px;
    padding: 0 10px;
  }

  body.site-mode-b .pa-hero {
    border-radius: 16px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: linear-gradient(135deg, #1c4fa3 0%, #245fc7 48%, #3475de 100%);
    color: #eaf3ff;
    padding: 18px 18px 14px;
    margin-bottom: 12px;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
  }

  body.site-mode-b .pa-hero__tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 999px;
    padding: 4px 10px;
    background: rgba(255, 255, 255, 0.16);
    font-size: 11px;
    font-weight: 700;
  }

  body.site-mode-b .pa-hero__title {
    margin: 10px 0 0;
    font-size: 30px;
    font-weight: 800;
    line-height: 1.2;
  }

  body.site-mode-b .pa-hero__sub {
    margin: 8px 0 0;
    font-size: 13px;
    line-height: 1.7;
    color: rgba(234, 243, 255, 0.94);
  }

  body.site-mode-b .pa-grid {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: 12px;
  }

  body.site-mode-b .pa-card {
    border-radius: 16px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
    overflow: hidden;
  }

  body.site-mode-b .pa-head {
    padding: 13px 16px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    font-size: 15px;
    font-weight: 800;
    color: #0f172a;
    background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
  }

  body.site-mode-b .pa-body {
    padding: 16px;
  }

  body.site-mode-b .pa-row {
    margin-bottom: 14px;
  }

  body.site-mode-b .pa-label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    color: #64748b;
    font-weight: 700;
  }

  body.site-mode-b .pa-value {
    color: #0f172a;
    font-size: 14px;
    line-height: 1.75;
  }

  body.site-mode-b .pa-brief {
    border: 1px solid rgba(44, 123, 229, 0.14);
    background: rgba(44, 123, 229, 0.05);
    border-radius: 12px;
    padding: 12px;
  }

  body.site-mode-b .pa-title {
    margin: 0;
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
  }

  body.site-mode-b .pa-budget {
    margin: 6px 0 0;
    font-size: 29px;
    font-weight: 800;
    color: #1d4ed8;
    line-height: 1.15;
  }

  body.site-mode-b .pa-agreement {
    margin: 0;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #f8fafc;
    color: #334155;
    font-size: 13px;
    line-height: 1.85;
    max-height: 300px;
    overflow: auto;
  }

  body.site-mode-b .pa-agreement p {
    margin: 0 0 7px;
  }

  body.site-mode-b .pa-agreement p:last-child {
    margin-bottom: 0;
  }

  body.site-mode-b .pa-select,
  body.site-mode-b .pa-textarea {
    width: 100%;
    border: 1px solid rgba(15, 23, 42, 0.14);
    border-radius: 10px;
    padding: 9px 10px;
    background: #fff;
    box-shadow: none;
  }

  body.site-mode-b .pa-select:focus,
  body.site-mode-b .pa-textarea:focus {
    outline: 0;
    border-color: rgba(44, 123, 229, 0.48);
    box-shadow: 0 0 0 3px rgba(44, 123, 229, 0.12);
  }

  body.site-mode-b .pa-textarea {
    min-height: 82px;
    resize: vertical;
  }

  body.site-mode-b .pa-note {
    margin-top: 6px;
    color: #64748b;
    font-size: 12px;
    line-height: 1.65;
  }

  body.site-mode-b .pa-summary-line {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
    color: #475569;
    font-size: 14px;
  }

  body.site-mode-b .pa-summary-total {
    margin-top: 3px;
    border-top: 1px dashed rgba(15, 23, 42, 0.16);
    padding-top: 12px;
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
  }

  body.site-mode-b .pa-btn {
    width: 100%;
    border: 0;
    border-radius: 12px;
    padding: 11px 12px;
    font-size: 15px;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(180deg, #2c7be5 0%, #1d4ed8 100%);
    box-shadow: 0 8px 18px rgba(44, 123, 229, 0.24);
    transition: transform .16s ease, box-shadow .16s ease;
  }

  body.site-mode-b .pa-btn:hover,
  body.site-mode-b .pa-btn:focus {
    transform: translateY(-1px);
    box-shadow: 0 10px 20px rgba(44, 123, 229, 0.3);
  }

  body.site-mode-b .pa-btn-note {
    margin-top: 8px;
    color: #64748b;
    font-size: 12px;
  }

  @media (max-width: 920px) {
    body.site-mode-b .pa-page {
      margin-top: 10px;
    }

    body.site-mode-b .pa-grid {
      grid-template-columns: 1fr;
    }

    body.site-mode-b .pa-hero__title {
      font-size: 24px;
    }
  }
</style>
@endpush

@section('content')

<div class="pa-page">
  <section class="pa-hero">
    <span class="pa-hero__tag">代购任务确认 · Procurement Agreement</span>
    <h1 class="pa-hero__title">确认任务信息并提交支付</h1>
    <p class="pa-hero__sub">当前页面用于确认委托内容、国内转寄地址与资金核算，提交后将创建订单并进入支付流程。</p>
  </section>

  <div class="pa-grid">
    <section class="pa-card">
      <div class="pa-head">任务信息确认</div>
      <div class="pa-body">
        <div class="pa-row">
          <div class="pa-brief">
            <h2 class="pa-title">{{ $itemName }}</h2>
            <p class="pa-budget">JPY ¥{{ number_format((float) $budgetAmount, 0) }}</p>
          </div>
        </div>

        <div class="pa-row">
          <label class="pa-label">任务描述</label>
          <div class="pa-value">{{ $narrative !== '' ? $narrative : '用户提交的跨境代购需求，等待代购人履行。' }}</div>
        </div>

        <div class="pa-row">
          <label class="pa-label">代购物品摘要</label>
          <div class="pa-value">匹配商品：{{ $product->title }}（参考规格：{{ $defaultSku->title }}）</div>
        </div>

        <div class="pa-row">
          <label class="pa-label">《跨境代购委托服务条款》</label>
          <div class="pa-agreement">
            <p>1. 求购者确认委托平台撮合代购人执行境外采购任务，代购人接受委托后将按任务描述进行采购与履约。</p>
            <p>2. 代购人接受委托，并在境外采购完成后通过国内快递转寄给求购者；求购者支付的资金将暂存于平台担保账户。</p>
            <p>3. 该预付款属于委托代购劳务资金存管，不代表平台向求购者直接销售特定零售商品。</p>
            <p>4. 若任务因不可抗力无法履行，平台将依据规则进行协商处理及后续资金结算。</p>
          </div>
        </div>

        <div class="pa-row">
          <label class="pa-label">国内转寄地址（Domestic Forwarding Address）</label>
          <select id="pa-address" class="pa-select">
            @foreach($addresses as $address)
              <option value="{{ $address->id }}">{{ $address->is_default ? '[默认] ' : '' }}{{ $address->full_address }} {{ $address->contact_name }} {{ $address->contact_phone }}</option>
            @endforeach
          </select>
          <div class="pa-note">请确保填写准确，代购人在完成境外代买并入境后，将通过国内顺丰/邮政转寄至此地址。</div>
        </div>

        <div class="pa-row">
          <label class="pa-label">委托备注（选填）</label>
          <textarea id="pa-remark" class="pa-textarea" placeholder="可填写品牌偏好、颜色偏好、紧急程度等"></textarea>
        </div>
      </div>
    </section>

    <aside class="pa-card">
      <div class="pa-head">支付核算</div>
      <div class="pa-body">
        <div class="pa-summary-line"><span>商品金额</span><strong id="pa-base">{{ number_format($unitPrice, 2, '.', '') }}</strong></div>
        <div class="pa-summary-line"><span>服务费(13%)</span><strong id="pa-service">{{ number_format($unitPrice * 0.13, 2, '.', '') }}</strong></div>
        <div class="pa-summary-line"><span>打包费</span><strong>300.00</strong></div>
        <div class="pa-summary-line"><span>EMS运费</span><strong>1750.00</strong></div>
        <div class="pa-summary-line pa-summary-total"><span>应付总额</span><strong id="pa-total">{{ number_format($unitPrice + ($unitPrice * 0.13) + 300 + 1750, 2, '.', '') }}</strong></div>

        <button type="button" id="pa-confirm" class="pa-btn">提交订单并支付</button>
        <div class="pa-btn-note">点击后将创建订单，并进入支付页面。</div>
      </div>
    </aside>
  </div>
</div>
@endsection

@section('scriptsAfterJs')
<script>
$(function () {
  $('#pa-confirm').on('click', function () {
    var addressId = $('#pa-address').val();
    if (!addressId) {
      swal('请选择国内转寄地址', '', 'warning');
      return;
    }

    var $btn = $(this);
    if ($btn.prop('disabled')) return;
    $btn.prop('disabled', true).text('正在跳转支付...');

    axios.post('{{ route('orders.store') }}', {
      address_id: addressId,
      remark: $('#pa-remark').val() || '',
      items: [{ sku_id: {{ (int) $defaultSku->id }}, amount: 1 }]
    }).then(function (resp) {
      var orderId = resp.data.id;
      if (!orderId) {
        swal('创建订单失败，请稍后重试', '', 'error');
        $btn.prop('disabled', false).text('提交订单并支付');
        return;
      }
      window.location.href = '{{ url('orders') }}/' + orderId;
    }).catch(function (error) {
      $btn.prop('disabled', false).text('提交订单并支付');
      if (error.response && error.response.status === 422 && error.response.data && error.response.data.errors) {
        var msg = '';
        _.forEach(error.response.data.errors, function (arr) { _.forEach(arr, function (m) { msg += m + '\n'; }); });
        swal(msg || '提交失败', '', 'error');
        return;
      }
      if (error.response && error.response.data && error.response.data.msg) {
        swal(error.response.data.msg, '', 'error');
        return;
      }
      swal('系统错误，请稍后重试', '', 'error');
    });
  });
});
</script>
@endsection
