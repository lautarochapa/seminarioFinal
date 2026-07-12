<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddHealthPreferencesWebPermission extends Migration
{
    public function up()
    {
        $permission = [
            'code' => 'web.admin.health-preferences',
            'module' => 'web.admin',
            'action' => 'access',
            'description' => 'Acceder a pantalla admin: health-preferences',
            'status' => 'active',
        ];

        DB::table('permissions')->updateOrInsert(
            ['code' => $permission['code']],
            $permission
        );

        $roleCodes = ['super_admin', 'catalog_admin'];
        $roleIds = DB::table('roles')->whereIn('code', $roleCodes)->pluck('id');
        $permissionId = DB::table('permissions')->where('code', $permission['code'])->value('id');

        if ($permissionId) {
            foreach ($roleIds as $roleId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    ['created_at' => now()]
                );
            }
        }
    }

    public function down()
    {
        // Preserve permission for deployed environments.
    }
}
