<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SecuritySeeder extends Seeder
{
    public function run()
    {
        $now = now();

        $roles = [
            ['code' => 'user', 'name' => 'Usuario comun', 'description' => 'Registro, perfil, grupo familiar, stock, recetas, planificacion, compras, presupuesto, reportes, notificaciones y suplementos.'],
            ['code' => 'dietologist', 'name' => 'Dietologo / profesional', 'description' => 'Ver usuarios autorizados, consultar perfil, reportes y planificacion, y modificar planes si tiene permiso.'],
            ['code' => 'catalog_admin', 'name' => 'Admin catalogo', 'description' => 'Ingredientes, productos, marcas, categorias, nutrientes, unidades, equivalencias y validacion de productos scrapeados.'],
            ['code' => 'supermarket_admin', 'name' => 'Admin supermercados', 'description' => 'Supermercados, sucursales, precios, promociones, scraping de supermercados y alertas.'],
            ['code' => 'recipe_admin', 'name' => 'Admin recetas / chef', 'description' => 'Recetas oficiales, tags, categorias, importacion y revision de recetas externas.'],
            ['code' => 'teacher', 'name' => 'Docente', 'description' => 'Ver documentacion, comentar documentacion y acceder a escenarios demo.'],
            ['code' => 'super_admin', 'name' => 'Super admin', 'description' => 'Usuarios, roles, permisos, auditoria, configuracion, feature flags, alertas criticas y control total.'],
            ['code' => 'system_jobs', 'name' => 'Sistema / Jobs', 'description' => 'Scraping, calculos nutricionales, costos, stock automatico, alertas, presupuesto, recomendaciones e incompatibilidades.'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['code' => $role['code']],
                $role + ['status' => 'active']
            );
        }

        $permissions = [
            ['code' => 'security.users.read', 'module' => 'security.users', 'action' => 'read', 'description' => 'Ver usuarios.'],
            ['code' => 'security.users.write', 'module' => 'security.users', 'action' => 'write', 'description' => 'Crear y editar usuarios.'],
            ['code' => 'security.roles.read', 'module' => 'security.roles', 'action' => 'read', 'description' => 'Ver roles.'],
            ['code' => 'security.roles.write', 'module' => 'security.roles', 'action' => 'write', 'description' => 'Crear y editar roles.'],
            ['code' => 'security.permissions.read', 'module' => 'security.permissions', 'action' => 'read', 'description' => 'Ver permisos.'],
            ['code' => 'audit.read', 'module' => 'audit', 'action' => 'read', 'description' => 'Ver auditoria.'],
            ['code' => 'profile.manage', 'module' => 'profile', 'action' => 'manage', 'description' => 'Gestionar perfil personal.'],
            ['code' => 'family.manage', 'module' => 'family', 'action' => 'manage', 'description' => 'Gestionar grupo familiar.'],
            ['code' => 'stock.manage', 'module' => 'stock', 'action' => 'manage', 'description' => 'Gestionar stock familiar.'],
            ['code' => 'recipes.use', 'module' => 'recipes', 'action' => 'use', 'description' => 'Consultar y usar recetas.'],
            ['code' => 'meal_plans.manage', 'module' => 'meal_plans', 'action' => 'manage', 'description' => 'Gestionar planificacion de comidas.'],
            ['code' => 'shopping.manage', 'module' => 'shopping', 'action' => 'manage', 'description' => 'Gestionar compras.'],
            ['code' => 'budget.manage', 'module' => 'budget', 'action' => 'manage', 'description' => 'Gestionar presupuesto familiar.'],
            ['code' => 'reports.read', 'module' => 'reports', 'action' => 'read', 'description' => 'Ver reportes.'],
            ['code' => 'notifications.manage', 'module' => 'notifications', 'action' => 'manage', 'description' => 'Gestionar notificaciones.'],
            ['code' => 'supplements.manage', 'module' => 'supplements', 'action' => 'manage', 'description' => 'Gestionar suplementos.'],
            ['code' => 'professional.users.read', 'module' => 'professional.users', 'action' => 'read', 'description' => 'Ver usuarios autorizados por vinculo profesional.'],
            ['code' => 'professional.meal_plans.write', 'module' => 'professional.meal_plans', 'action' => 'write', 'description' => 'Modificar planes autorizados.'],
            ['code' => 'catalog.manage', 'module' => 'catalog', 'action' => 'manage', 'description' => 'Gestionar catalogo alimentario y comercial.'],
            ['code' => 'scraped_products.review', 'module' => 'scraping.products', 'action' => 'review', 'description' => 'Revisar productos scrapeados.'],
            ['code' => 'supermarkets.manage', 'module' => 'supermarkets', 'action' => 'manage', 'description' => 'Gestionar supermercados, sucursales, precios y promociones.'],
            ['code' => 'scraping.manage', 'module' => 'scraping', 'action' => 'manage', 'description' => 'Gestionar scraping y alertas tecnicas.'],
            ['code' => 'recipes.manage', 'module' => 'recipes', 'action' => 'manage', 'description' => 'Gestionar recetas oficiales, tags, categorias e importaciones.'],
            ['code' => 'thesis_documents.read', 'module' => 'thesis_documents', 'action' => 'read', 'description' => 'Ver documentacion de tesis.'],
            ['code' => 'thesis_comments.write', 'module' => 'thesis_comments', 'action' => 'write', 'description' => 'Comentar documentacion.'],
            ['code' => 'demo_scenarios.read', 'module' => 'demo_scenarios', 'action' => 'read', 'description' => 'Acceder a escenarios demo.'],
            ['code' => 'settings.manage', 'module' => 'settings', 'action' => 'manage', 'description' => 'Gestionar configuraciones generales.'],
            ['code' => 'feature_flags.manage', 'module' => 'feature_flags', 'action' => 'manage', 'description' => 'Gestionar feature flags.'],
            ['code' => 'jobs.run', 'module' => 'jobs', 'action' => 'run', 'description' => 'Ejecutar procesos internos del sistema.'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                $permission + ['status' => 'active']
            );
        }

        $rolePermissions = [
            'user' => ['profile.manage', 'family.manage', 'stock.manage', 'recipes.use', 'meal_plans.manage', 'shopping.manage', 'budget.manage', 'reports.read', 'notifications.manage', 'supplements.manage'],
            'dietologist' => ['professional.users.read', 'reports.read', 'meal_plans.manage', 'professional.meal_plans.write'],
            'catalog_admin' => ['catalog.manage', 'scraped_products.review'],
            'supermarket_admin' => ['supermarkets.manage', 'scraping.manage'],
            'recipe_admin' => ['recipes.manage'],
            'teacher' => ['thesis_documents.read', 'thesis_comments.write', 'demo_scenarios.read'],
            'system_jobs' => ['jobs.run', 'scraping.manage', 'stock.manage', 'budget.manage', 'notifications.manage'],
        ];

        foreach ($rolePermissions as $roleCode => $permissionCodes) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            $permissionIds = DB::table('permissions')->whereIn('code', $permissionCodes)->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    ['created_at' => $now]
                );
            }
        }

        $superAdminRoleId = DB::table('roles')->where('code', 'super_admin')->value('id');
        $permissionIds = DB::table('permissions')->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $superAdminRoleId, 'permission_id' => $permissionId],
                ['created_at' => $now]
            );
        }
    }
}
