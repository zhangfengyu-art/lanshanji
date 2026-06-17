<div class="order-fp-batch-import" style="display:inline-block;margin-left:8px;vertical-align:middle;">
  <form action="{{ route('admin.orders.batch.fulfillment_photos') }}" method="post" enctype="multipart/form-data" class="form-inline" style="margin:0;" data-order-fp-batch-form>
    <input type="hidden" name="_token" value="{{ csrf_token() }}">
    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple style="display:none;" data-order-fp-batch-input>
    <button type="button" class="btn btn-sm btn-success" data-order-fp-batch-trigger title="选择多张图片，文件名需含订单流水号">
      <i class="fa fa-picture-o"></i> 批量导入实拍图
    </button>
  </form>
</div>
