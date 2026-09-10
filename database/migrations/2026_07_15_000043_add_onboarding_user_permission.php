<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddOnboardingUserPermission extends Migration
{
    private $permission = [
        'code'        => 'web.user.onboarding',
        'module'      => 'web.user',
        'action'      => 'access',
        'description' => 'Acceder a pantalla de usuario: onboarding',
        'status'      => 'active',
    ];

    public function up()
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['code' => $this->permission['code']],
            $this->permission
        );

        $permId  = DB::table('permissions')->where('code', $this->permission['code'])->value('id');
        $roleIds = DB::table('roles')->whereIn('code', ['user', 'super_admin'])->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permId],
                ['created_at' => $now]
            );
        }
    }

    public function down()
    {
    }
}
