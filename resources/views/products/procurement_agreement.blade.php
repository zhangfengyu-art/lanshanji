@extends(is_site_mode_b() ? 'b_mode.layouts.app' : 'layouts.app')
@section('title', '代购任务确认')

@section('content')
@php
  $unitPrice = (float) $defaultSku->price;
@endphp

<style>
  .pa-wrap {
    max-width: 1060px;
    margin: 20px auto 30px;
    padding: 0 6px;
  }
  .pa-grid { display: grid; grid-template-columns: 1.1fr .9fr; gap: 14px; }
  .pa-card {
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 16px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
    overflow: hidden;
  }
  .pa-head {
    padding: 13px 16px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    font-size: 16px;
    font-weight: 700;
    color: #1f2937;
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
  }
  .pa-body { padding: 16px; }
  .pa-row { margin-bottom: 14px; }
  .pa-label { display: block; margin-bottom: 6px; font-size: 12px; color: #64748b; }
  .pa-value { color: #111827; font-size: 14px; line-height: 1.8; }
  .pa-title { margin: 0; font-size: 24px; font-weight: 800; color: #0f172a; }
  .pa-budget { margin: 6px 0 0; font-size: 30px; color: #0f172a; font-weight: 800; line-height: 1.15; }
  .pa-agreement {
    font-size: 13px;
    color: #334155;
    line-height: 1.8;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px;
    max-height: 320px;
    overflow: auto;
  }
  .pa-select, .pa-textarea {
    width: 100%;
    border: 1px solid #d7dbe2;
    border-radius: 10px;
    padding: 9px 10px;
    background: #fff;
  }
  .pa-textarea { min-height: 78px; resize: vertical; }
  .pa-summary-line {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    color: #475569;
    font-size: 14px;
  }
  .pa-summary-total {
    margin-top: 2px;
    font-size: 21px;
    font-weight: 800;
    color: #0f172a;
    border-top: 1px dashed #d4d7de;
    padding-top: 11px;
  }
  .pa-btn {
    width: 100%;
    border: 0;
    border-radius: 12px;
    padding: 11px 12px;
    font-size: 15px;
    font-weight: 700;
    color: #fff;
    background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
    transition: transform .16s ease, box-shadow .16s ease;
  }
  .pa-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 16px rgba(15, 23, 42, 0.2); }
  .pa-note { margin-top: 8px; color: #64748b; font-size: 12px; }
  @media (max-width: 900px) {
    .pa-grid { grid-template-columns: 1fr; }
    .pa-wrap { margin: 10px auto 16px; }
  }
</style>

<div class="pa-wrap">
  <div class="pa-grid">
    <section class="pa-card">
      <div class="pa-head">代购任务确认（Procurement Agreement）</div>
      <div class="pa-body">
        <div class="pa-row">
          <h1 class="pa-title">{{ $itemName }}</h1>
          <p class="pa-budget">预算总计：JPY ¥{{ number_format((float) $budgetAmount, 0) }}</p>
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
      <div class="pa-head">预付资金核算</div>
      <div class="pa-body">
        <div class="pa-summary-line"><span>商品金额</span><strong id="pa-base">{{ number_format($unitPrice, 2, '.', '') }}</strong></div>
        <div class="pa-summary-line"><span>服务费(13%)</span><strong id="pa-service">{{ number_format($unitPrice * 0.13, 2, '.', '') }}</strong></div>
        <div class="pa-summary-line"><span>打包费</span><strong>300.00</strong></div>
        <div class="pa-summary-line"><span>EMS运费</span><strong>1750.00</strong></div>
        <div class="pa-summary-line pa-summary-total"><span>应付总额</span><strong id="pa-total">{{ number_format($unitPrice + ($unitPrice * 0.13) + 300 + 1750, 2, '.', '') }}</strong></div>

        <button type="button" id="pa-confirm" class="pa-btn">提交订单并支付</button>
        <div class="pa-note">点击后将创建订单，并进入支付页面。</div>
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
