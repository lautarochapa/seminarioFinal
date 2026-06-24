<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddAiFoundationAdminPermission extends Migration
{
    public function up()
    {
        $permId = DB::table('permissions')->insertGetId([
            'code'        => 'web.admin.ai-foundation',
            'description' => 'Acceso al panel de arquitectura IA',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $roles = DB::table('roles')->whereIn('code', ['super_admin'])->pluck('id');
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
        $perm = DB::table('permissions')->where('code', 'web.admin.ai-foundation')->first();
        if ($perm) {
            DB::table('role_permissions')->where('permission_id', $perm->id)->delete();
            DB::table('permissions')->where('id', $perm->id)->delete();
        }
    }
}
