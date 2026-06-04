@extends('layouts.app')
@section('title', '发布代购委托需求')

@section('content')
<div class="row">
  <div class="col-lg-10 col-lg-offset-1">
    <div class="panel panel-default">
      <div class="panel-heading">
        <strong>发布代购委托需求</strong>
        <span class="text-muted" style="margin-left: 8px;">Post My Request</span>
      </div>
      <div class="panel-body">
        <p class="text-muted" style="margin-bottom: 14px;">
          请填写你的代购委托信息。平台会将需求展示在求购广场，等待代购人承接。
        </p>

        @if (count($errors) > 0)
          <div class="alert alert-danger">
            <ul style="margin: 0; padding-left: 18px;">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <form class="form-horizontal" role="form" method="POST" action="{{ route('procurement.store') }}" enctype="multipart/form-data">
          {{ csrf_field() }}

          <div class="form-group">
            <label class="control-label col-sm-2">想要什么？</label>
            <div class="col-sm-9">
              <input type="text" class="form-control" name="item_name" value="{{ old('item_name') }}" maxlength="120" placeholder="例如：限定款护肤套装、某品牌相机镜头">
            </div>
          </div>

          <div class="form-group">
            <label class="control-label col-sm-2">分类</label>
            <div class="col-sm-9">
              <select class="form-control" name="category_id">
                <option value="">请选择分类</option>
                @if(isset($categories) && count($categories))
                  @foreach($categories as $category)
                    <option value="{{ data_get($category, 'id') }}" {{ (string) old('category_id') === (string) data_get($category, 'id') ? 'selected' : '' }}>{{ data_get($category, 'name') }}</option>
                  @endforeach
                @endif
              </select>
              <p class="help-block">分类用于帮助代购人更快识别需求类型。</p>
            </div>
          </div>

          <div class="form-group">
            <label class="control-label col-sm-2">您的预算是多少？</label>
            <div class="col-sm-9">
              <input type="number" step="0.01" min="0" class="form-control" name="budget_amount" value="{{ old('budget_amount') }}" placeholder="例如：25000">
            </div>
          </div>

          <div class="form-group">
            <label class="control-label col-sm-2">详细描述</label>
            <div class="col-sm-9">
              <textarea class="form-control" rows="5" name="order_narrative" placeholder="例如：急求，希望能带原盒，有小票更好。">{{ old('order_narrative') }}</textarea>
              <p class="help-block">可描述时效、版本偏好、包装要求等委托细节。</p>
            </div>
          </div>

          <div class="form-group">
            <label class="control-label col-sm-2">图片上传（可选）</label>
            <div class="col-sm-9">
              <input type="file" name="image_url" class="form-control" accept="image/*">
              <p class="help-block">上传参考图有助于提升匹配准确度。</p>
            </div>
          </div>

          <div class="form-group">
            <div class="col-sm-offset-2 col-sm-9">
              <button type="submit" class="btn btn-primary">提交代购委托需求</button>
              <a href="{{ route('products.index') }}" class="btn btn-default" style="margin-left: 8px;">返回求购广场</a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
