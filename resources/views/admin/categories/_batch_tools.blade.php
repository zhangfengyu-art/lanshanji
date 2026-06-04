@php
  use App\Models\Product;
  use App\Models\Category;
  $parentOptions = Category::query()->orderBy('name')->get()->mapWithKeys(function ($cat) {
    $label = $cat->name;
    if ($cat->parent_id) {
      $parentName = Category::query()->where('id', $cat->parent_id)->value('name');
      $label = ($parentName ? $parentName.' / ' : '').$cat->name;
    }
    return [$cat->id => $label];
  });
@endphp
@component('admin.partials._batch_toolbar_wrap', ['entityLabel' => '分类'])
  <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
    <select class="form-control input-sm batch-cat-shipping-mode" style="width: 150px;">
      <option value="">默认寄送模式</option>
      @foreach(Product::shippingModeOptions() as $k => $v)
        <option value="{{ $k }}">{{ $v }}</option>
      @endforeach
    </select>
    <button type="button" class="btn btn-sm btn-primary" data-category-batch="shipping-mode">应用</button>

    <button type="button" class="btn btn-sm btn-info" data-category-batch="set-directory" data-is-directory="1">标为目录</button>
    <button type="button" class="btn btn-sm btn-default" data-category-batch="set-directory" data-is-directory="0">标为非目录</button>

    <select class="form-control input-sm batch-cat-parent" style="width: 160px;">
      <option value="">移动到父分类</option>
      <option value="0">→ 根分类</option>
      @foreach($parentOptions as $id => $name)
        <option value="{{ $id }}">{{ $name }}</option>
      @endforeach
    </select>
    <button type="button" class="btn btn-sm btn-warning" data-category-batch="move-parent">移动</button>
  </div>
@endcomponent
