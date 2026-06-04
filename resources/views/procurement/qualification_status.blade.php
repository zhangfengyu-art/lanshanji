@extends('layouts.app')

@section('title', '代购资质认证状态 - 岚山跨境')

@section('content')
<section style="max-width: 760px; margin: 0 auto; padding: 3rem 1rem;">

  <div style="margin-bottom: 2rem;">
    <a href="{{ route('products.index') }}" style="color: #1e3a8a; text-decoration: none; font-size: 14px;">
      <i class="fa fa-chevron-left" style="margin-right: 6px;"></i>返回求购列表
    </a>
  </div>

  @if(session('success'))
    <div style="margin-bottom: 1.5rem; background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; padding: 0.75rem 1rem; border-radius: 8px; font-size: 14px;">
      {{ session('success') }}
    </div>
  @endif

  <div style="background: #fff; border-radius: 12px; padding: 3rem; box-shadow: 0 2px 12px rgba(30,58,138,0.08);">

    <div style="margin-bottom: 2.5rem; padding-bottom: 2rem; border-bottom: 1px solid #e5e7eb;">
      <h1 style="font-size: 24px; font-weight: 700; color: #202422; margin: 0 0 0.5rem 0;">
        <i class="fa fa-id-card" style="color: #1e3a8a; margin-right: 10px;"></i>代购资质认证
      </h1>
    </div>

    @if(!$qualification)
      {{-- 尚未提交申请 --}}
      <div style="text-align: center; padding: 3rem 0;">
        <div style="font-size: 60px; margin-bottom: 1.5rem; color: #d1d5db;">
          <i class="fa fa-id-card-o"></i>
        </div>
        <p style="color: #6b7280; font-size: 15px; margin-bottom: 2rem;">您尚未提交代购资质申请，提交后方可接单代购。</p>
        <a href="{{ route('procurement.qualification.create') }}" style="
          display: inline-flex;
          align-items: center;
          height: 46px;
          padding: 0 2.5rem;
          background-color: #1e3a8a;
          color: #fff;
          border-radius: 8px;
          font-size: 15px;
          font-weight: 700;
          text-decoration: none;
        ">
          <i class="fa fa-paper-plane" style="margin-right: 8px;"></i>立即提交申请
        </a>
      </div>

    @elseif($qualification->isPending())
      {{-- 待审核 --}}
      <div style="text-align: center; padding: 2rem 0;">
        <div style="display: inline-flex; align-items: center; justify-content: center; width: 80px; height: 80px; border-radius: 50%; background: #fef3c7; margin-bottom: 1.5rem;">
          <i class="fa fa-clock-o" style="font-size: 32px; color: #f59e0b;"></i>
        </div>
        <h2 style="font-size: 20px; font-weight: 700; color: #92400e; margin: 0 0 0.75rem 0;">审核中</h2>
        <p style="color: #6b7280; font-size: 14px; line-height: 1.7; margin: 0 0 2rem 0;">
          您的代购资质申请已提交，正在等待管理员审核。<br>
          审核周期一般为 1~3 个工作日，请耐心等待。
        </p>
        <div style="text-align: left; background: #f9fafb; border-radius: 8px; padding: 1.5rem; font-size: 13px; color: #6b7280;">
          <div style="display: grid; grid-template-columns: 120px 1fr; gap: 0.75rem;">
            <span style="font-weight: 600; color: #374151;">提交时间</span>
            <span>{{ $qualification->created_at->format('Y-m-d H:i') }}</span>
            <span style="font-weight: 600; color: #374151;">申请状态</span>
            <span><span style="background: #fef3c7; color: #92400e; padding: 2px 10px; border-radius: 4px; font-weight: 600;">待审核</span></span>
            @if($qualification->applicant_note)
              <span style="font-weight: 600; color: #374151;">申请备注</span>
              <span>{{ $qualification->applicant_note }}</span>
            @endif
          </div>
        </div>
      </div>

    @elseif($qualification->isApproved())
      {{-- 已通过 --}}
      <div style="text-align: center; padding: 2rem 0;">
        <div style="display: inline-flex; align-items: center; justify-content: center; width: 80px; height: 80px; border-radius: 50%; background: #d1fae5; margin-bottom: 1.5rem;">
          <i class="fa fa-check" style="font-size: 32px; color: #10b981;"></i>
        </div>
        <h2 style="font-size: 20px; font-weight: 700; color: #065f46; margin: 0 0 0.75rem 0;">资质审核已通过</h2>
        <p style="color: #6b7280; font-size: 14px; line-height: 1.7; margin: 0 0 2rem 0;">
          恭喜！您的代购资质已通过审核，现在可以接单代购了。
        </p>
        <a href="{{ route('products.index') }}" style="
          display: inline-flex;
          align-items: center;
          height: 46px;
          padding: 0 2.5rem;
          background-color: #1e3a8a;
          color: #fff;
          border-radius: 8px;
          font-size: 15px;
          font-weight: 700;
          text-decoration: none;
        ">
          <i class="fa fa-list" style="margin-right: 8px;"></i>去接单
        </a>
        <div style="margin-top: 2rem; text-align: left; background: #f9fafb; border-radius: 8px; padding: 1.5rem; font-size: 13px; color: #6b7280;">
          <div style="display: grid; grid-template-columns: 120px 1fr; gap: 0.75rem;">
            <span style="font-weight: 600; color: #374151;">通过时间</span>
            <span>{{ optional($qualification->reviewed_at)->format('Y-m-d H:i') ?: '-' }}</span>
            <span style="font-weight: 600; color: #374151;">申请状态</span>
            <span><span style="background: #d1fae5; color: #065f46; padding: 2px 10px; border-radius: 4px; font-weight: 600;">已通过</span></span>
          </div>
        </div>
      </div>

    @elseif($qualification->isRejected())
      {{-- 已拒绝 --}}
      <div style="text-align: center; padding: 2rem 0;">
        <div style="display: inline-flex; align-items: center; justify-content: center; width: 80px; height: 80px; border-radius: 50%; background: #fee2e2; margin-bottom: 1.5rem;">
          <i class="fa fa-times" style="font-size: 32px; color: #ef4444;"></i>
        </div>
        <h2 style="font-size: 20px; font-weight: 700; color: #991b1b; margin: 0 0 0.75rem 0;">资质审核未通过</h2>
        @if($qualification->reject_reason)
          <div style="display: inline-block; text-align: left; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 1rem 1.5rem; margin-bottom: 1.5rem; font-size: 14px; color: #b91c1c; max-width: 480px; line-height: 1.6;">
            <strong>拒绝原因：</strong>{{ $qualification->reject_reason }}
          </div>
        @else
          <p style="color: #6b7280; font-size: 14px; margin-bottom: 2rem;">材料不符合要求，请修改后重新提交。</p>
        @endif

        <div style="margin-bottom: 2rem;"></div>
        <a href="{{ route('procurement.qualification.create') }}" style="
          display: inline-flex;
          align-items: center;
          height: 46px;
          padding: 0 2.5rem;
          background-color: #1e3a8a;
          color: #fff;
          border-radius: 8px;
          font-size: 15px;
          font-weight: 700;
          text-decoration: none;
        ">
          <i class="fa fa-refresh" style="margin-right: 8px;"></i>重新提交申请
        </a>
      </div>

    @endif

  </div>
</section>
@endsection
