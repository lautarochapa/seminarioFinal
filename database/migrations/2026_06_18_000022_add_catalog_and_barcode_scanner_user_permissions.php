<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddCatalogAndBarcodeScannerUserPermissions extends Migration
{
    private $permissions = [
        [
            'code'        => 'web.user.catalog',
            'module'      => 'web.user',
            'action'      => 'access',
            'description' => 'Acceder a pantalla de usuario: catalog',
            'status'      => 'active',
        ],
        [
            'code'        => 'web.user.barcode-scanner',
            'module'      => 'web.user',
            'action'      => 'access',
            'description' => 'Acceder a pantalla de usuario: barcode-scanner',
            'status'      => 'active',
        ],
    ];

    public function up()
    {
        $now = now();

        foreach ($this->permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                $permission
            );
        }

        $codes    = array_column($this->permissions, 'code');
        $permIds  = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
        $roleIds  = DB::table('roles')->whereIn('code', ['user', 'super_admin'])->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permIds as $permId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permId],
                    ['created_at' => $now]
                );
            }
        }
    }

    public function down()
    {
        
    }
}
