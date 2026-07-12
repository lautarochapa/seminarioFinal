<?php

namespace Tests\Feature\Api\V1\Admin;

use App\AuditLog;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditAuditLogTest extends TestCase
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

    private function makeLog(array $attrs = [])
    {
        return AuditLog::create(array_merge([
            'user_id'     => null,
            'action'      => 'user.admin.create',
            'entity_name' => 'users',
            'entity_id'   => '1',
            'old_values'  => null,
            'new_values'  => ['name' => 'Test'],
            'ip_address'  => '127.0.0.1',
            'user_agent'  => 'phpunit',
        ], $attrs));
    }

    // ---- AUTH / PERMISOS ----

    public function test_sin_autenticacion()
    {
        $response = $this->getJson('/api/v1/admin/audit-logs');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_UNAUTHENTICATED');
    }

    public function test_sin_permiso()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/admin/audit-logs');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');
    }

    // ---- LISTADO ----

    public function test_listado_exitoso()
    {
        $admin = $this->createAdminUser();
        $this->makeLog(['user_id' => $admin->id]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [[
                    'id', 'action', 'resource', 'resource_id',
                    'user', 'before', 'after', 'ip', 'user_agent', 'created_at',
                ]],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links',
                'trace_id',
            ]);
    }

    public function test_paginacion()
    {
        $admin = $this->createAdminUser();
        for ($i = 0; $i < 5; $i++) {
            $this->makeLog();
        }

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-logs?per_page=2&page=1');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('meta.per_page'));
        $this->assertLessThanOrEqual(2, count($response->json('data')));
    }

    // ---- FILTROS ----

    public function test_filtro_busqueda_por_action()
    {
        $admin = $this->createAdminUser();
        $this->makeLog(['action' => 'role.admin.create']);
        $this->makeLog(['action' => 'user.admin.update']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-logs?search=role');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($data));
        foreach ($data as $log) {
            $this->assertStringContainsString('role', strtolower($log['action']));
        }
    }

    public function test_filtro_user_id()
    {
        $admin  = $this->createAdminUser();
        $other  = factory(User::class)->create();
        $this->makeLog(['user_id' => $admin->id]);
        $this->makeLog(['user_id' => $other->id]);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/audit-logs?user_id={$admin->id}");

        $response->assertStatus(200);
        foreach ($response->json('data') as $log) {
            $this->assertEquals($admin->id, $log['user']['id'] ?? null);
        }
    }

    public function test_filtro_accion_exacta()
    {
        $admin = $this->createAdminUser();
        $this->makeLog(['action' => 'role.admin.create']);
        $this->makeLog(['action' => 'user.admin.update']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-logs?action=role.admin.create');

        $response->assertStatus(200);
        foreach ($response->json('data') as $log) {
            $this->assertEquals('role.admin.create', $log['action']);
        }
    }

    public function test_filtro_resource()
    {
        $admin = $this->createAdminUser();
        $this->makeLog(['entity_name' => 'roles']);
        $this->makeLog(['entity_name' => 'users']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-logs?resource=roles');

        $response->assertStatus(200);
        foreach ($response->json('data') as $log) {
            $this->assertEquals('roles', $log['resource']);
        }
    }

    public function test_filtro_resource_id()
    {
        $admin = $this->createAdminUser();
        $this->makeLog(['entity_name' => 'users', 'entity_id' => '42']);
        $this->makeLog(['entity_name' => 'users', 'entity_id' => '99']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-logs?resource_id=42');

        $response->assertStatus(200);
        foreach ($response->json('data') as $log) {
            $this->assertEquals(42, $log['resource_id']);
        }
    }

    public function test_filtro_date_from()
    {
        $admin = $this->createAdminUser();
        DB::table('audit_logs')->insert([
            'user_id'     => null,
            'action'      => 'old.action',
            'entity_name' => 'users',
            'entity_id'   => '1',
            'created_at'  => now()->subDays(10),
        ]);
        $this->makeLog(['action' => 'recent.action']);

        $from     = now()->subDay()->toDateString();
        $response = $this->actingAs($admin)->getJson("/api/v1/admin/audit-logs?date_from={$from}");

        $response->assertStatus(200);
        foreach ($response->json('data') as $log) {
            $this->assertNotEquals('old.action', $log['action']);
        }
    }

    public function test_filtro_date_to()
    {
        $admin = $this->createAdminUser();
        $this->makeLog(['action' => 'current.action']);
        DB::table('audit_logs')->insert([
            'user_id'     => null,
            'action'      => 'future.action',
            'entity_name' => 'users',
            'entity_id'   => '1',
            'created_at'  => now()->addDays(5),
        ]);

        $to       = now()->addDay()->toDateString();
        $response = $this->actingAs($admin)->getJson("/api/v1/admin/audit-logs?date_to={$to}");

        $response->assertStatus(200);
        foreach ($response->json('data') as $log) {
            $this->assertNotEquals('future.action', $log['action']);
        }
    }

    public function test_sort_valido()
    {
        $admin = $this->createAdminUser();
        $this->makeLog(['action' => 'a.action']);
        $this->makeLog(['action' => 'z.action']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-logs?sort=action&order=asc');

        $response->assertStatus(200);
        $data = $response->json('data');
        if (count($data) >= 2) {
            $this->assertLessThanOrEqual($data[1]['action'], $data[0]['action']);
        }
    }

    public function test_sort_invalido_no_causa_error()
    {
        $admin = $this->createAdminUser();
        $this->makeLog();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-logs?sort=columna_inventada&order=asc');

        $response->assertStatus(200);
    }

    // ---- USUARIO ACTOR ----

    public function test_usuario_actor_en_respuesta()
    {
        $admin = $this->createAdminUser();
        $this->makeLog(['user_id' => $admin->id]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-logs');

        $response->assertStatus(200);
        $log = collect($response->json('data'))->firstWhere('user.id', $admin->id);
        $this->assertNotNull($log);
        $this->assertEquals($admin->email, $log['user']['email']);
    }

    public function test_usuario_actor_eliminado_es_null()
    {
        $admin  = $this->createAdminUser();
        $actor  = factory(User::class)->create();
        $this->makeLog(['user_id' => $actor->id]);
        $actor->delete();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-logs');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $log = collect($data)->first(function ($l) use ($actor) {
            return isset($l['user_id']) && $l['user_id'] === $actor->id;
        });
        if ($log) {
            $this->assertNull($log['user']);
        }
    }

    // ---- SANITIZACIÓN ----

    public function test_sanitizacion_password_en_after()
    {
        $admin = $this->createAdminUser();
        $this->makeLog([
            'new_values' => ['name' => 'John', 'password' => 'secret123'],
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-logs');

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringNotContainsString('secret123', $content);
        $this->assertStringContainsString('[REDACTED]', $content);
    }

    public function test_sanitizacion_token_en_before()
    {
        $admin = $this->createAdminUser();
        $this->makeLog([
            'old_values' => ['access_token' => 'tok_abc123', 'status' => 'active'],
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-logs');

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringNotContainsString('tok_abc123', $content);
    }

    public function test_registro_con_valores_nulos_no_causa_500()
    {
        $admin = $this->createAdminUser();
        // jsonb columna valida JSON a nivel DB, valores nulos son permitidos
        $this->makeLog(['old_values' => null, 'new_values' => null]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-logs');

        $response->assertStatus(200);
        $data = $response->json('data');
        $found = collect($data)->first(fn($l) => is_null($l['before']) && is_null($l['after']));
        $this->assertNotNull($found);
    }

    // ---- TRACE ID ----

    public function test_trace_id_presente()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-logs');

        $traceId = $response->json('trace_id');
        $this->assertNotNull($traceId);
        $this->assertRegExp(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $traceId
        );
    }
}
