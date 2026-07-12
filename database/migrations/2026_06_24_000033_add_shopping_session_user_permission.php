<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddShoppingSessionUserPermission extends Migration
{
    public function up()
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['code' => 'web.user.shopping-session'],
            [
                'code'        => 'web.user.shopping-session',
                'module'      => 'web.user',
                'action'      => 'access',
                'description' => 'Pantalla sesión de compra mobile',
                'status'      => 'active',
            ]
        );

        $permId = DB::table('permissions')->where('code', 'web.user.shopping-session')->value('id');

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
        $permission = DB::table('permissions')->where('code', 'web.user.shopping-session')->first();
        if ($permission) {
            DB::table('role_permissions')->where('permission_id', $permission->id)->delete();
            DB::table('permissions')->where('id', $permission->id)->delete();
        }
    }
}
