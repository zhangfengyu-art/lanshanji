@php
  $exportBaseUrl = $exportBaseUrl ?? '';
  $scopeOptions = $scopeOptions ?? [];
  $dropdownLabel = $dropdownLabel ?? '导出订单';
@endphp
<div class="btn-group" style="margin-right: 8px;">
  <button type="button" class="btn btn-sm btn-success dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <i class="fa fa-download"></i> {{ $dropdownLabel }} <span class="caret"></span>
  </button>
  <ul class="dropdown-menu">
    @foreach($scopeOptions as $scope => $label)
      <li>
        <a href="{{ $exportBaseUrl }}?scope={{ $scope }}" title="下载 ZIP，解压后用 Excel/WPS 打开「订单表.html」可查看商品图">
          <i class="fa fa-file-archive-o"></i> {{ $label }}
        </a>
      </li>
    @endforeach
  </ul>
</div>
