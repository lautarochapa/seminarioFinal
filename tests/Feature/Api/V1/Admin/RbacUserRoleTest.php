<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Permission;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RbacUserRoleTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser()
    {
        $user = factory(User::class)->create();
        $role = Role::where('code', 'super_admin')->first();
        if ($role) {
            DB::table('user_roles')->insert([
                'user_id'    => $user->id,
                'role_id'    => $role->id,
                'created_at' => now(),
            ]);
        }
        return $user;
    }

    // ---- ASSIGN ROLE ----

    public function test_asignar_rol_a_usuario_exitoso()
    {
        $admin  = $this->createAdminUser();
        $target = factory(User::class)->create();
        $role   = factory(Role::class)->create();

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/admin/users/{$target->id}/roles",
            ['role_id' => $role->id]
        );

        $response->assertStatus(201)
            ->assertJsonStructure(['data', 'trace_id']);

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $target->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_asignar_rol_ya_asignado_retorna_conflicto()
    {
        $admin  = $this->createAdminUser();
        $target = factory(User::class)->create();
        $role   = factory(Role::class)->create();

        DB::table('user_roles')->insert([
            'user_id'    => $target->id,
            'role_id'    => $role->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/admin/users/{$target->id}/roles",
            ['role_id' => $role->id]
        );

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'USER_ROLE_ALREADY_EXISTS');
    }

    public function test_asignar_rol_usuario_inexistente()
    {
        $admin = $this->createAdminUser();
        $role  = factory(Role::class)->create();

        $response = $this->actingAs($admin)->postJson(
            '/api/v1/admin/users/99999/roles',
            ['role_id' => $role->id]
        );

        $response->assertStatus(404);
    }

    public function test_asignar_rol_inexistente()
    {
        $admin  = $this->createAdminUser();
        $target = factory(User::class)->create();

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/admin/users/{$target->id}/roles",
            ['role_id' => 99999]
        );

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_asignar_rol_sin_autenticacion()
    {
        $target = factory(User::class)->create();
        $role   = factory(Role::class)->create();

        $response = $this->postJson(
            "/api/v1/admin/users/{$target->id}/roles",
            ['role_id' => $role->id]
        );

        $response->assertStatus(401);
    }

    public function test_asignar_rol_sin_permiso()
    {
        $user   = factory(User::class)->create();
        $target = factory(User::class)->create();
        $role   = factory(Role::class)->create();

        $response = $this->actingAs($user)->postJson(
            "/api/v1/admin/users/{$target->id}/roles",
            ['role_id' => $role->id]
        );

        $response->assertStatus(403);
    }

    // ---- REMOVE ROLE ----

    public function test_remover_rol_de_usuario_exitoso()
    {
        $admin  = $this->createAdminUser();
        $target = factory(User::class)->create();
        $role   = factory(Role::class)->create();

        DB::table('user_roles')->insert([
            'user_id'    => $target->id,
            'role_id'    => $role->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->deleteJson(
            "/api/v1/admin/users/{$target->id}/roles/{$role->id}"
        );

        $response->assertStatus(204);

        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $target->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_remover_rol_no_asignado_retorna_404()
    {
        $admin  = $this->createAdminUser();
        $target = factory(User::class)->create();
        $role   = factory(Role::class)->create();

        $response = $this->actingAs($admin)->deleteJson(
            "/api/v1/admin/users/{$target->id}/roles/{$role->id}"
        );

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'USER_ROLE_NOT_FOUND');
    }

    public function test_remover_rol_usuario_inexistente()
    {
        $admin = $this->createAdminUser();
        $role  = factory(Role::class)->create();

        $response = $this->actingAs($admin)->deleteJson(
            "/api/v1/admin/users/99999/roles/{$role->id}"
        );

        $response->assertStatus(404);
    }
}