@php
  $isEdit = $role && $role->exists;
  $action = $isEdit ? admin_url('super-console/roles/'.$role->id) : admin_url('super-console/roles');
  $permIds = old('permission_ids', $isEdit ? $role->permissions->pluck('id')->toArray() : []);
  $isSuperRole = $isEdit && $role->slug === \App\Services\Admin\SuperAdminGuard::ROLE_SLUG;
@endphp
<div class="box box-primary">
  <div class="box-header with-border">
    <h3 class="box-title">{{ $isEdit ? '编辑角色' : '新建角色' }}</h3>
  </div>
  <form class="form-horizontal" method="POST" action="{{ $action }}">
    {{ csrf_field() }}
    @if($isEdit)
      {{ method_field('PUT') }}
    @endif
    <div class="box-body">
      <div class="form-group">
        <label class="col-sm-2 control-label">角色名称</label>
        <div class="col-sm-8">
          <input type="text" class="form-control" name="name" value="{{ old('name', $role->name ?? '') }}" required {{ $isSuperRole ? 'readonly' : '' }}>
        </div>
      </div>
      @if(!$isEdit)
      <div class="form-group">
        <label class="col-sm-2 control-label">标识 slug</label>
        <div class="col-sm-8">
          <input type="text" class="form-control" name="slug" value="{{ old('slug') }}" required pattern="[a-z0-9\-_]+" placeholder="如 operator">
        </div>
      </div>
      @else
      <div class="form-group">
        <label class="col-sm-2 control-label">标识</label>
        <div class="col-sm-8">
          <p class="form-control-static"><code>{{ $role->slug }}</code></p>
        </div>
      </div>
      @endif
      @if($isSuperRole)
        <div class="alert alert-danger">终极管理员角色固定拥有全部权限。</div>
      @else
      <div class="form-group">
        <label class="col-sm-2 control-label">模块权限</label>
        <div class="col-sm-8">
          <select class="form-control" name="permission_ids[]" multiple style="min-height:220px;">
            @foreach($permissions as $id => $label)
              <option value="{{ $id }}" {{ in_array($id, $permIds) ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
      </div>
      @endif
    </div>
    <div class="box-footer">
      <button type="submit" class="btn btn-primary">保存</button>
      <a href="{{ admin_url('super-console') }}" class="btn btn-default">返回</a>
    </div>
  </form>
</div>
