@php
  $exportBaseUrl = $exportBaseUrl ?? '';
  $scopeOptions = $scopeOptions ?? [];
  $dropdownLabel = $dropdownLabel ?? '导出 CSV';
@endphp
<div class="btn-group" style="margin-right: 8px;">
  <button type="button" class="btn btn-sm btn-success dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <i class="fa fa-download"></i> {{ $dropdownLabel }} <span class="caret"></span>
  </button>
  <ul class="dropdown-menu">
    @foreach($scopeOptions as $scope => $label)
      <li>
        <a href="{{ $exportBaseUrl }}?scope={{ $scope }}" target="_blank">
          <i class="fa fa-file-excel-o"></i> {{ $label }}
        </a>
      </li>
    @endforeach
  </ul>
</div>
