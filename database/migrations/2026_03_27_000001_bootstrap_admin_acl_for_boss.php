<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class BootstrapAdminAclForBoss extends Migration
{
    public function up()
    {
        $connection = config('admin.database.connection') ?: config('database.default');

        $usersTable = config('admin.database.users_table');
        $rolesTable = config('admin.database.roles_table');
        $permissionsTable = config('admin.database.permissions_table');
        $menuTable = config('admin.database.menu_table');
        $roleUsersTable = config('admin.database.role_users_table');
        $rolePermissionsTable = config('admin.database.role_permissions_table');
        $roleMenuTable = config('admin.database.role_menu_table');

        $now = date('Y-m-d H:i:s');

        // 1) Ensure administrator role exists.
        $role = DB::connection($connection)->table($rolesTable)->where('slug', 'administrator')->first();
        if (!$role) {
            $roleId = DB::connection($connection)->table($rolesTable)->insertGetId([
                'name' => 'Administrator',
                'slug' => 'administrator',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $roleId = $role->id;
        }

        // 2) Ensure all-permission (*) exists.
        $permission = DB::connection($connection)->table($permissionsTable)->where('slug', '*')->first();
        if (!$permission) {
            $permissionId = DB::connection($connection)->table($permissionsTable)->insertGetId([
                'name' => 'All permission',
                'slug' => '*',
                'http_method' => '',
                'http_path' => '*',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $permissionId = $permission->id;
        }

        // 3) Ensure role-permission mapping exists.
        $existsRolePerm = DB::connection($connection)->table($rolePermissionsTable)
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->exists();
        if (!$existsRolePerm) {
            DB::connection($connection)->table($rolePermissionsTable)->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 4) Ensure default admin menus exist (including products/orders).
        $defaultMenus = [
            ['parent_id' => 0, 'order' => 1, 'title' => 'Dashboard', 'icon' => 'fa-bar-chart', 'uri' => '/'],
            ['parent_id' => 0, 'order' => 2, 'title' => 'Users', 'icon' => 'fa-users', 'uri' => 'users'],
            ['parent_id' => 0, 'order' => 3, 'title' => 'Products', 'icon' => 'fa-cubes', 'uri' => 'products'],
            ['parent_id' => 0, 'order' => 4, 'title' => 'Orders', 'icon' => 'fa-rmb', 'uri' => 'orders'],
            ['parent_id' => 0, 'order' => 5, 'title' => 'Coupon Codes', 'icon' => 'fa-tags', 'uri' => 'coupon_codes'],
        ];

        foreach ($defaultMenus as $menuData) {
            $menu = DB::connection($connection)->table($menuTable)
                ->where('title', $menuData['title'])
                ->where('uri', $menuData['uri'])
                ->first();

            if (!$menu) {
                $menuId = DB::connection($connection)->table($menuTable)->insertGetId([
                    'parent_id' => $menuData['parent_id'],
                    'order' => $menuData['order'],
                    'title' => $menuData['title'],
                    'icon' => $menuData['icon'],
                    'uri' => $menuData['uri'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $menuId = $menu->id;
            }

            $existsRoleMenu = DB::connection($connection)->table($roleMenuTable)
                ->where('role_id', $roleId)
                ->where('menu_id', $menuId)
                ->exists();
            if (!$existsRoleMenu) {
                DB::connection($connection)->table($roleMenuTable)->insert([
                    'role_id' => $roleId,
                    'menu_id' => $menuId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // 5) Ensure boss user is mapped to administrator role.
        $boss = DB::connection($connection)->table($usersTable)->where('username', 'boss')->first();
        if ($boss) {
            $existsRoleUser = DB::connection($connection)->table($roleUsersTable)
                ->where('role_id', $roleId)
                ->where('user_id', $boss->id)
                ->exists();

            if (!$existsRoleUser) {
                DB::connection($connection)->table($roleUsersTable)->insert([
                    'role_id' => $roleId,
                    'user_id' => $boss->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down()
    {
        // Intentionally left blank to avoid removing active ACL data.
    }
}
