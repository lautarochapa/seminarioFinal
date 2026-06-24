<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddRecipeSearchUserPermission extends Migration
{
    public function up()
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['code' => 'web.user.recipe-search'],
            [
                'code'        => 'web.user.recipe-search',
                'module'      => 'web.user',
                'action'      => 'access',
                'description' => 'Acceder a pantalla de búsqueda de recetas',
                'status'      => 'active',
            ]
        );

        $permId = DB::table('permissions')
            ->where('code', 'web.user.recipe-search')
            ->value('id');

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
        $permId = DB::table('permissions')
            ->where('code', 'web.user.recipe-search')
            ->value('id');

        if ($permId) {
            DB::table('role_permissions')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }
    }
}
