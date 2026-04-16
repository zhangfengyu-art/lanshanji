@extends('b_mode.layouts.app')
@section('title', 'FAQ & Guidelines')

@push('styles')
<style>
  .site-mode-b .b-faq-page {
    padding: 14px 0 88px;
  }

  .site-mode-b .b-faq-hero,
  .site-mode-b .b-faq-card {
    border-radius: 12px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
    padding: 14px;
    margin-bottom: 12px;
  }

  .site-mode-b .b-faq-title {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    color: var(--b-mode-primary);
  }

  .site-mode-b .b-faq-sub {
    margin: 8px 0 0;
    color: var(--b-mode-muted);
    font-size: 13px;
    line-height: 1.6;
  }

  .site-mode-b .b-faq-card h3 {
    margin: 0 0 8px;
    font-size: 16px;
    font-weight: 700;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .site-mode-b .b-faq-card ul {
    margin: 0;
    padding-left: 18px;
    color: #334155;
    line-height: 1.7;
    font-size: 14px;
  }

  .site-mode-b .b-faq-actions {
    display: flex;
    gap: 10px;
    margin-top: 14px;
  }

  .site-mode-b .b-faq-actions a {
    border-radius: 10px;
    font-weight: 700;
  }
</style>
@endpush

@section('content')
<section class="b-faq-page">
  <div class="b-faq-hero">
    <h1 class="b-faq-title">常见代购问题沟通页 (FAQ & Guidelines)</h1>
    <p class="b-faq-sub">此页面用于说明承接流程、服务边界与争议处理路径，帮助承接者和求购者在任务开始前达成一致。</p>
  </div>

  <div class="b-faq-card">
    <h3><span data-lucide="shield-check"></span> 资金与结算说明</h3>
    <ul>
      <li>平台对任务报酬执行托管，避免线下私下结算风险。</li>
      <li>任务状态到达“确认转寄”后，系统自动触发结算流程。</li>
      <li>异常订单可提交证据进入平台复核。</li>
    </ul>
  </div>

  <div class="b-faq-card">
    <h3><span data-lucide="message-circle"></span> 沟通建议</h3>
    <ul>
      <li>先确认可承接范围、预计时效与服务费细节。</li>
      <li>关键信息尽量使用平台留痕沟通，便于后续核查。</li>
      <li>如需补充材料，请在任务开始前一次性说明。</li>
    </ul>
  </div>

  <div class="b-faq-card">
    <h3><span data-lucide="scale"></span> 争议处理</h3>
    <ul>
      <li>若出现履约争议，可通过工单提交订单号、截图和说明。</li>
      <li>平台会按时间线与证据进行仲裁并给出处理结果。</li>
      <li>请避免脱离平台的私下转账或口头承诺。</li>
    </ul>

    <div class="b-faq-actions">
      <a class="btn btn-secondary" href="{{ route('products.index') }}">返回代购互助广场</a>
      <a class="btn btn-primary" href="{{ route('support.feedbacks.create') }}">提交沟通工单</a>
    </div>
  </div>
</section>
@endsection
