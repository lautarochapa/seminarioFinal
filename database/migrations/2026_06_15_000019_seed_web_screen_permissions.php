<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedWebScreenPermissions extends Migration
{
    private $userScreens = [
        'dashboard', 'stock', 'recipes', 'planning', 'shopping-list', 'budget', 'reports',
        'family-group', 'profile-objectives', 'professional-permissions',
    ];

    private $adminScreens = [
        'dashboard', 'users', 'roles-permissions', 'ingredients', 'ingredient-categories',
        'nutrients', 'units-conversions', 'equivalences', 'products', 'brands', 'barcodes',
        'supermarkets', 'branches', 'prices', 'promotions', 'supermarket-scraping',
        'scraped-products', 'official-recipes', 'imported-recipes', 'recipe-scraping',
        'recipe-tags', 'scraping-alerts', 'admin-reports', 'audit', 'settings',
        'thesis-docs', 'demo-scenarios',
    ];

    private $teacherScreens = [
        'home', 'functional-docs', 'technical-docs', 'comments', 'demo-scenarios', 'demo-metrics',
    ];

    public function up()
    {
        $now = now();
        $permissions = [];

        foreach ($this->userScreens as $screen) {
            $permissions[] = $this->permission('web.user.' . $screen, 'web.user', 'access', 'Acceder a pantalla de usuario: ' . $screen);
        }

        foreach ($this->adminScreens as $screen) {
            $permissions[] = $this->permission('web.admin.' . $screen, 'web.admin', 'access', 'Acceder a pantalla admin: ' . $screen);
        }

        foreach ($this->teacherScreens as $screen) {
            $permissions[] = $this->permission('web.teacher.' . $screen, 'web.teacher', 'access', 'Acceder a pantalla docente: ' . $screen);
        }

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                $permission + ['status' => 'active']
            );
        }

        $rolePermissions = [
            'user' => array_map(function ($screen) { return 'web.user.' . $screen; }, $this->userScreens),
            'dietologist' => [
                'web.user.dashboard',
                'web.user.reports',
                'web.user.professional-permissions',
            ],
            'catalog_admin' => [
                'web.admin.dashboard',
                'web.admin.ingredients',
                'web.admin.ingredient-categories',
                'web.admin.nutrients',
                'web.admin.units-conversions',
                'web.admin.equivalences',
                'web.admin.products',
                'web.admin.brands',
                'web.admin.barcodes',
                'web.admin.scraped-products',
            ],
            'supermarket_admin' => [
                'web.admin.dashboard',
                'web.admin.supermarkets',
                'web.admin.branches',
                'web.admin.prices',
                'web.admin.promotions',
                'web.admin.supermarket-scraping',
                'web.admin.scraping-alerts',
            ],
            'recipe_admin' => [
                'web.admin.dashboard',
                'web.admin.official-recipes',
                'web.admin.imported-recipes',
                'web.admin.recipe-scraping',
                'web.admin.recipe-tags',
            ],
            'teacher' => array_map(function ($screen) { return 'web.teacher.' . $screen; }, $this->teacherScreens),
            'system_jobs' => [
                'web.admin.dashboard',
                'web.admin.supermarket-scraping',
                'web.admin.recipe-scraping',
                'web.admin.scraping-alerts',
            ],
        ];

        $superAdminPermissions = array_map(function ($permission) {
            return $permission['code'];
        }, $permissions);
        $rolePermissions['super_admin'] = $superAdminPermissions;

        foreach ($rolePermissions as $roleCode => $permissionCodes) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            if (! $roleId) {
                continue;
            }

            $permissionIds = DB::table('permissions')->whereIn('code', $permissionCodes)->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    ['created_at' => $now]
                );
            }
        }
    }

    public function down()
    {
        // Screen permissions are intentionally preserved.
    }

    private function permission($code, $module, $action, $description)
    {
        return [
            'code' => $code,
            'module' => $module,
            'action' => $action,
            'description' => $description,
        ];
    }
}
