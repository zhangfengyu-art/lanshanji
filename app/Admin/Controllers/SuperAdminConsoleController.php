<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Admin\SuperAdminGuard;
use App\Services\Admin\SuperAdminStaffService;
use App\Models\Admin\Administrator;
use Encore\Admin\Auth\Database\Permission;
use Encore\Admin\Auth\Database\Role;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Tab;
use Illuminate\Http\Request;

class SuperAdminConsoleController extends Controller
{
    /** @var SuperAdminStaffService */
    protected $staff;

    public function __construct(SuperAdminStaffService $staff)
    {
        $this->staff = $staff;
    }

    public function index()
    {
        return Admin::content(function (Content $content) {
            $content->header('终极管控台');
            $content->description('创建/删除管理员；用岗位套餐 + 模块勾选分配权限；仅终极管理员可进入');

            $tab = new Tab();
            $tab->add('管理员账号', $this->staffGrid()->render(), true);
            $tab->add('角色管理', $this->rolesGrid()->render());
            $tab->add('权限一览', $this->permissionsPanel());

            $content->body($tab);
        });
    }

    public function create()
    {
        return Admin::content(function (Content $content) {
            $content->header('终极管控台');
            $content->description('新建管理员');
            $content->body(view('admin.super_console.staff_form', array_merge(
                ['user' => new Administrator()],
                $this->staff->prepareFormData()
            )));
        });
    }

    public function store(Request $request)
    {
        try {
            $this->staff->create($request->all());
            admin_toastr('管理员已创建', 'success');
        } catch (\App\Exceptions\InvalidRequestException $e) {
            admin_toastr($e->getMessage(), 'error');

            return back()->withInput();
        }

        return redirect(admin_url('super-console'));
    }

    public function edit($id)
    {
        $user = Administrator::query()->with(['roles', 'permissions'])->findOrFail($id);

        return Admin::content(function (Content $content) use ($user) {
            $content->header('终极管控台');
            $content->description('编辑管理员 #'.$user->id);
            $content->body(view('admin.super_console.staff_form', array_merge(
                ['user' => $user],
                $this->staff->prepareFormData()
            )));
        });
    }

    public function update($id, Request $request)
    {
        $user = Administrator::query()->findOrFail($id);

        try {
            $this->staff->update($user, $request->all());
            admin_toastr('已保存', 'success');
        } catch (\App\Exceptions\InvalidRequestException $e) {
            admin_toastr($e->getMessage(), 'error');

            return back()->withInput();
        }

        return redirect(admin_url('super-console'));
    }

    public function destroy($id)
    {
        $user = Administrator::query()->findOrFail($id);

        try {
            $result = $this->staff->delete($user);
            admin_toastr($result['message'], 'success');
        } catch (\App\Exceptions\InvalidRequestException $e) {
            admin_toastr($e->getMessage(), 'error');
        }

        return redirect(admin_url('super-console'));
    }

    public function createRole()
    {
        return Admin::content(function (Content $content) {
            $content->header('终极管控台');
            $content->description('新建角色');
            $content->body(view('admin.super_console.role_form', [
                'role' => new Role(),
                'permissions' => $this->assignablePermissionOptions(),
            ]));
        });
    }

    public function storeRole(Request $request)
    {
        $name = trim((string) $request->input('name'));
        $slug = trim((string) $request->input('slug'));
        if ($name === '' || $slug === '') {
            admin_toastr('请填写角色名称与标识', 'error');

            return back()->withInput();
        }
        if ($slug === SuperAdminGuard::ROLE_SLUG) {
            admin_toastr('该标识保留给终极管理员', 'error');

            return back()->withInput();
        }
        if (Role::query()->where('slug', $slug)->exists()) {
            admin_toastr('角色标识已存在', 'error');

            return back()->withInput();
        }

        $role = Role::query()->create(['name' => $name, 'slug' => $slug]);
        $role->permissions()->sync((array) $request->input('permission_ids', []));

        admin_toastr('角色已创建', 'success');

        return redirect(admin_url('super-console'));
    }

    public function editRole($id)
    {
        $role = Role::query()->with('permissions')->findOrFail($id);

        return Admin::content(function (Content $content) use ($role) {
            $content->header('终极管控台');
            $content->description('编辑角色');
            $content->body(view('admin.super_console.role_form', [
                'role' => $role,
                'permissions' => $this->assignablePermissionOptions(),
            ]));
        });
    }

    public function updateRole($id, Request $request)
    {
        $role = Role::query()->findOrFail($id);
        if ($role->slug === SuperAdminGuard::ROLE_SLUG) {
            admin_toastr('终极管理员角色固定拥有全部权限', 'info');

            return redirect(admin_url('super-console'));
        }

        $role->update(['name' => trim((string) $request->input('name', $role->name))]);
        $role->permissions()->sync((array) $request->input('permission_ids', []));

        admin_toastr('角色已更新', 'success');

        return redirect(admin_url('super-console'));
    }

