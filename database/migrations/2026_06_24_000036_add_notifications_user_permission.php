<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddNotificationsUserPermission extends Migration
{
    public function up()
    {
        $permId = DB::table('permissions')->insertGetId([
            'code'        => 'web.user.notifications',
            'description' => 'Acceso al centro de notificaciones del usuario',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $roles = DB::table('roles')->whereIn('code', ['user', 'super_admin'])->pluck('id');
        foreach ($roles as $roleId) {
            DB::table('role_permissions')->insert([
                'role_id'       => $roleId,
                'permission_id' => $permId,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
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
