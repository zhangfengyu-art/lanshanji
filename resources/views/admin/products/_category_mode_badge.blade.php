<div class="alert alert-info" style="margin-bottom:12px;">
  <strong>当前模式：</strong>{{ $modeLabel }}
  @if(!empty($categoryId))
    <span style="margin-left:10px;color:#666;">分类 ID：{{ $categoryId }}</span>
  @else
    <span style="margin-left:10px;color:#666;">未指定分类，显示全部商品</span>
  @endif
</div>
