<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SetupSuperAdminConsole extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('admin_roles')) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $superRoleId = $this->ensureSuperAdminRole($now);
        $this->ensureSuperConsolePermission($now);
        $menuId = $this->ensureSuperConsoleMenu($now);

        $permIds = DB::table('admin_permissions')->pluck('id')->all();
        if ($superRoleId && $permIds) {
            foreach ($permIds as $pid) {
                $this->attachRolePermission($superRoleId, $pid, $now);
            }
        }

        if ($superRoleId && $menuId) {
            $this->attachRoleMenu($superRoleId, $menuId, $now);
            $allMenuIds = DB::table('admin_menu')->pluck('id')->all();
            foreach ($allMenuIds as $mid) {
                $this->attachRoleMenu($superRoleId, $mid, $now);
            }
        }

        $adminUser = DB::table('admin_users')->where('username', 'admin')->first();
        if ($adminUser && $superRoleId) {
            $this->attachUserRole($adminUser->id, $superRoleId, $now);
            $starId = DB::table('admin_permissions')->where('slug', '*')->value('id');
            if ($starId) {
                $this->attachUserPermission($adminUser->id, $starId, $now);
            }
        }

        $this->restrictSystemMenusToSuperAdmin($superRoleId, $now);
        $this->stripLegacyAdministratorElevatedRights($now);
    }

    protected function stripLegacyAdministratorElevatedRights($now)
    {
        $adminRoleId = DB::table('admin_roles')->where('slug', 'administrator')->value('id');
        if (!$adminRoleId) {
            return;
        }

        $rp = config('admin.database.role_permissions_table', 'admin_role_permissions');
        $stripSlugs = ['*', 'auth.management', 'super-admin.console'];
        $permIds = DB::table('admin_permissions')->whereIn('slug', $stripSlugs)->pluck('id')->all();
        if ($permIds) {
            DB::table($rp)
                ->where('role_id', $adminRoleId)
                ->whereIn('permission_id', $permIds)
                ->delete();
        }

        DB::table('admin_roles')->where('id', $adminRoleId)->update([
            'name' => '普通管理员',
            'updated_at' => $now,
        ]);
    }

    public function down()
    {
        if (!Schema::hasTable('admin_menu')) {
            return;
        }

        DB::table('admin_menu')->where('uri', 'super-console')->delete();
        DB::table('admin_permissions')->where('slug', 'super-admin.console')->delete();
    }

    protected function ensureSuperAdminRole($now)
    {
        $row = DB::table('admin_roles')->where('slug', 'super-admin')->first();
        if ($row) {
            DB::table('admin_roles')->where('id', $row->id)->update([
                'name' => '终极管理员',
                'updated_at' => $now,
            ]);

            return (int) $row->id;
        }

        return (int) DB::table('admin_roles')->insertGetId([
            'name' => '终极管理员',
            'slug' => 'super-admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function ensureSuperConsolePermission($now)
    {
        $row = DB::table('admin_permissions')->where('slug', 'super-admin.console')->first();
        if ($row) {
            return (int) $row->id;
        }

        return (int) DB::table('admin_permissions')->insertGetId([
            'name' => '终极管控台',
            'slug' => 'super-admin.console',
            'http_method' => '',
            'http_path' => "/super-console*\r\n/super-console/*",
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function ensureSuperConsoleMenu($now)
    {
        $row = DB::table('admin_menu')->where('uri', 'super-console')->first();
        if ($row) {
            DB::table('admin_menu')->where('id', $row->id)->update([
                'title' => '终极管控台',
                'icon' => 'fa-shield',
                'order' => 0,
                'updated_at' => $now,
            ]);

            return (int) $row->id;
        }

        $maxOrder = (int) DB::table('admin_menu')->where('parent_id', 0)->max('order');

        return (int) DB::table('admin_menu')->insertGetId([
            'parent_id' => 0,
            'order' => max(0, $maxOrder - 1),
            'title' => '终极管控台',
            'icon' => 'fa-shield',
            'uri' => 'super-console',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function restrictSystemMenusToSuperAdmin($superRoleId, $now)
    {
        $systemMenuIds = DB::table('admin_menu')
            ->whereIn('uri', ['auth/users', 'auth/roles', 'auth/permissions', 'auth/menu', 'auth/logs'])
            ->pluck('id')
            ->all();

        if (!$systemMenuIds) {
            return;
        }

        $pivot = config('admin.database.role_menu_table', 'admin_role_menu');
        $otherRoleIds = DB::table('admin_roles')
            ->where('id', '!=', $superRoleId)
            ->pluck('id')
            ->all();

        foreach ($otherRoleIds as $roleId) {
            DB::table($pivot)
                ->where('role_id', $roleId)
                ->whereIn('menu_id', $systemMenuIds)
                ->delete();
        }

        foreach ($systemMenuIds as $menuId) {
            $this->attachRoleMenu($superRoleId, $menuId, $now);
        }
    }

    protected function attachRolePermission($roleId, $permissionId, $now)
    {
        $table = config('admin.database.role_permissions_table', 'admin_role_permissions');
        if (!DB::table($table)->where('role_id', $roleId)->where('permission_id', $permissionId)->exists()) {
            DB::table($table)->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    protected function attachRoleMenu($roleId, $menuId, $now)
    {
        $table = config('admin.database.role_menu_table', 'admin_role_menu');
        if (!DB::table($table)->where('role_id', $roleId)->where('menu_id', $menuId)->exists()) {
            DB::table($table)->insert([
                'role_id' => $roleId,
                'menu_id' => $menuId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    protected function attachUserRole($userId, $roleId, $now)
    {
        $table = config('admin.database.role_users_table', 'admin_role_users');
        if (!DB::table($table)->where('user_id', $userId)->where('role_id', $roleId)->exists()) {
            DB::table($table)->insert([
                'user_id' => $userId,
                'role_id' => $roleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    protected function attachUserPermission($userId, $permissionId, $now)
    {
        $table = config('admin.database.user_permissions_table', 'admin_user_permissions');
        if (!DB::table($table)->where('user_id', $userId)->where('permission_id', $permissionId)->exists()) {
            DB::table($table)->insert([
                'user_id' => $userId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
