@php
  $hasPhoto = $order->hasFulfillmentPhoto();
  $formId = 'order-fp-form-'.$order->id;
  $inputId = 'order-fp-input-'.$order->id;
@endphp
<div class="order-fp-cell" style="min-width:88px;">
  @if($hasPhoto)
    <a href="{{ route('admin.orders.fulfillment_photo', $order) }}" target="_blank" title="查看原图">
      <img src="{{ route('admin.orders.fulfillment_photo', $order) }}" alt="实拍" style="width:48px;height:48px;object-fit:cover;border:1px solid #ddd;border-radius:4px;display:block;margin-bottom:4px;">
    </a>
  @else
    <div style="width:48px;height:48px;border:1px dashed #cbd5e1;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#94a3b8;margin-bottom:4px;">未上传</div>
  @endif
  <form id="{{ $formId }}" action="{{ route('admin.orders.fulfillment_photo.upload', $order) }}" method="post" enctype="multipart/form-data" style="margin:0;">
    <input type="hidden" name="_token" value="{{ csrf_token() }}">
    <input type="file" id="{{ $inputId }}" name="photo" accept="image/jpeg,image/png,image/webp" style="display:none;" data-order-fp-input>
    <button type="button" class="btn btn-xs btn-{{ $hasPhoto ? 'default' : 'success' }}" data-order-fp-upload data-target="#{{ $inputId }}">
      <i class="fa fa-camera"></i> {{ $hasPhoto ? '更换' : '上传' }}
    </button>
  </form>
</div>
