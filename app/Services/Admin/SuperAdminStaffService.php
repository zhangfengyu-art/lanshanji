<?php

namespace App\Services\Admin;

use App\Exceptions\InvalidRequestException;
use App\Models\Admin\Administrator;
use Encore\Admin\Auth\Database\Role;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\DB;

class SuperAdminStaffService
{
    /** @var AdminPermissionCatalogService */
    protected $catalog;

    public function __construct(AdminPermissionCatalogService $catalog)
    {
        $this->catalog = $catalog;
    }

    public function listQuery()
    {
        return Administrator::query()->with(['roles', 'permissions'])->orderBy('id');
    }

    public function prepareFormData()
    {
        $this->catalog->ensureModuleRoles();

        return [
            'groupedModules' => $this->catalog->groupedModulesForUi(),
            'presets' => $this->catalog->presetList(),
        ];
    }

    public function create(array $data)
    {
        $this->validateUsername($data['username'] ?? '');
        $password = (string) ($data['password'] ?? '');
        if (strlen($password) < 6) {
            throw new InvalidRequestException('密码至少 6 位');
        }

        return DB::transaction(function () use ($data, $password) {
            $user = Administrator::query()->create([
                'username' => trim($data['username']),
                'name' => trim($data['name'] ?? $data['username']),
                'password' => bcrypt($password),
            ]);

            $this->syncAccess($user, $data);

            return $user->fresh(['roles', 'permissions']);
        });
    }

    public function update(Administrator $user, array $data)
    {
        if (!empty($data['username']) && $data['username'] !== $user->username) {
            $this->validateUsername($data['username'], $user->id);
            $user->username = trim($data['username']);
        }

        if (!empty($data['name'])) {
            $user->name = trim($data['name']);
        }

        if (!empty($data['password'])) {
            if (strlen((string) $data['password']) < 6) {
                throw new InvalidRequestException('密码至少 6 位');
            }
            $user->password = bcrypt($data['password']);
        }

        return DB::transaction(function () use ($user, $data) {
            $user->save();
            $this->syncAccess($user, $data);

            return $user->fresh(['roles', 'permissions']);
        });
    }

    public function delete(Administrator $user)
    {
        if (SuperAdminGuard::isSuperAdmin($user)) {
            $count = Role::query()
                ->where('slug', SuperAdminGuard::ROLE_SLUG)
                ->first()
                ->administrators()
                ->count();

            if ($count <= 1) {
                throw new InvalidRequestException('不能删除唯一终极管理员账号');
            }
        }

        $current = Admin::user();
        if ($current && (int) $current->id === (int) $user->id) {
            throw new InvalidRequestException('不能删除当前登录账号');
        }

        $user->roles()->detach();
        $user->permissions()->detach();
        $user->delete();

        return ['message' => '已删除管理员 '.$user->username];
    }

    protected function syncAccess(Administrator $user, array $data)
    {
        $grantSuper = !empty($data['grant_super_admin']);
        if ($grantSuper) {
            if (empty($data['confirm_super_role'])) {
                throw new InvalidRequestException('授予终极管理员请勾选确认项');
            }
            $superRoleId = Role::query()->where('slug', SuperAdminGuard::ROLE_SLUG)->value('id');
            if (!$superRoleId) {
                throw new InvalidRequestException('未找到终极管理员角色，请先运行 setup_super_admin.php');
            }
            $user->roles()->sync([(int) $superRoleId]);
            $user->permissions()->sync([]);

            return;
        }

        $moduleSlugs = array_values(array_unique(array_filter((array) ($data['module_slugs'] ?? []))));
        if (count($moduleSlugs) === 0) {
            throw new InvalidRequestException('请至少勾选一个可访问模块');
        }

        if (!in_array('dashboard', $moduleSlugs, true)) {
            $moduleSlugs[] = 'dashboard';
        }

        $roleIds = $this->catalog->roleIdsFromModuleSlugs($moduleSlugs);
        if (count($roleIds) === 0) {
            throw new InvalidRequestException('模块角色未就绪，请刷新页面后重试');
        }

        $user->roles()->sync($roleIds);
        $user->permissions()->sync([]);
    }

    protected function validateUsername($username, $exceptId = null)
    {
        $username = trim((string) $username);
        if ($username === '') {
            throw new InvalidRequestException('请填写登录用户名');
        }

        $q = Administrator::query()->where('username', $username);
        if ($exceptId) {
            $q->where('id', '!=', (int) $exceptId);
        }
        if ($q->exists()) {
            throw new InvalidRequestException('用户名已存在');
        }
    }

    public function accessSummaryForUser(Administrator $user)
    {
        if (SuperAdminGuard::isSuperAdmin($user)) {
            return '终极管理员（全站）';
        }

        $labels = $this->catalog->labelsFromModuleSlugs($this->catalog->moduleSlugsFromUser($user));

        return $labels ? implode('、', $labels) : '未分配模块';
    }
}
