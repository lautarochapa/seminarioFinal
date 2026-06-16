<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RbacUserAdminTest extends TestCase
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

    private function createUserWithRole($roleCode)
    {
        $user = factory(User::class)->create();
        $role = Role::where('code', $roleCode)->first();
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

    public function test_listar_usuarios_exitoso()
    {
        $admin = $this->createAdminUser();
        factory(User::class, 3)->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email', 'status']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links',
                'trace_id',
            ]);
    }

    public function test_listar_usuarios_sin_autenticacion()
    {
        $response = $this->getJson('/api/v1/admin/users');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_UNAUTHENTICATED');
    }

    public function test_listar_usuarios_sin_permiso()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/admin/users');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');
    }

    public function test_listar_usuarios_paginacion()
    {
        $admin = $this->createAdminUser();
        factory(User::class, 5)->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users?per_page=2&page=1');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('meta.per_page'));
        $this->assertEquals(1, $response->json('meta.current_page'));
    }

    public function test_listar_usuarios_filtro_status()
    {
        $admin = $this->createAdminUser();
        factory(User::class, 2)->create(['status' => 'active']);
        factory(User::class, 2)->create(['status' => 'inactive']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users?status=inactive');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $u) {
            $this->assertEquals('inactive', $u['status']);
        }
    }

    public function test_listar_usuarios_filtro_search()
    {
        $admin = $this->createAdminUser();
        factory(User::class)->create(['name' => 'Buscable Test', 'email' => 'buscable@test.com']);
        factory(User::class)->create(['name' => 'Otro Usuario', 'email' => 'otro@test.com']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users?search=buscable');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($data));
    }

    // ---- SHOW ----

    public function test_ver_usuario_exitoso()
    {
        $admin  = $this->createAdminUser();
        $target = factory(User::class)->create();

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/users/{$target->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath('data.email', $target->email)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'status'], 'trace_id']);
    }

    public function test_ver_usuario_inexistente()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users/99999');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    // ---- CREATE ----

    public function test_crear_usuario_exitoso()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/users', [
            'name'                  => 'Nuevo Admin',
            'email'                 => 'nuevo@admin.com',
            'password'              => 'secreto123',
            'password_confirmation' => 'secreto123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'nuevo@admin.com')
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'status'], 'trace_id']);

        $this->assertDatabaseHas('users', ['email' => 'nuevo@admin.com']);
    }

    public function test_crear_usuario_email_duplicado()
    {
        $admin = $this->createAdminUser();
        factory(User::class)->create(['email' => 'duplicado@example.com']);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/users', [
            'name'                  => 'Dup',
            'email'                 => 'duplicado@example.com',
            'password'              => 'secreto123',
            'password_confirmation' => 'secreto123',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'USER_EMAIL_ALREADY_EXISTS');
    }

    public function test_crear_usuario_validacion_falla()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/users', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    // ---- UPDATE ----

    public function test_actualizar_usuario_exitoso()
    {
        $admin  = $this->createAdminUser();
        $target = factory(User::class)->create(['name' => 'Original']);

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$target->id}", [
            'name' => 'Actualizado',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Actualizado');
    }

    public function test_actualizar_usuario_inexistente()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->patchJson('/api/v1/admin/users/99999', [
            'name' => 'X',
        ]);

        $response->assertStatus(404);
    }

    // ---- DELETE (soft) ----

    public function test_eliminar_usuario_soft_delete()
    {
        $admin  = $this->createAdminUser();
        $target = factory(User::class)->create();

        $response = $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$target->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_eliminar_usuario_ya_eliminado()
    {
        $admin  = $this->createAdminUser();
        $target = factory(User::class)->create();
        $target->delete();

        $response = $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$target->id}");

        $response->assertStatus(404);
    }

    // ---- RESTORE ----

    public function test_restaurar_usuario_exitoso()
    {
        $admin  = $this->createAdminUser();
        $target = factory(User::class)->create();
        $target->delete();

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$target->id}/restore");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $target->id);

        $this->assertDatabaseHas('users', ['id' => $target->id, 'deleted_at' => null]);
    }

    public function test_restaurar_usuario_no_eliminado()
    {
        $admin  = $this->createAdminUser();
        $target = factory(User::class)->create();

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$target->id}/restore");

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'RESOURCE_NOT_DELETED');
    }

    // ---- AUDIT ----

    public function test_audit_usuario_retorna_lista()
    {
        $admin  = $this->createAdminUser();
        $target = factory(User::class)->create();

        \App\AuditLog::create([
            'user_id'     => $admin->id,
            'action'      => 'user.admin.create',
            'entity_name' => 'users',
            'entity_id'   => $target->id,
            'old_values'  => null,
            'new_values'  => ['name' => $target->name],
            'ip_address'  => '127.0.0.1',
            'user_agent'  => 'test',
        ]);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/users/{$target->id}/audit");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'action', 'resource', 'resource_id', 'created_at']],
                'meta',
                'trace_id',
            ]);
    }

    // ---- TRACE ID ----

    public function test_trace_id_presente_en_respuesta()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users');

        $traceId = $response->json('trace_id');
        $this->assertNotNull($traceId);
        $this->assertRegExp(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $traceId
        );
    }
}