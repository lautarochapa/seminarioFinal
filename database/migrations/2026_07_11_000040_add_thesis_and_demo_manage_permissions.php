<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddThesisAndDemoManagePermissions extends Migration
{
    private $permissions = [
        [
            'code'        => 'thesis_documents.manage',
            'module'      => 'thesis_documents',
            'action'      => 'manage',
            'description' => 'Administrar documentacion de tesis',
            'status'      => 'active',
        ],
        [
            'code'        => 'demo_scenarios.manage',
            'module'      => 'demo_scenarios',
            'action'      => 'manage',
            'description' => 'Administrar escenarios demo',
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

        $permissionIds = DB::table('permissions')
            ->whereIn('code', array_column($this->permissions, 'code'))
            ->pluck('id');

        $roleIds = DB::table('roles')
            ->whereIn('code', ['super_admin', 'admin'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
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
        //
    }
}
