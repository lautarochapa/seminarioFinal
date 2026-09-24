<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\DB;

class RolePolicy
{
    const RETIRED = ['teacher', 'dietologist', 'system_jobs', 'supermarket_admin'];
    const CURRENT = ['user', 'catalog_admin', 'recipe_admin', 'super_admin'];

    public static function retiredPermission(string $code): bool
    {
        foreach (['professional.', 'thesis_documents.', 'thesis_comments.', 'demo_scenarios.', 'web.teacher.'] as $prefix) {
            if (strpos($code, $prefix) === 0) {
                return true;
            }
        }
        return in_array($code, ['jobs.run', 'web.user.professional-permissions', 'web.admin.thesis-docs', 'web.admin.demo-scenarios'], true);
    }

    public static function synchronize(): void
    {
        DB::transaction(function () {
            $names = [
                'user' => 'Usuario comun',
                'catalog_admin' => 'Administrador de catalogo y supermercados',
                'recipe_admin' => 'Administrador de recetas / chef',
                'super_admin' => 'Superadministrador',
            ];
            foreach ($names as $code => $name) {
                DB::table('roles')->updateOrInsert(['code' => $code], ['name' => $name, 'status' => 'active']);
            }
            $roles = DB::table('roles')->pluck('id', 'code');
            // Preserve existing supermarket administrators, merging their access into catalog.
            if (isset($roles['supermarket_admin'])) {
                foreach (DB::table('user_roles')->where('role_id', $roles['supermarket_admin'])->get() as $assignment) {
                    DB::table('user_roles')->updateOrInsert(
                        ['user_id' => $assignment->user_id, 'role_id' => $roles['catalog_admin']],
                        ['created_at' => now()]
                    );
                }
            }
            DB::table('roles')->whereIn('code', self::RETIRED)->update(['status' => 'inactive']);
            $retiredIds = DB::table('roles')->whereIn('code', self::RETIRED)->pluck('id');
            DB::table('role_permissions')->whereIn('role_id', $retiredIds)->delete();
            DB::table('user_roles')->whereIn('role_id', $retiredIds)->delete();

            $catalogScreens = [
                'dashboard', 'ingredients', 'ingredient-categories', 'nutrients', 'units-conversions',
                'equivalences', 'products', 'brands', 'barcodes', 'scraped-products', 'objectives',
                'health-preferences', 'product-requests', 'product-reports', 'cities', 'supermarkets',
                'branches', 'prices', 'promotions', 'supermarket-scraping', 'scraping-alerts',
            ];
            $recipeScreens = [
                'dashboard', 'official-recipes', 'imported-recipes', 'recipe-scraping',
                'recipe-tags', 'recipe-categories', 'recipe-import', 'recipe-import-text',
            ];
            $catalog = array_merge(['catalog.manage', 'scraped_products.review', 'supermarkets.manage', 'scraping.manage'],
                array_map(function ($s) { return 'web.admin.'.$s; }, $catalogScreens));
            $recipes = array_merge(['recipes.manage'],
                array_map(function ($s) { return 'web.admin.'.$s; }, $recipeScreens));
            $user = ['profile.manage', 'family.manage', 'stock.manage', 'recipes.use', 'meal_plans.manage',
                'shopping.manage', 'budget.manage', 'reports.read', 'notifications.manage', 'supplements.manage'];
            foreach (self::CURRENT as $role) {
                DB::table('role_permissions')->where('role_id', $roles[$role])->delete();
            }
            foreach (DB::table('permissions')->get() as $permission) {
                if (self::retiredPermission($permission->code)) {
                    DB::table('permissions')->where('id', $permission->id)->update(['status' => 'inactive']);
                    continue;
                }
                if ($permission->status !== 'active') {
                    continue;
                }
                $grants = ['super_admin'];
                if (in_array($permission->code, $catalog, true)) $grants[] = 'catalog_admin';
                if (in_array($permission->code, $recipes, true)) $grants[] = 'recipe_admin';
                if (in_array($permission->code, $user, true) || strpos($permission->code, 'web.user.') === 0) $grants[] = 'user';
                foreach ($grants as $role) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $roles[$role], 'permission_id' => $permission->id, 'created_at' => now(),
                    ]);
                }
            }
        });
    }
}
