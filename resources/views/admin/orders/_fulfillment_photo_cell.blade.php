@php
  $hasPhoto = $order->hasFulfillmentPhoto();
  $formId = 'order-fp-form-'.$order->id;
  $inputId = 'order-fp-input-'.$order->id;
  $thumbUrl = $hasPhoto ? route('admin.orders.fulfillment_photo', ['order' => $order->id, 'max_edge' => 96]) : '';
  $fullUrl = $hasPhoto ? route('admin.orders.fulfillment_photo', ['order' => $order->id]) : '';
@endphp
<div class="order-fp-cell" style="min-width:88px;" data-order-fp-cell data-order-id="{{ $order->id }}">
  @if($hasPhoto)
    <a href="{{ $fullUrl }}" target="_blank" title="查看原图" data-order-fp-preview-link>
      <img src="{{ $thumbUrl }}" alt="实拍" data-order-fp-preview style="width:48px;height:48px;object-fit:cover;border:1px solid #ddd;border-radius:4px;display:block;margin-bottom:4px;">
    </a>
  @else
    <div data-order-fp-placeholder style="width:48px;height:48px;border:1px dashed #cbd5e1;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#94a3b8;margin-bottom:4px;">未上传</div>
  @endif
  <form id="{{ $formId }}" class="order-fp-upload-form" action="{{ route('admin.orders.fulfillment_photo.upload', $order) }}" method="post" enctype="multipart/form-data" style="margin:0;">
    <input type="hidden" name="_token" value="{{ csrf_token() }}">
    <input type="file" id="{{ $inputId }}" name="photo" accept="image/jpeg,image/png,image/webp,image/*" style="display:none;" data-order-fp-input>
    <button type="button" class="btn btn-xs btn-{{ $hasPhoto ? 'default' : 'success' }}" data-order-fp-upload data-target="#{{ $inputId }}">
      <i class="fa fa-camera"></i> <span data-order-fp-upload-label>{{ $hasPhoto ? '更换' : '上传' }}</span>
    </button>
  </form>
</div>
