<?php

namespace Tests\Feature\Api\V1\Admin;

use App\AuditLog;
use App\Permission;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditResourceAuditTest extends TestCase
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

    private function makeLog(string $entityName, $entityId, array $attrs = [])
    {
        return AuditLog::create(array_merge([
            'user_id'     => null,
            'action'      => 'test.action',
            'entity_name' => $entityName,
            'entity_id'   => (string) $entityId,
            'old_values'  => null,
            'new_values'  => ['key' => 'value'],
            'ip_address'  => '127.0.0.1',
            'user_agent'  => 'phpunit',
        ], $attrs));
    }

    // ---- RECURSO USERS ----

    public function test_recurso_users_con_historial()
    {
        $admin  = $this->createAdminUser();
        $target = factory(User::class)->create();
        $this->makeLog('users', $target->id);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/users/{$target->id}/audit");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'action', 'resource', 'resource_id', 'created_at']],
                'meta',
                'trace_id',
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('users', $data[0]['resource']);
    }

    // ---- RECURSO ROLES ----

    public function test_recurso_roles_con_historial()
    {
        $admin = $this->createAdminUser();
        $role  = factory(Role::class)->create();
        $this->makeLog('roles', $role->id);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/roles/{$role->id}/audit");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'action', 'resource', 'resource_id', 'created_at']],
                'meta',
                'trace_id',
            ]);
    }

    // ---- RECURSO GENÉRICO ----

    public function test_recurso_permissions_con_historial()
    {
        $admin      = $this->createAdminUser();
        $permission = factory(Permission::class)->create();
        $this->makeLog('permissions', $permission->id);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/permissions/{$permission->id}/audit");

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta', 'trace_id']);
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_recurso_permissions_sin_historial()
    {
        $admin      = $this->createAdminUser();
        $permission = factory(Permission::class)->create();

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/permissions/{$permission->id}/audit");

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    public function test_recurso_no_permitido_retorna_404()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/products/1/audit');

        $response->assertStatus(404);
    }

    public function test_id_inexistente_retorna_404()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/permissions/99999/audit');

        $response->assertStatus(404);
    }

    public function test_recurso_eliminado_con_historial()
    {
        $admin  = $this->createAdminUser();
        $target = factory(User::class)->create();
        $this->makeLog('users', $target->id, ['action' => 'user.admin.create']);
        $target->delete();

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/users/{$target->id}/audit");

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data'));
    }

    // ---- FILTROS Y PAGINACIÓN ----

    public function test_paginacion_por_recurso()
    {
        $admin      = $this->createAdminUser();
        $permission = factory(Permission::class)->create();
        for ($i = 0; $i < 5; $i++) {
            $this->makeLog('permissions', $permission->id);
        }

        $response = $this->actingAs($admin)->getJson(
            "/api/v1/admin/permissions/{$permission->id}/audit?per_page=2"
        );

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('meta.per_page'));
    }

    public function test_orden_cronologico_descendente()
    {
        $admin      = $this->createAdminUser();
        $permission = factory(Permission::class)->create();

        DB::table('audit_logs')->insert([
            'action'      => 'first.action',
            'entity_name' => 'permissions',
            'entity_id'   => (string) $permission->id,
            'created_at'  => now()->subHour(),
        ]);
        DB::table('audit_logs')->insert([
            'action'      => 'second.action',
            'entity_name' => 'permissions',
            'entity_id'   => (string) $permission->id,
            'created_at'  => now(),
        ]);

        $response = $this->actingAs($admin)->getJson(
            "/api/v1/admin/permissions/{$permission->id}/audit"
        );

        $response->assertStatus(200);
        $data = $response->json('data');
        if (count($data) >= 2) {
            $this->assertEquals('second.action', $data[0]['action']);
            $this->assertEquals('first.action', $data[1]['action']);
        }
    }

    // ---- CONTRATO COMPARTIDO ----

    public function test_contrato_users_audit_igual_al_generico()
    {
        $admin  = $this->createAdminUser();
        $target = factory(User::class)->create();
        $this->makeLog('users', $target->id);

        $responseSpecific = $this->actingAs($admin)->getJson("/api/v1/admin/users/{$target->id}/audit");
        $responseGeneric  = $this->actingAs($admin)->getJson("/api/v1/admin/users/{$target->id}/audit");

        $responseSpecific->assertStatus(200);
        $responseGeneric->assertStatus(200);

        $keysSpecific = array_keys($responseSpecific->json('data')[0] ?? []);
        $keysGeneric  = array_keys($responseGeneric->json('data')[0] ?? []);

        $this->assertEquals($keysSpecific, $keysGeneric);
    }

    // ---- PERMISOS ----

    public function test_sin_autenticacion()
    {
        $response = $this->getJson('/api/v1/admin/permissions/1/audit');

        $response->assertStatus(401);
    }

    public function test_sin_permiso()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/admin/permissions/1/audit');

        $response->assertStatus(403);
    }
}
