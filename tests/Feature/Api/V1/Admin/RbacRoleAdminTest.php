<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RbacRoleAdminTest extends TestCase
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

    // ---- LIST ----

    public function test_listar_roles_exitoso()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'code', 'name', 'status']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links',
                'trace_id',
            ]);
    }

    public function test_listar_roles_sin_autenticacion()
    {
        $response = $this->getJson('/api/v1/admin/roles');

        $response->assertStatus(401);
    }

    public function test_listar_roles_sin_permiso()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/admin/roles');

        $response->assertStatus(403);
    }

    public function test_listar_roles_filtro_status()
    {
        $admin = $this->createAdminUser();
        factory(Role::class)->create(['status' => 'inactive']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/roles?status=inactive');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $role) {
            $this->assertEquals('inactive', $role['status']);
        }
    }

    // ---- SHOW ----

    public function test_ver_rol_exitoso()
    {
        $admin = $this->createAdminUser();
        $role  = factory(Role::class)->create();

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $role->id)
            ->assertJsonPath('data.code', $role->code);
    }

    public function test_ver_rol_inexistente()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/roles/99999');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    // ---- CREATE ----

    public function test_crear_rol_exitoso()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
            'code'        => 'nuevo_rol',
            'name'        => 'Nuevo Rol',
            'description' => 'Un rol de prueba',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'nuevo_rol')
            ->assertJsonStructure(['data' => ['id', 'code', 'name', 'status'], 'trace_id']);

        $this->assertDatabaseHas('roles', ['code' => 'nuevo_rol', 'status' => 'active']);
    }

    public function test_crear_rol_codigo_duplicado()
    {
        $admin = $this->createAdminUser();
        factory(Role::class)->create(['code' => 'duplicado']);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
            'code' => 'duplicado',
            'name' => 'Dup',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'ROLE_CODE_ALREADY_EXISTS');
    }

    public function test_crear_rol_validacion_falla()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/roles', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    // ---- UPDATE ----

    public function test_actualizar_rol_exitoso()
    {
        $admin = $this->createAdminUser();
        $role  = factory(Role::class)->create(['name' => 'Original']);

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/roles/{$role->id}", [
            'name' => 'Actualizado',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Actualizado');
    }

    public function test_actualizar_rol_inexistente()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->patchJson('/api/v1/admin/roles/99999', [
            'name' => 'X',
        ]);

        $response->assertStatus(404);
    }

    // ---- DELETE (status=inactive) ----

    public function test_eliminar_rol_logicamente()
    {
        $admin = $this->createAdminUser();
        $role  = factory(Role::class)->create(['status' => 'active']);

        $response = $this->actingAs($admin)->deleteJson("/api/v1/admin/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'status' => 'inactive']);
    }

    public function test_eliminar_rol_ya_inactivo()
    {
        $admin = $this->createAdminUser();
        $role  = factory(Role::class)->create(['status' => 'inactive']);

        $response = $this->actingAs($admin)->deleteJson("/api/v1/admin/roles/{$role->id}");

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'RESOURCE_ALREADY_DELETED');
    }

    public function test_eliminar_rol_inexistente()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->deleteJson('/api/v1/admin/roles/99999');

        $response->assertStatus(404);
    }

    // ---- RESTORE ----

    public function test_restaurar_rol_exitoso()
    {
        $admin = $this->createAdminUser();
        $role  = factory(Role::class)->create(['status' => 'inactive']);

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/roles/{$role->id}/restore");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'status' => 'active']);
    }

    public function test_restaurar_rol_ya_activo()
    {
        $admin = $this->createAdminUser();
        $role  = factory(Role::class)->create(['status' => 'active']);

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/roles/{$role->id}/restore");

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'RESOURCE_NOT_DELETED');
    }

    // ---- AUDIT ----

    public function test_audit_rol_retorna_lista()
    {
        $admin = $this->createAdminUser();
        $role  = factory(Role::class)->create();

        \App\AuditLog::create([
            'user_id'     => $admin->id,
            'action'      => 'role.admin.create',
            'entity_name' => 'roles',
            'entity_id'   => $role->id,
            'old_values'  => null,
            'new_values'  => ['code' => $role->code],
            'ip_address'  => '127.0.0.1',
            'user_agent'  => 'test',
        ]);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/roles/{$role->id}/audit");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'action', 'entity_name', 'entity_id', 'created_at']],
                'meta',
                'trace_id',
            ]);
    }
}