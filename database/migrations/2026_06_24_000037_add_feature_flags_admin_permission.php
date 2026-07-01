<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddFeatureFlagsAdminPermission extends Migration
{
    public function up()
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['code' => 'web.admin.feature-flags'],
            [
                'code'        => 'web.admin.feature-flags',
                'module'      => 'web.admin',
                'action'      => 'access',
                'description' => 'Acceso al panel de feature flags',
                'status'      => 'active',
            ]
        );

        $permId = DB::table('permissions')->where('code', 'web.admin.feature-flags')->value('id');

        if (! $permId) {
            return;
        }

        foreach (['super_admin'] as $roleCode) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            if ($roleId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permId],
                    ['created_at' => $now]
                );
            }
        }
    }

    public function down()
    {
        $perm = DB::table('permissions')->where('code', 'web.admin.feature-flags')->first();
        if ($perm) {
            DB::table('role_permissions')->where('permission_id', $perm->id)->delete();
            DB::table('permissions')->where('id', $perm->id)->delete();
        }
    }
}
