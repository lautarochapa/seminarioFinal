<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddSupplementsUserPermission extends Migration
{
    public function up()
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['code' => 'web.user.supplements'],
            [
                'code'        => 'web.user.supplements',
                'module'      => 'web.user',
                'action'      => 'access',
                'description' => 'Acceso a pantalla de suplementos del usuario',
                'status'      => 'active',
            ]
        );

        $permId = DB::table('permissions')->where('code', 'web.user.supplements')->value('id');

        if (! $permId) {
            return;
        }

        foreach (['user', 'super_admin'] as $roleCode) {
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
        $perm = DB::table('permissions')->where('code', 'web.user.supplements')->first();
        if ($perm) {
            DB::table('role_permissions')->where('permission_id', $perm->id)->delete();
            DB::table('permissions')->where('id', $perm->id)->delete();
        }
    }
}
