<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddProductReportsAdminPermission extends Migration
{
    public function up()
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['code' => 'web.admin.product-reports'],
            [
                'code'        => 'web.admin.product-reports',
                'module'      => 'web.admin',
                'action'      => 'access',
                'description' => 'Acceder a pantalla admin: product-reports',
                'status'      => 'active',
            ]
        );

        $permId = DB::table('permissions')
            ->where('code', 'web.admin.product-reports')
            ->value('id');

        if (! $permId) {
            return;
        }

        $superAdminId = DB::table('roles')->where('code', 'super_admin')->value('id');

        if ($superAdminId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $superAdminId, 'permission_id' => $permId],
                ['created_at' => $now]
            );
        }
    }

    public function down()
    {
        $permId = DB::table('permissions')
            ->where('code', 'web.admin.product-reports')
            ->value('id');

        if ($permId) {
            DB::table('role_permissions')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }
    }
}
