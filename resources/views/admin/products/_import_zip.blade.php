<form action="{{ route('admin.products.import_zip') }}" method="post" enctype="multipart/form-data" class="form-inline" style="display:inline-block; margin-left:6px;">
  <input type="hidden" name="_token" value="{{ csrf_token() }}">
  <input type="file" name="file" accept=".zip,application/zip" class="form-control input-sm" required style="display:inline-block; width:auto;">
  <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-upload"></i> ZIP 导入商品</button>
</form>
