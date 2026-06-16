<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Permission;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RbacRolePermissionTest extends TestCase
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

    // ---- ASSIGN PERMISSION ----

    public function test_asignar_permiso_a_rol_exitoso()
    {
        $admin      = $this->createAdminUser();
        $role       = factory(Role::class)->create();
        $permission = factory(Permission::class)->create();

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/admin/roles/{$role->id}/permissions",
            ['permission_id' => $permission->id]
        );

        $response->assertStatus(201)
            ->assertJsonStructure(['data', 'trace_id']);

        $this->assertDatabaseHas('role_permissions', [
            'role_id'       => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    public function test_asignar_permiso_ya_asignado_retorna_conflicto()
    {
        $admin      = $this->createAdminUser();
        $role       = factory(Role::class)->create();
        $permission = factory(Permission::class)->create();

        DB::table('role_permissions')->insert([
            'role_id'       => $role->id,
            'permission_id' => $permission->id,
            'created_at'    => now(),
        ]);

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/admin/roles/{$role->id}/permissions",
            ['permission_id' => $permission->id]
        );

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'ROLE_PERMISSION_ALREADY_EXISTS');
    }

    public function test_asignar_permiso_rol_inexistente()
    {
        $admin      = $this->createAdminUser();
        $permission = factory(Permission::class)->create();

        $response = $this->actingAs($admin)->postJson(
            '/api/v1/admin/roles/99999/permissions',
            ['permission_id' => $permission->id]
        );

        $response->assertStatus(404);
    }

    public function test_asignar_permiso_inexistente()
    {
        $admin = $this->createAdminUser();
        $role  = factory(Role::class)->create();

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/admin/roles/{$role->id}/permissions",
            ['permission_id' => 99999]
        );

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_asignar_permiso_sin_autenticacion()
    {
        $role       = factory(Role::class)->create();
        $permission = factory(Permission::class)->create();

        $response = $this->postJson(
            "/api/v1/admin/roles/{$role->id}/permissions",
            ['permission_id' => $permission->id]
        );

        $response->assertStatus(401);
    }

    public function test_asignar_permiso_sin_permiso_de_escritura()
    {
        $user       = factory(User::class)->create();
        $role       = factory(Role::class)->create();
        $permission = factory(Permission::class)->create();

        $response = $this->actingAs($user)->postJson(
            "/api/v1/admin/roles/{$role->id}/permissions",
            ['permission_id' => $permission->id]
        );

        $response->assertStatus(403);
    }

    // ---- REMOVE PERMISSION ----

    public function test_remover_permiso_de_rol_exitoso()
    {
        $admin      = $this->createAdminUser();
        $role       = factory(Role::class)->create();
        $permission = factory(Permission::class)->create();

        DB::table('role_permissions')->insert([
            'role_id'       => $role->id,
            'permission_id' => $permission->id,
            'created_at'    => now(),
        ]);

        $response = $this->actingAs($admin)->deleteJson(
            "/api/v1/admin/roles/{$role->id}/permissions/{$permission->id}"
        );

        $response->assertStatus(204);

        $this->assertDatabaseMissing('role_permissions', [
            'role_id'       => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    public function test_remover_permiso_no_asignado_retorna_404()
    {
        $admin      = $this->createAdminUser();
        $role       = factory(Role::class)->create();
        $permission = factory(Permission::class)->create();

        $response = $this->actingAs($admin)->deleteJson(
            "/api/v1/admin/roles/{$role->id}/permissions/{$permission->id}"
        );

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'ROLE_PERMISSION_NOT_FOUND');
    }

    public function test_remover_permiso_rol_inexistente()
    {
        $admin      = $this->createAdminUser();
        $permission = factory(Permission::class)->create();

        $response = $this->actingAs($admin)->deleteJson(
            "/api/v1/admin/roles/99999/permissions/{$permission->id}"
        );

        $response->assertStatus(404);
    }
}
