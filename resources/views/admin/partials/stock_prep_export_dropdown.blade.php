@php
  $exportBaseUrl = $exportBaseUrl ?? '';
  $scopeOptions = $scopeOptions ?? [];
  $dropdownLabel = $dropdownLabel ?? '导出备货表';
@endphp
<div class="btn-group" style="margin-right: 8px;">
  <button type="button" class="btn btn-sm btn-success dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <i class="fa fa-download"></i> {{ $dropdownLabel }} <span class="caret"></span>
  </button>
  <ul class="dropdown-menu">
    <li class="dropdown-header">ZIP（Excel / WPS）</li>
    @foreach($scopeOptions as $scope => $label)
      <li>
        <a href="{{ $exportBaseUrl }}?scope={{ $scope }}" target="_blank" rel="noopener" title="下载 ZIP，解压后打开备货表.html">
          <i class="fa fa-file-archive-o"></i> {{ $label }}
        </a>
      </li>
    @endforeach
    <li role="separator" class="divider"></li>
    <li class="dropdown-header">PDF（直接打印）</li>
    @foreach($scopeOptions as $scope => $label)
      <li>
        <a href="{{ $exportBaseUrl }}?scope={{ $scope }}&amp;format=pdf" target="_blank" rel="noopener" title="下载 PDF，适合打印">
          <i class="fa fa-file-pdf-o"></i> {{ $label }}
        </a>
      </li>
    @endforeach
  </ul>
</div>
