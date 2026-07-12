<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SeedDemoRoleUsers extends Migration
{
    private $password = '12345678';

    private $users = [
        ['role' => 'user', 'name' => 'Usuario', 'lastname' => 'Demo', 'username' => 'demo_user', 'email' => 'usuario@cccontrol.test'],
        ['role' => 'dietologist', 'name' => 'Dietologo', 'lastname' => 'Demo', 'username' => 'demo_dietologist', 'email' => 'dietologo@cccontrol.test'],
        ['role' => 'catalog_admin', 'name' => 'Catalogo', 'lastname' => 'Admin', 'username' => 'demo_catalog_admin', 'email' => 'catalogo@cccontrol.test'],
        ['role' => 'supermarket_admin', 'name' => 'Supermercados', 'lastname' => 'Admin', 'username' => 'demo_supermarket_admin', 'email' => 'supermercados@cccontrol.test'],
        ['role' => 'recipe_admin', 'name' => 'Recetas', 'lastname' => 'Admin', 'username' => 'demo_recipe_admin', 'email' => 'recetas@cccontrol.test'],
        ['role' => 'teacher', 'name' => 'Docente', 'lastname' => 'Demo', 'username' => 'demo_teacher', 'email' => 'docente@cccontrol.test'],
        ['role' => 'super_admin', 'name' => 'Super', 'lastname' => 'Admin', 'username' => 'demo_super_admin', 'email' => 'superadmin@cccontrol.test'],
        ['role' => 'system_jobs', 'name' => 'Sistema', 'lastname' => 'Jobs', 'username' => 'demo_system_jobs', 'email' => 'sistema@cccontrol.test'],
    ];

    public function up()
    {
        $now = now();

        foreach ($this->users as $demoUser) {
            $roleId = DB::table('roles')->where('code', $demoUser['role'])->value('id');

            if (! $roleId) {
                continue;
            }

            DB::table('users')->updateOrInsert(
                ['email' => $demoUser['email']],
                [
                    'name' => $demoUser['name'],
                    'lastname' => $demoUser['lastname'],
                    'username' => $demoUser['username'],
                    'password' => Hash::make($this->password),
                    'status' => 'active',
                    'email_verified_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                    'deleted_at' => null,
                ]
            );

            $userId = DB::table('users')->where('email', $demoUser['email'])->value('id');

            DB::table('user_roles')->updateOrInsert(
                ['user_id' => $userId, 'role_id' => $roleId],
                ['created_at' => $now]
            );
        }
    }

    public function down()
    {
        $emails = array_map(function ($user) {
            return $user['email'];
        }, $this->users);

        $userIds = DB::table('users')->whereIn('email', $emails)->pluck('id');
        DB::table('user_roles')->whereIn('user_id', $userIds)->delete();
        DB::table('users')->whereIn('email', $emails)->delete();
    }
}
