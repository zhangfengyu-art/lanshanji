@extends('layouts.app')

@section('title', '提交代购资质认证 - 岚山跨境')

@section('content')
<section style="max-width: 760px; margin: 0 auto; padding: 3rem 1rem;">

  <div style="margin-bottom: 2rem;">
    <a href="{{ route('products.index') }}" style="color: #1e3a8a; text-decoration: none; font-size: 14px;">
      <i class="fa fa-chevron-left" style="margin-right: 6px;"></i>返回求购列表
    </a>
  </div>

  <div style="background: #fff; border-radius: 12px; padding: 3rem; box-shadow: 0 2px 12px rgba(30,58,138,0.08);">

    <div style="margin-bottom: 2.5rem; padding-bottom: 2rem; border-bottom: 1px solid #e5e7eb;">
      <h1 style="font-size: 24px; font-weight: 700; color: #202422; margin: 0 0 0.5rem 0;">
        <i class="fa fa-id-card" style="color: #1e3a8a; margin-right: 10px;"></i>代购资质认证
      </h1>
      <p style="color: #6b7280; font-size: 14px; margin: 0; line-height: 1.6;">
        在岚山跨境平台进行代购接单前，需通过资质认证审核。请上传真实有效的证件材料，审核通过后方可接单。
      </p>
    </div>

    @if ($errors->any())
      <div style="margin-bottom: 1.5rem; background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 1rem 1.25rem; border-radius: 8px; font-size: 14px;">
        <ul style="margin: 0; padding-left: 18px;">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST"
          action="{{ route('procurement.qualification.store') }}"
          enctype="multipart/form-data">
      {{ csrf_field() }}

      <!-- 身份证正面 -->
      <div style="margin-bottom: 2rem;">
        <label style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">
          身份证正面 <span style="color: #ef4444;">*</span>
        </label>
        <p style="font-size: 12px; color: #9ca3af; margin: 0 0 0.75rem 0;">请上传清晰的身份证正面（姓名面）照片，格式：JPG / PNG / GIF，大小不超过 5MB</p>
        <input type="file" name="id_card_front" accept="image/*" required
               style="display: block; width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #374151; background: #f9fafb; cursor: pointer;"
               id="input-front"
               onchange="previewImage(this, 'preview-front')">
        <div id="preview-front" style="margin-top: 0.75rem; display: none;">
          <img style="max-width: 300px; max-height: 200px; border-radius: 8px; border: 1px solid #e5e7eb;" src="" alt="正面预览">
        </div>
      </div>

      <!-- 身份证背面 -->
      <div style="margin-bottom: 2rem;">
        <label style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">
          身份证背面 <span style="color: #ef4444;">*</span>
        </label>
        <p style="font-size: 12px; color: #9ca3af; margin: 0 0 0.75rem 0;">请上传清晰的身份证背面（国徽面）照片</p>
        <input type="file" name="id_card_back" accept="image/*" required
               style="display: block; width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #374151; background: #f9fafb; cursor: pointer;"
               id="input-back"
               onchange="previewImage(this, 'preview-back')">
        <div id="preview-back" style="margin-top: 0.75rem; display: none;">
          <img style="max-width: 300px; max-height: 200px; border-radius: 8px; border: 1px solid #e5e7eb;" src="" alt="背面预览">
        </div>
      </div>

      <!-- 机票凭证 -->
      <div style="margin-bottom: 2rem;">
        <label style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">
          机票凭证照片 <span style="color: #ef4444;">*</span>
        </label>
        <p style="font-size: 12px; color: #9ca3af; margin: 0 0 0.75rem 0;">请上传近期（6个月内）前往代购目的地的机票订单截图或电子客票</p>
        <input type="file" name="flight_ticket" accept="image/*" required
               style="display: block; width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #374151; background: #f9fafb; cursor: pointer;"
               id="input-ticket"
               onchange="previewImage(this, 'preview-ticket')">
        <div id="preview-ticket" style="margin-top: 0.75rem; display: none;">
          <img style="max-width: 300px; max-height: 200px; border-radius: 8px; border: 1px solid #e5e7eb;" src="" alt="机票预览">
        </div>
      </div>

      <!-- 备注 -->
      <div style="margin-bottom: 2.5rem;">
        <label style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">
          申请备注（选填）
        </label>
        <textarea name="applicant_note" rows="3" maxlength="500"
                  placeholder="如有特殊情况或补充说明，可在此填写…"
                  style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #374151; resize: vertical; box-sizing: border-box;">{{ old('applicant_note') }}</textarea>
      </div>

      <!-- 提示 -->
      <div style="margin-bottom: 2rem; padding: 1rem 1.25rem; background: #f0f4ff; border-left: 4px solid #1e3a8a; border-radius: 8px; font-size: 13px; color: #4b5563; line-height: 1.7;">
        <strong style="color: #1e3a8a;">须知：</strong>
        所有材料将被严格保密，仅用于代购资质审核。审核周期一般为 1~3 个工作日，
        请耐心等待。审核通过后您将可以接单代购。
      </div>

      <button type="submit" style="
        display: inline-flex;
        align-items: center;
        height: 46px;
        padding: 0 2.5rem;
        background-color: #1e3a8a;
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: background-color 0.2s;
      " onmouseover="this.style.backgroundColor='#152d6f'" onmouseout="this.style.backgroundColor='#1e3a8a'">
        <i class="fa fa-paper-plane" style="margin-right: 8px;"></i>提交资质申请
      </button>
    </form>

  </div>
</section>

<script>
function previewImage(input, previewId) {
  var previewBox = document.getElementById(previewId);
  var img = previewBox.querySelector('img');
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      img.src = e.target.result;
      previewBox.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
@endsection
