@extends('layouts.app')
@section('title', '代购任务确认')

@php
  $serviceFeeRate = (float) config('site.service_fee_rate', 0.15);
  $serviceFeePercent = (int) round($serviceFeeRate * 100);
  $unitPrice = (float) $defaultSku->price;
@endphp

<style>
  .pa-wrap { max-width: 1020px; margin: 20px auto 30px; }
  .pa-grid { display: grid; grid-template-columns: 1.1fr .9fr; gap: 14px; }
  .pa-card { background: #fff; border: 1px solid #e6e7eb; border-radius: 12px; box-shadow: 0 4px 14px rgba(20, 24, 31, 0.05); }
  .pa-head { padding: 12px 14px; border-bottom: 1px solid #eef0f3; font-size: 16px; font-weight: 700; color: #20242c; }
  .pa-body { padding: 14px; }
  .pa-row { margin-bottom: 12px; }
  .pa-label { display: block; margin-bottom: 6px; font-size: 12px; color: #6b7280; }
  .pa-value { color: #111827; font-size: 14px; }
  .pa-title { margin: 0; font-size: 22px; color: #111827; }
  .pa-budget { margin: 6px 0 0; font-size: 26px; color: #111827; font-weight: 700; }
  .pa-agreement { font-size: 13px; color: #374151; line-height: 1.8; background: #fafafa; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; max-height: 300px; overflow: auto; }
  .pa-select, .pa-textarea { width: 100%; border: 1px solid #d7dbe2; border-radius: 8px; padding: 8px 10px; }
  .pa-textarea { min-height: 74px; resize: vertical; }
  .pa-summary-line { display: flex; justify-content: space-between; margin-bottom: 10px; color: #4b5563; }
  .pa-summary-total { font-size: 20px; font-weight: 700; color: #111827; border-top: 1px dashed #d4d7de; padding-top: 10px; }
  .pa-btn { width: 100%; border: 0; border-radius: 10px; padding: 11px 12px; font-size: 15px; font-weight: 700; color: #fff; background: linear-gradient(180deg, #1f242e 0%, #11151d 100%); }
  .pa-note { margin-top: 8px; color: #6b7280; font-size: 12px; }
  @media (max-width: 900px) { .pa-grid { grid-template-columns: 1fr; } }
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
        <div class="pa-summary-line"><span>服务费({{ $serviceFeePercent }}%)</span><strong id="pa-service">{{ number_format($unitPrice * $serviceFeeRate, 2, '.', '') }}</strong></div>
        <div class="pa-summary-line"><span>打包费</span><strong>300.00</strong></div>
        <div class="pa-summary-line"><span>EMS运费</span><strong>1750.00</strong></div>
        <div class="pa-summary-line pa-summary-total"><span>应付总额</span><strong id="pa-total">{{ number_format($unitPrice + ($unitPrice * $serviceFeeRate) + 300 + 1750, 2, '.', '') }}</strong></div>

        <button type="button" id="pa-confirm" class="pa-btn">确认并预付资金</button>
        <div class="pa-note">点击后将创建订单并跳转至订单支付页。</div>
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
    $btn.prop('disabled', true).text('创建任务订单中...');

    axios.post('{{ route('orders.store') }}', {
      address_id: addressId,
      remark: $('#pa-remark').val() || '',
      items: [{ sku_id: {{ (int) $defaultSku->id }}, amount: 1 }]
    }).then(function (resp) {
      var orderId = resp.data.id;
      if (!orderId) {
        swal('创建订单失败，请重试', '', 'error');
        $btn.prop('disabled', false).text('确认并预付资金');
        return;
      }
      window.location.href = '{{ url('orders') }}/' + orderId;
    }).catch(function (error) {
      $btn.prop('disabled', false).text('确认并预付资金');
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
