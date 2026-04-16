@extends(is_site_mode_b() ? 'b_mode.layouts.app' : 'layouts.app')
@section('title', '发布代购委托需求')

@section('content')
<style>
  body.site-mode-b .b-create-wrap {
    max-width: 980px;
    margin: 8px auto 20px;
    padding: 0 6px;
  }

  body.site-mode-b .b-create-intro {
    margin-bottom: 10px;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid rgba(44, 123, 229, 0.18);
    background: linear-gradient(135deg, #ffffff 0%, #f7fafc 100%);
  }

  body.site-mode-b .b-create-intro__title {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #1e3a8a;
  }

  body.site-mode-b .b-create-intro__sub {
    margin: 6px 0 0;
    color: #64748b;
    font-size: 12px;
  }

  body.site-mode-b .b-create-wrap .panel {
    border-radius: 18px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
  }

  body.site-mode-b .b-create-wrap .panel-heading {
    padding: 18px 20px;
    font-size: 17px;
  }

  body.site-mode-b .b-create-wrap .panel-body {
    padding: 20px 20px 18px;
  }

  body.site-mode-b .b-create-wrap .form-group {
    margin-bottom: 18px;
  }

  body.site-mode-b .b-create-wrap .form-control {
    min-height: 42px;
    border-radius: 12px;
    border-color: rgba(15, 23, 42, 0.14);
    box-shadow: none;
    transition: border-color .15s ease, box-shadow .15s ease;
  }

  body.site-mode-b .b-create-wrap .form-control:focus {
    border-color: rgba(44, 123, 229, 0.46);
    box-shadow: 0 0 0 3px rgba(44, 123, 229, 0.12);
  }

  body.site-mode-b .b-create-wrap textarea.form-control {
    min-height: 130px;
    padding-top: 10px;
  }

  body.site-mode-b .b-create-wrap .help-block {
    margin-top: 7px;
    color: #64748b;
  }

  body.site-mode-b .b-create-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  body.site-mode-b .b-create-actions .btn {
    border-radius: 12px;
    min-height: 40px;
    font-weight: 700;
    padding: 8px 14px;
  }

  body.site-mode-b .b-create-mobile-actions {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1033;
    padding: 8px 10px calc(8px + env(safe-area-inset-bottom));
    display: none;
    gap: 8px;
    background: rgba(244, 247, 251, 0.94);
    border-top: 1px solid rgba(15, 23, 42, 0.08);
    backdrop-filter: blur(8px);
  }

  body.site-mode-b .b-create-mobile-actions .btn {
    flex: 1;
    border-radius: 12px;
    min-height: 42px;
    font-weight: 700;
  }

  @media (max-width: 768px) {
    body.site-mode-b .b-create-wrap {
      margin-bottom: 16px;
      padding-bottom: 110px;
    }

    body.site-mode-b .b-create-wrap .panel-heading {
      padding: 14px;
      font-size: 16px;
    }

    body.site-mode-b .b-create-wrap .panel-body {
      padding: 12px;
    }

    body.site-mode-b .b-create-wrap .control-label {
      text-align: left;
      margin-bottom: 8px;
    }

    body.site-mode-b .b-create-mobile-actions {
      display: flex;
    }
  }
</style>

<div class="b-create-wrap">
  <div class="b-create-intro">
    <p class="b-create-intro__title">委托发布助手</p>
    <p class="b-create-intro__sub">填写越清晰，越容易被优质代购人快速承接。</p>
  </div>

  <div class="row">
    <div class="col-lg-10 col-lg-offset-1">
      <div class="panel panel-default">
        <div class="panel-heading">
          <strong>发布代购委托需求</strong>
          <span class="text-muted" style="margin-left: 8px;">Post My Request</span>
        </div>
        <div class="panel-body">
          <p class="text-muted" style="margin-bottom: 14px;">
            请填写你的代购委托信息。平台会将需求展示在代购互助广场，等待代购人承接。
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

          <form id="b-create-form" class="form-horizontal" role="form" method="POST" action="{{ route('procurement.store') }}" enctype="multipart/form-data">
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
                <div class="b-create-actions">
                  <button id="b-create-submit" type="submit" class="btn btn-primary">提交代购委托需求</button>
                  <a href="{{ route('products.index') }}" class="btn btn-default">返回代购互助广场</a>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="b-create-mobile-actions">
  <a href="{{ route('products.index') }}" class="btn btn-default">返回代购互助广场</a>
  <button type="button" id="b-create-mobile-submit" class="btn btn-primary">提交需求</button>
</div>
@endsection

@section('scriptsAfterJs')
<script>
$(function () {
  $('#b-create-mobile-submit').on('click', function () {
    $('#b-create-submit').trigger('click');
  });
});
</script>
@endsection
