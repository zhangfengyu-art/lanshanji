@extends('layouts.app')

@section('title', $procurementOrder->item_name . ' - 岚山跨境求购大厅')

@section('content')
<section class="b-workstation" style="max-width: 1120px; margin: 0 auto; padding: 3rem 1rem;">

  @if(session('success'))
    <div style="margin-bottom: 1rem; background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; padding: 0.75rem 1rem; border-radius: 8px;">
      {{ session('success') }}
    </div>
  @endif

  @if(session('error'))
    <div style="margin-bottom: 1rem; background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 0.75rem 1rem; border-radius: 8px;">
      {{ session('error') }}
    </div>
  @endif
  
  <div style="margin-bottom: 2rem;">
    <a href="{{ route('products.index') }}" style="color: #1e3a8a; text-decoration: none; font-size: 14px;">
      <i class="fa fa-chevron-left" style="margin-right: 6px;"></i>返回求购列表
    </a>
  </div>

  <div style="background: #ffffff; border-radius: 12px; padding: 3rem; box-shadow: 0 2px 12px rgba(30, 58, 138, 0.08);">
    
    <!-- 标题和基础信息 -->
    <div style="margin-bottom: 2.5rem; padding-bottom: 2.5rem; border-bottom: 1px solid #e5e7eb;">
      <div style="display: flex; align-items: flex-start; gap: 2rem;">
        <div style="flex: 1;">
          <h1 style="font-size: 28px; font-weight: 700; margin: 0 0 1rem 0; color: #202422;">
            {{ $procurementOrder->item_name }}
          </h1>
          <div style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 1.5rem;">
            @php
              $statusMeta = [
                0 => ['text' => '待接单', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
                1 => ['text' => '已接单', 'color' => '#10b981', 'bg' => '#d1fae5'],
                2 => ['text' => '采购中', 'color' => '#3b82f6', 'bg' => '#dbeafe'],
                3 => ['text' => '已发货', 'color' => '#8b5cf6', 'bg' => '#ede9fe'],
              ];
              $status = $statusMeta[$procurementOrder->proxy_status] ?? $statusMeta[0];
            @endphp
            <span style="display: inline-block; padding: 0.5rem 1rem; background-color: {{ $status['bg'] }}; color: {{ $status['color'] }}; border-radius: 6px; font-size: 13px; font-weight: 600;">
              {{ $status['text'] }}
            </span>
            <span style="color: #6b7280; font-size: 14px;">发布于 {{ $procurementOrder->created_at->diffForHumans() }}</span>
          </div>
        </div>
        <div style="text-align: right;">
          <div style="font-size: 14px; color: #6b7280; margin-bottom: 0.5rem;">预算金额</div>
          <div style="font-size: 36px; font-weight: 700; color: #1e3a8a;">
            @php $displayCurrencyLabel = data_get($procurementOrder->extra, 'payment_currency', 'JPY') === 'CNY' ? 'RMB' : 'JPY'; @endphp
            {{ $displayCurrencyLabel }} ¥{{ number_format($procurementOrder->budget_amount, 2, '.', '') }}
          </div>
        </div>
      </div>
    </div>

    <!-- 求购人信息 -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 2rem; margin-bottom: 2.5rem; padding-bottom: 2.5rem; border-bottom: 1px solid #e5e7eb;">
      <div>
        <div style="color: #6b7280; font-size: 12px; margin-bottom: 0.5rem;">采购人</div>
        <div style="font-size: 15px; font-weight: 600; color: #202422;">{{ $procurementOrder->buyer_nickname ?? '岚山采购员' }}</div>
      </div>
      <div>
        <div style="color: #6b7280; font-size: 12px; margin-bottom: 0.5rem;">采购等级</div>
        <div style="font-size: 15px; font-weight: 600; color: #202422;">⭐⭐⭐⭐⭐</div>
      </div>
      <div>
        <div style="color: #6b7280; font-size: 12px; margin-bottom: 0.5rem;">已完成订单</div>
        <div style="font-size: 15px; font-weight: 600; color: #202422;">42笔</div>
      </div>
      <div>
        <div style="color: #6b7280; font-size: 12px; margin-bottom: 0.5rem;">接单覆盖</div>
        <div style="font-size: 15px; font-weight: 600; color: #202422;">日本、韩国、欧美</div>
      </div>
    </div>

    <!-- 求购详情 -->
    <div style="margin-bottom: 2.5rem;">
      <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 1rem 0; color: #202422;">求购详情</h3>
      <p style="color: #4b5563; line-height: 1.8; font-size: 15px; margin: 0;">
        {{ $procurementOrder->order_narrative }}
      </p>
    </div>

    <!-- 求购需求 -->
    <div style="margin-bottom: 2.5rem;">
      <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 1rem 0; color: #202422;">关键需求</h3>
      <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        @php
          $keywords = ['原装正品', '日本直邮', '清关便利', '长期合作', '有发票'];
          foreach ($keywords as $kw) {
            echo '<span style="display: inline-block; padding: 0.5rem 1rem; background-color: #f0ebea; color: #8b7f6f; border-radius: 6px; font-size: 13px; font-weight: 500;">' . $kw . '</span>';
          }
        @endphp
      </div>
    </div>

    <!-- 接单按钮 -->
    <div style="display: flex; gap: 1rem; padding-top: 2rem; border-top: 1px solid #e5e7eb;">
      @php
        $isPending = (int) $procurementOrder->proxy_status === 0;
        $authed    = auth()->check();
        $verified  = $authed && auth()->user()->email_verified;

        // 资质状态（只在已登录+邮箱验证后才查）
        $qualification = null;
        if ($verified) {
            $qualification = \App\Models\ProxyQualification::query()
                ->where('user_id', auth()->id())
                ->latest()
                ->first();
        }
        $qualApproved  = $qualification && $qualification->isApproved();
        $qualPending   = $qualification && $qualification->isPending();
        $qualRejected  = $qualification && $qualification->isRejected();

        $canAccept = $verified && $qualApproved && $isPending;
      @endphp

      @if($canAccept)
        <form method="POST" action="{{ route('procurement.accept', $procurementOrder->id) }}" style="display: inline;">
          {{ csrf_field() }}
          <button type="submit" style="
            display: inline-flex;
            align-items: center;
            height: 46px;
            padding: 0 2rem;
            background-color: #1e3a8a;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
          " onmouseover="this.style.backgroundColor='#152d6f'; this.style.boxShadow='0 10px 20px rgba(30, 58, 138, 0.2)'" onmouseout="this.style.backgroundColor='#1e3a8a'; this.style.boxShadow='none'">
            <i class="fa fa-check" style="margin-right: 8px;"></i>确认接单
          </button>
        </form>
      @elseif(!auth()->check())
        <a href="{{ route('login') }}" style="
          display: inline-flex;
          align-items: center;
          height: 46px;
          padding: 0 2rem;
          background-color: #1e3a8a;
          color: #ffffff;
          border-radius: 8px;
          font-size: 15px;
          font-weight: 700;
          text-decoration: none;
        ">
          <i class="fa fa-sign-in" style="margin-right: 8px;"></i>登录后接单
        </a>
      @elseif($authed && !auth()->user()->email_verified)
        <a href="{{ route('email_verify_notice') }}" style="
          display: inline-flex;
          align-items: center;
          height: 46px;
          padding: 0 2rem;
          background-color: #c9b89a;
          color: #ffffff;
          border-radius: 8px;
          font-size: 15px;
          font-weight: 700;
          text-decoration: none;
        ">
          <i class="fa fa-envelope" style="margin-right: 8px;"></i>先验证邮箱再接单
        </a>

      @elseif($verified && !$qualification)
        {{-- 已登录已验证，但从未提交过资质申请 --}}
        <a href="{{ route('procurement.qualification.create') }}" style="
          display: inline-flex;
          align-items: center;
          height: 46px;
          padding: 0 2rem;
          background-color: #1e3a8a;
          color: #ffffff;
          border-radius: 8px;
          font-size: 15px;
          font-weight: 700;
          text-decoration: none;
        ">
          <i class="fa fa-id-card" style="margin-right: 8px;"></i>提交代购资质认证
        </a>

      @elseif($qualPending)
        {{-- 资质审核中 --}}
        <button type="button" disabled style="
          display: inline-flex;
          align-items: center;
          height: 46px;
          padding: 0 2rem;
          background-color: #fef3c7;
          color: #92400e;
          border: 1px solid #fcd34d;
          border-radius: 8px;
          font-size: 15px;
          font-weight: 700;
          cursor: not-allowed;
        ">
          <i class="fa fa-clock-o" style="margin-right: 8px;"></i>资质审核中，请等待
        </button>

      @elseif($qualRejected)
        {{-- 资质被拒，引导重新申请 --}}
        <a href="{{ route('procurement.qualification.create') }}" style="
          display: inline-flex;
          align-items: center;
          height: 46px;
          padding: 0 2rem;
          background-color: #ef4444;
          color: #ffffff;
          border-radius: 8px;
          font-size: 15px;
          font-weight: 700;
          text-decoration: none;
        ">
          <i class="fa fa-refresh" style="margin-right: 8px;"></i>资质审核未通过，重新申请
        </a>

      @elseif($qualApproved && !$isPending)
        {{-- 资质通过，但订单已被人接了 --}}
        <button type="button" disabled style="
          display: inline-flex;
          align-items: center;
          height: 46px;
          padding: 0 2rem;
          background-color: #d1d5db;
          color: #9ca3af;
          border: none;
          border-radius: 8px;
          font-size: 15px;
          font-weight: 700;
          cursor: not-allowed;
        ">
          <i class="fa fa-check" style="margin-right: 8px;"></i>已有人接单
        </button>

      @else
        {{-- 兜底：订单非待接状态 --}}
        <button type="button" disabled style="
          display: inline-flex;
          align-items: center;
          height: 46px;
          padding: 0 2rem;
          background-color: #d1d5db;
          color: #9ca3af;
          border: none;
          border-radius: 8px;
          font-size: 15px;
          font-weight: 700;
          cursor: not-allowed;
        ">
          <i class="fa fa-check" style="margin-right: 8px;"></i>已有人接单
        </button>
      @endif
      
      <a href="{{ route('products.index') }}" style="
        display: inline-flex;
        align-items: center;
        height: 46px;
        padding: 0 2rem;
        background-color: #f3f4f6;
        color: #202422;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s ease;
      " onmouseover="this.style.backgroundColor='#e5e7eb'" onmouseout="this.style.backgroundColor='#f3f4f6'">
        <i class="fa fa-times" style="margin-right: 8px;"></i>返回列表
      </a>
    </div>

  </div>

  <!-- 注意事项 -->
  <div style="margin-top: 2rem; padding: 1.5rem; background-color: #f0f4ff; border-left: 4px solid #1e3a8a; border-radius: 8px;">
    <h4 style="margin: 0 0 0.75rem 0; color: #1e3a8a; font-size: 14px; font-weight: 700;">接单须知</h4>
    <ul style="margin: 0; padding-left: 20px; color: #4b5563; font-size: 13px; line-height: 1.8;">
      <li>您是代购人：只需确认接单，货款由求购方预付托管，您无需在本页付款</li>
      <li>接单后需要在7个工作日内完成采购</li>
      <li>产品需符合求购人的所有要求</li>
      <li>有任何疑问请及时与求购人沟通</li>
      <li>违规接单将扣除信誉分，情节严重时禁用账号</li>
    </ul>
  </div>

</section>

<style>
  @media (max-width: 980px) {
    .b-workstation {
      padding: 2rem 1rem;
    }
  }
  
  @media (max-width: 768px) {
    h1 {
      font-size: 22px !important;
    }
    [style*="grid-template-columns: repeat(4"]  {
      grid-template-columns: repeat(2, 1fr) !important;
    }
  }
</style>

@endsection
