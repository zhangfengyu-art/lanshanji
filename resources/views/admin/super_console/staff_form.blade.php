@php
  $isEdit = $user && $user->exists;
  $action = $isEdit ? admin_url('super-console/'.$user->id) : admin_url('super-console');
  $isSuper = $isEdit && \App\Services\Admin\SuperAdminGuard::isSuperAdmin($user);
  $selectedModules = old('module_slugs', $isEdit ? app(\App\Services\Admin\AdminPermissionCatalogService::class)->moduleSlugsFromUser($user) : ['dashboard']);
  $grantSuper = old('grant_super_admin', $isSuper ? '1' : '');
  $groupedModules = $groupedModules ?? [];
  $presets = $presets ?? [];
@endphp
<div class="box box-primary">
  <div class="box-header with-border">
    <h3 class="box-title">{{ $isEdit ? '编辑管理员' : '新建管理员' }}</h3>
  </div>
  <form class="form-horizontal" method="POST" action="{{ $action }}" id="staff-access-form">
    {{ csrf_field() }}
    @if($isEdit)
      {{ method_field('PUT') }}
    @endif
    <div class="box-body">
      <div class="callout callout-info">
        <h4>怎么分配权限？</h4>
        <p>① 先选一个<strong>岗位套餐</strong>（可一键勾选常用组合）；② 在下面<strong>单独勾选</strong>需要开通的模块，想开哪项勾哪项；③ 若需全站最高权限，勾选「终极管理员」。</p>
      </div>

      <div class="form-group">
        <label class="col-sm-2 control-label">登录用户名</label>
        <div class="col-sm-8">
          <input type="text" class="form-control" name="username" value="{{ old('username', $user->username ?? '') }}" required>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-2 control-label">显示名称</label>
        <div class="col-sm-8">
          <input type="text" class="form-control" name="name" value="{{ old('name', $user->name ?? '') }}" required>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-2 control-label">密码</label>
        <div class="col-sm-8">
          <input type="password" class="form-control" name="password" {{ $isEdit ? '' : 'required' }} minlength="6">
          @if($isEdit)
            <p class="help-block">留空表示不修改密码</p>
          @endif
        </div>
      </div>

      <div class="form-group">
        <label class="col-sm-2 control-label">终极管理员</label>
        <div class="col-sm-8">
          <label class="checkbox-inline">
            <input type="checkbox" name="grant_super_admin" value="1" id="grant-super-admin" {{ $grantSuper ? 'checked' : '' }}>
            全站最高权限（无需再勾模块）
          </label>
          <div id="super-confirm-wrap" style="margin-top:8px;{{ $grantSuper ? '' : 'display:none;' }}">
            <label class="checkbox-inline text-danger">
              <input type="checkbox" name="confirm_super_role" value="1" {{ old('confirm_super_role') ? 'checked' : '' }}>
              我确认授予终极管理员
            </label>
          </div>
        </div>
      </div>

      <div id="module-access-panel">
        <div class="form-group">
          <label class="col-sm-2 control-label">岗位套餐</label>
          <div class="col-sm-8">
            <div class="btn-group btn-group-justified" style="margin-bottom:10px;" id="preset-buttons">
              @foreach($presets as $presetKey => $preset)
                <div class="btn-group">
                  <button type="button" class="btn btn-default preset-btn" data-preset="{{ $presetKey }}"
                    title="{{ $preset['desc'] }}">{{ $preset['name'] }}</button>
                </div>
              @endforeach
            </div>
            <p class="help-block">点击套餐会勾选对应模块，您仍可单独增删勾选。</p>
          </div>
        </div>

        <div class="form-group">
          <label class="col-sm-2 control-label">可访问模块</label>
          <div class="col-sm-8">
            @foreach($groupedModules as $groupTitle => $items)
              <div class="panel panel-default" style="margin-bottom:12px;">
                <div class="panel-heading" style="padding:8px 12px;">
                  <strong>{{ $groupTitle }}</strong>
                  <a href="#" class="pull-right select-group-all" data-group="{{ $groupTitle }}">全选本组</a>
                </div>
                <div class="panel-body" style="padding:10px 15px;">
                  @foreach($items as $item)
                    <label class="checkbox-inline" style="margin-right:18px; margin-left:0; min-width:140px;">
                      <input type="checkbox" name="module_slugs[]" value="{{ $item['slug'] }}"
                        class="module-checkbox" data-group="{{ $groupTitle }}"
                        {{ in_array($item['slug'], $selectedModules, true) ? 'checked' : '' }}>
                      {{ $item['label'] }}
                      <small class="text-muted">（{{ $item['hint'] }}）</small>
                    </label>
                  @endforeach
                </div>
              </div>
            @endforeach
            <p class="help-block" id="module-summary">已选：<span id="module-summary-text">—</span></p>
          </div>
        </div>
      </div>
    </div>
    <div class="box-footer">
      <button type="submit" class="btn btn-primary">保存</button>
      <a href="{{ admin_url('super-console') }}" class="btn btn-default">返回</a>
    </div>
  </form>
</div>

<script>
(function () {
  var presets = @json($presets);

  function updateSummary() {
    var labels = [];
    $('.module-checkbox:checked').each(function () {
      labels.push($(this).closest('label').text().split('（')[0].trim());
    });
    $('#module-summary-text').text(labels.length ? labels.join('、') : '请至少勾选一个模块');
  }

  function setModules(slugs) {
    $('.module-checkbox').prop('checked', false);
    (slugs || []).forEach(function (slug) {
      $('.module-checkbox[value="' + slug + '"]').prop('checked', true);
    });
    updateSummary();
  }

  function toggleSuperPanel() {
    var on = $('#grant-super-admin').is(':checked');
    $('#super-confirm-wrap').toggle(on);
    $('#module-access-panel').toggle(!on);
  }

  $('.preset-btn').on('click', function (e) {
    e.preventDefault();
    var key = $(this).data('preset');
    if (presets[key] && presets[key].modules) {
      setModules(presets[key].modules);
    }
    $('.preset-btn').removeClass('btn-primary').addClass('btn-default');
    $(this).removeClass('btn-default').addClass('btn-primary');
  });

  $('.select-group-all').on('click', function (e) {
    e.preventDefault();
    var g = $(this).data('group');
    $('.module-checkbox[data-group="' + g + '"]').prop('checked', true);
    updateSummary();
  });

  $('.module-checkbox').on('change', updateSummary);
  $('#grant-super-admin').on('change', toggleSuperPanel);

  toggleSuperPanel();
  updateSummary();
})();
</script>