    public function destroyRole($id)
    {
        $role = Role::query()->findOrFail($id);
        if ($role->slug === SuperAdminGuard::ROLE_SLUG) {
            admin_toastr('不能删除终极管理员角色', 'error');

            return redirect(admin_url('super-console'));
        }
        if (strpos($role->slug, 'mod-') === 0) {
            admin_toastr('系统自动模块角色不可删除', 'error');

            return redirect(admin_url('super-console'));
        }
        if ($role->administrators()->count() > 0) {
            admin_toastr('仍有管理员绑定此角色，请先解除', 'error');

            return redirect(admin_url('super-console'));
        }

        $role->permissions()->detach();
        $role->delete();
        admin_toastr('角色已删除', 'success');

        return redirect(admin_url('super-console'));
    }

    protected function staffRoleOptions()
    {
        $options = SuperAdminGuard::assignableRoles()->toArray();
        $super = Role::query()->where('slug', SuperAdminGuard::ROLE_SLUG)->first();
        if ($super) {
            $options[$super->id] = $super->name.'（全站最高权限）';
        }

        return $options;
    }

    protected function assignablePermissionOptions()
    {
        return Permission::query()
            ->where('slug', '!=', '*')
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    protected function staffGrid()
    {
        return new Grid(new Administrator(), function (Grid $grid) {
            $grid->model()->with(['roles']);
            $grid->id('ID')->sortable();
            $grid->username('用户名');
            $grid->name('显示名');
            $grid->column('access', '可访问模块')->display(function () {
                return e(app(SuperAdminStaffService::class)->accessSummaryForUser($this));
            });
            $grid->column('is_super', '类型')->display(function () {
                return SuperAdminGuard::isSuperAdmin($this)
                    ? '<span class="label label-danger">终极管理员</span>'
                    : '<span class="label label-default">普通管理员</span>';
            });
            $grid->created_at('创建时间');

            $grid->disableExport();
            $grid->disableCreation();
            $grid->disableFilter();
            $grid->setResource(admin_url('super-console'));

            $grid->actions(function ($actions) {
                $actions->disableEdit();
                $actions->disableView();
                $actions->prepend(
                    '<a href="'.admin_url('super-console/'.$actions->getKey().'/edit').'" class="btn btn-xs btn-primary" style="margin-right:4px;">'
                    .'<i class="fa fa-edit"></i> 编辑</a>'
                );
                if (SuperAdminGuard::isSuperAdmin($actions->row)) {
                    $superRole = Role::query()->where('slug', SuperAdminGuard::ROLE_SLUG)->first();
                    if ($superRole && $superRole->administrators()->count() <= 1) {
                        $actions->disableDelete();
                    }
                }
            });

            $grid->tools(function ($tools) {
                $tools->append(
                    '<a href="'.admin_url('super-console/create').'" class="btn btn-sm btn-success">'
                    .'<i class="fa fa-plus"></i> 新建管理员</a>'
                );
                $tools->batch(function ($batch) {
                    $batch->disableDelete();
                });
            });
        });
    }

    protected function rolesGrid()
    {
        return new Grid(new Role(), function (Grid $grid) {
            $grid->model()->withCount(['permissions', 'administrators'])->orderBy('id');
            $grid->id('ID');
            $grid->name('角色名');
            $grid->slug('标识');
            $grid->column('permissions_count', '权限数');
            $grid->column('administrators_count', '管理员数');

            $grid->disableExport();
            $grid->disableCreation();
            $grid->setResource(admin_url('super-console/roles'));

            $grid->actions(function ($actions) {
                $actions->disableEdit();
                $actions->disableView();
                $actions->prepend(
                    '<a href="'.admin_url('super-console/roles/'.$actions->getKey().'/edit').'" class="btn btn-xs btn-primary" style="margin-right:4px;">编辑</a>'
                );
                if (data_get($actions->row, 'slug') === SuperAdminGuard::ROLE_SLUG) {
                    $actions->disableDelete();
                }
            });

            $grid->tools(function ($tools) {
                $tools->append(
                    '<a href="'.admin_url('super-console/roles/create').'" class="btn btn-sm btn-primary">'
                    .'<i class="fa fa-plus"></i> 新建角色</a>'
                );
                $tools->batch(function ($batch) {
                    $batch->disableDelete();
                });
            });
        });
    }

    protected function permissionsPanel()
    {
        $rows = Permission::query()->orderBy('name')->get();
        $html = '<table class="table table-bordered table-striped"><thead><tr>'
            .'<th>ID</th><th>名称</th><th>标识</th><th>路径规则</th></tr></thead><tbody>';
        foreach ($rows as $p) {
            $html .= '<tr><td>'.$p->id.'</td><td>'.e($p->name).'</td><td><code>'.e($p->slug).'</code></td>'
                .'<td><pre style="margin:0;max-height:80px;overflow:auto;">'.e($p->http_path).'</pre></td></tr>';
        }
        $html .= '</tbody></table><p class="text-muted">'
            .'分配管理员时请在「可访问模块」中勾选；每项对应一个系统模块角色（mod-xxx），无需理解上表路径规则。'
            .'终极管理员拥有 <code>*</code> 全权限。</p>';

        return new Box('全站权限规则', $html);
    }
}
