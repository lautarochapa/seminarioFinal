<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddNotificationsUserPermission extends Migration
{
    public function up()
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['code' => 'web.user.notifications'],
            [
                'code'        => 'web.user.notifications',
                'module'      => 'web.user',
                'action'      => 'access',
                'description' => 'Acceso al centro de notificaciones del usuario',
                'status'      => 'active',
            ]
        );

        $permId = DB::table('permissions')->where('code', 'web.user.notifications')->value('id');

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
        $perm = DB::table('permissions')->where('code', 'web.user.notifications')->first();
        if ($perm) {
            DB::table('role_permissions')->where('permission_id', $perm->id)->delete();
            DB::table('permissions')->where('id', $perm->id)->delete();
        }
    }
}
