<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddSupermarketsUserPermission extends Migration
{
    public function up()
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['code' => 'web.user.supermarkets'],
            [
                'code'        => 'web.user.supermarkets',
                'module'      => 'web.user',
                'action'      => 'access',
                'description' => 'Acceder a pantalla de usuario: supermarkets',
                'status'      => 'active',
            ]
        );

        $permId = DB::table('permissions')
            ->where('code', 'web.user.supermarkets')
            ->value('id');

        if (! $permId) {
            return;
        }

        $roleCodes = ['user', 'super_admin'];

        foreach ($roleCodes as $code) {
            $roleId = DB::table('roles')->where('code', $code)->value('id');
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
        $permId = DB::table('permissions')
            ->where('code', 'web.user.supermarkets')
            ->value('id');

        if ($permId) {
            DB::table('role_permissions')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }
    }
}
