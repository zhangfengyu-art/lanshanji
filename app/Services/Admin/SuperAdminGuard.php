<?php

namespace App\Services\Admin;

use App\Models\Admin\Administrator;
use Encore\Admin\Auth\Database\Role;
use Encore\Admin\Facades\Admin;
use Illuminate\Auth\Access\AuthorizationException;

class SuperAdminGuard
{
    const ROLE_SLUG = 'super-admin';

    public static function isSuperAdmin($user = null)
    {
        $user = $user ?: Admin::user();
        if (!$user) {
            return false;
        }

        if ($user instanceof Administrator) {
            $user->loadMissing('roles');

            return $user->isRole(self::ROLE_SLUG);
        }

        foreach ((array) data_get($user, 'roles', []) as $role) {
            if (data_get($role, 'slug') === self::ROLE_SLUG) {
                return true;
            }
        }

        return false;
    }

    /**
     * laravel-admin 表格行内 roles 可能是数组，统一取出角色名。
     */
    public static function roleNamesFromRow($row)
    {
        $roles = data_get($row, 'roles', []);

        return collect($roles)->map(function ($role) {
            return (string) data_get($role, 'name', '');
        })->filter()->values();
    }

    public static function assertSuperAdmin()
    {
        if (!self::isSuperAdmin()) {
            throw new AuthorizationException('仅终极管理员可访问此功能。');
        }
    }

    /**
     * 可分配给下级管理员的角色（不含终极管理员角色）。
     */
    public static function assignableRoles()
    {
        return Role::query()
            ->where('slug', '!=', self::ROLE_SLUG)
            ->orderBy('name')
            ->pluck('name', 'id');
    }
}
