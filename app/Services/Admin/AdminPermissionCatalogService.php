<?php

namespace App\Services\Admin;

use Encore\Admin\Auth\Database\Menu;
use Encore\Admin\Auth\Database\Permission;
use Encore\Admin\Auth\Database\Role;
use Illuminate\Support\Facades\DB;

/**
 * 按业务模块拆分角色（mod-xxx），勾选即分配，菜单与权限自动对齐。
 */
class AdminPermissionCatalogService
{
    const MODULES = [
        'dashboard' => ['label' => '后台首页', 'hint' => '登录后默认首页', 'menu_uri' => '/'],
        'users' => ['label' => '用户管理', 'hint' => '前台注册用户', 'menu_uri' => 'users'],
        'products' => ['label' => '商品管理', 'hint' => '商品、批量、导入', 'menu_uri' => 'products'],
        'orders' => ['label' => '订单管理', 'hint' => '订单处理、发货、导出', 'menu_uri' => 'orders'],
        'coupon_codes' => ['label' => '优惠券', 'hint' => '优惠券创建与发放', 'menu_uri' => 'coupon_codes'],
        'categories' => ['label' => '分类管理', 'hint' => '商品分类', 'menu_uri' => 'categories'],
        'support_feedbacks' => ['label' => '客户反馈', 'hint' => '客服反馈回复', 'menu_uri' => 'support-feedbacks'],
        'site_settings' => ['label' => '站点设置', 'hint' => 'Logo、汇率等', 'menu_uri' => 'site-settings/logo'],
        'procurement_orders' => ['label' => '代购需求', 'hint' => 'B 站代购单', 'menu_uri' => 'procurement-orders'],
        'procurement_reference_items' => ['label' => '参考商品库', 'hint' => '代购参考库', 'menu_uri' => 'procurement-reference-items'],
        'proxy_qualifications' => ['label' => '代购资质审核', 'hint' => '资质审核', 'menu_uri' => 'proxy-qualifications'],
    ];

    /** 岗位套餐：仅用于界面一键勾选 */
    const PRESETS = [
        'preset-order' => [
            'name' => '订单运营',
            'desc' => '订单 + 用户，不改商品',
            'modules' => ['dashboard', 'users', 'orders'],
        ],
        'preset-product' => [
            'name' => '商品维护',
            'desc' => '商品与分类',
            'modules' => ['dashboard', 'products', 'categories'],
        ],
        'preset-support' => [
            'name' => '客服反馈',
            'desc' => '反馈 + 订单查看',
            'modules' => ['dashboard', 'support_feedbacks', 'orders'],
        ],
        'preset-marketing' => [
            'name' => '营销运营',
            'desc' => '优惠券 + 商品',
            'modules' => ['dashboard', 'coupon_codes', 'products'],
        ],
        'preset-procurement' => [
            'name' => '代购业务',
            'desc' => '代购相关全部',
            'modules' => ['dashboard', 'procurement_orders', 'procurement_reference_items', 'proxy_qualifications'],
        ],
        'preset-readonly' => [
            'name' => '只读巡查',
            'desc' => '仅看订单与商品',
            'modules' => ['dashboard', 'orders', 'products'],
        ],
    ];

    const BASE_PERMISSION_SLUGS = ['auth.login', 'auth.setting'];

    public function groupedModulesForUi()
    {
        $groups = [
            '日常运营' => ['dashboard', 'users', 'orders', 'support_feedbacks'],
            '商品与分类' => ['products', 'categories'],
            '营销' => ['coupon_codes'],
            '站点与代购' => ['site_settings', 'procurement_orders', 'procurement_reference_items', 'proxy_qualifications'],
        ];

        $result = [];
        foreach ($groups as $title => $slugs) {
            $items = [];
            foreach ($slugs as $slug) {
                if (!isset(self::MODULES[$slug])) {
                    continue;
                }
                $items[] = [
                    'slug' => $slug,
                    'label' => self::MODULES[$slug]['label'],
                    'hint' => self::MODULES[$slug]['hint'],
                ];
            }
            if ($items) {
                $result[$title] = $items;
            }
        }

        return $result;
    }

    public function presetList()
    {
        return self::PRESETS;
    }

    public function moduleRoleSlug($moduleSlug)
    {
        return 'mod-'.$moduleSlug;
    }

    public function ensureModuleRoles()
    {
        $loginPermIds = Permission::query()
            ->whereIn('slug', self::BASE_PERMISSION_SLUGS)
            ->pluck('id')
            ->all();

        foreach (self::MODULES as $moduleSlug => $cfg) {
            $roleSlug = $this->moduleRoleSlug($moduleSlug);
            $role = Role::query()->firstOrCreate(
                ['slug' => $roleSlug],
                ['name' => '模块：'.$cfg['label']]
            );

            $permIds = Permission::query()->where('slug', $moduleSlug)->pluck('id')->all();
            $role->permissions()->sync(array_values(array_unique(array_merge($loginPermIds, $permIds))));

            $menuId = Menu::query()->where('uri', $cfg['menu_uri'])->value('id');
            $this->syncRoleMenus($role, $menuId ? [(int) $menuId] : []);
        }
    }

    protected function syncRoleMenus(Role $role, array $menuIds)
    {
        $table = config('admin.database.role_menu_table', 'admin_role_menu');
        DB::table($table)->where('role_id', $role->id)->delete();
        $now = date('Y-m-d H:i:s');
        foreach ($menuIds as $menuId) {
            DB::table($table)->insert([
                'role_id' => $role->id,
                'menu_id' => $menuId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function roleIdsFromModuleSlugs(array $moduleSlugs)
    {
        $this->ensureModuleRoles();
        $ids = [];
        foreach (array_unique($moduleSlugs) as $slug) {
            if (!isset(self::MODULES[$slug])) {
                continue;
            }
            $id = Role::query()->where('slug', $this->moduleRoleSlug($slug))->value('id');
            if ($id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    public function moduleSlugsFromUser($user)
    {
        if (!$user) {
            return [];
        }

        $slugs = [];
        foreach ($this->roleItemsFromUser($user) as $role) {
            $roleSlug = (string) data_get($role, 'slug', '');
            if (strpos($roleSlug, 'mod-') === 0) {
                $slugs[] = substr($roleSlug, 4);
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * 兼容 Eloquent 关联与 laravel-admin 表格行内 roles 数组。
     *
     * @param mixed $user
     *
     * @return array
     */
    protected function roleItemsFromUser($user)
    {
        if (is_array($user)) {
            return (array) data_get($user, 'roles', []);
        }

        if ($user instanceof \Illuminate\Database\Eloquent\Model) {
            if ($user->relationLoaded('roles')) {
                $roles = $user->getRelation('roles');
            } else {
                $attr = $user->getAttribute('roles');
                if ($attr !== null) {
                    $roles = $attr;
                } else {
                    try {
                        $user->loadMissing('roles');
                        $roles = $user->getRelation('roles');
                    } catch (\Throwable $e) {
                        $roles = [];
                    }
                }
            }

            if ($roles instanceof \Illuminate\Support\Collection) {
                return $roles->all();
            }

            return is_array($roles) ? $roles : [];
        }

        return (array) data_get($user, 'roles', []);
    }

    public function labelsFromModuleSlugs(array $moduleSlugs)
    {
        $out = [];
        foreach ($moduleSlugs as $slug) {
            if (isset(self::MODULES[$slug])) {
                $out[] = self::MODULES[$slug]['label'];
            }
        }

        return $out;
    }
}
