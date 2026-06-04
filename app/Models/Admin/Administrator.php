<?php

namespace App\Models\Admin;

use Encore\Admin\Auth\Database\Administrator as BaseAdministrator;
use Illuminate\Support\Collection;

/**
 * 兼容 laravel-admin 表格行把 roles/permissions 变成数组 的情况。
 */
class Administrator extends BaseAdministrator
{
    public function isAdministrator(): bool
    {
        return $this->isRole('administrator') || $this->isRole('super-admin');
    }

    public function isRole(string $role): bool
    {
        return $this->roleSlugs()->contains($role);
    }

    public function inRoles(array $roles = []): bool
    {
        return $this->roleSlugs()->intersect($roles)->isNotEmpty();
    }

    public function can(string $permission): bool
    {
        if ($this->isAdministrator() || $this->isRole('super-admin')) {
            return true;
        }

        if ($this->permissionSlugs()->contains($permission)) {
            return true;
        }

        return $this->rolePermissionSlugs()->contains($permission);
    }

    protected function roleSlugs(): Collection
    {
        return $this->normalizeRelationItems($this->resolveRoles())->pluck('slug');
    }

    protected function permissionSlugs(): Collection
    {
        return $this->normalizeRelationItems($this->resolvePermissions())->pluck('slug');
    }

    protected function rolePermissionSlugs(): Collection
    {
        $slugs = collect();
        foreach ($this->normalizeRelationItems($this->resolveRoles()) as $role) {
            $perms = data_get($role, 'permissions');
            if (is_array($perms) || $perms instanceof Collection) {
                $slugs = $slugs->merge(collect($perms)->pluck('slug'));
            }
        }

        if ($slugs->isEmpty() && $this->relationLoaded('roles')) {
            try {
                return $this->roles()->with('permissions')->get()
                    ->pluck('permissions')->flatten()->pluck('slug');
            } catch (\Throwable $e) {
                return collect();
            }
        }

        return $slugs;
    }

    protected function resolveRoles()
    {
        if ($this->relationLoaded('roles')) {
            return $this->getRelation('roles');
        }

        $attr = $this->getAttribute('roles');
        if ($attr !== null) {
            return $attr;
        }

        return $this->roles()->get();
    }

    protected function resolvePermissions()
    {
        if ($this->relationLoaded('permissions')) {
            return $this->getRelation('permissions');
        }

        $attr = $this->getAttribute('permissions');
        if ($attr !== null) {
            return $attr;
        }

        return $this->permissions()->get();
    }

    protected function normalizeRelationItems($items): Collection
    {
        if ($items instanceof Collection) {
            return $items;
        }

        if (is_array($items)) {
            return collect($items);
        }

        return collect();
    }
}
