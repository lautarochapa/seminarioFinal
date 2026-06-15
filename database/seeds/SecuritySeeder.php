<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SecuritySeeder extends Seeder
{
    public function run()
    {
        $roles = [
            ['code' => 'user', 'name' => 'Usuario', 'description' => 'Usuario base del sistema.'],
            ['code' => 'dietologist', 'name' => 'Dietologo', 'description' => 'Profesional de dietas y seguimiento nutricional.'],
            ['code' => 'catalog_admin', 'name' => 'Admin catalogo', 'description' => 'Administrador del catalogo de productos e insumos.'],
            ['code' => 'supermarket_admin', 'name' => 'Admin supermercados', 'description' => 'Administrador de supermercados y sucursales.'],
            ['code' => 'recipe_admin', 'name' => 'Admin recetas', 'description' => 'Administrador de recetas.'],
            ['code' => 'teacher', 'name' => 'Docente', 'description' => 'Docente con acceso academico.'],
            ['code' => 'super_admin', 'name' => 'Super admin', 'description' => 'Administrador total del sistema.'],
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
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                $permission + ['status' => 'active']
            );
        }

        $superAdminRoleId = DB::table('roles')->where('code', 'super_admin')->value('id');
        $permissionIds = DB::table('permissions')->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_permissions')->updateOrInsert([
                'role_id' => $superAdminRoleId,
                'permission_id' => $permissionId,
            ], [
                'created_at' => now(),
            ]);
        }
    }
}
