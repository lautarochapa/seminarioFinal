<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddPurchasesUserPermission extends Migration
{
    public function up()
    {
        DB::table('permissions')->updateOrInsert(
            ['code' => 'web.user.purchases'],
            ['code' => 'web.user.purchases', 'description' => 'Pantalla ítems de compra real']
        );

        $permission = DB::table('permissions')->where('code', 'web.user.purchases')->first();

        foreach (['user', 'super_admin'] as $roleCode) {
            $role = DB::table('roles')->where('code', $roleCode)->first();
            if ($role && $permission) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $role->id, 'permission_id' => $permission->id],
                    ['role_id' => $role->id, 'permission_id' => $permission->id]
                );
            }
        }
    }

    public function down()
    {
        $permission = DB::table('permissions')->where('code', 'web.user.purchases')->first();
        if ($permission) {
            DB::table('role_permissions')->where('permission_id', $permission->id)->delete();
            DB::table('permissions')->where('id', $permission->id)->delete();
        }
    }
}
