<?php

namespace Tests\Feature\Api\V1\Admin;

use App\LoginLog;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLoginLogTest extends TestCase
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
        return LoginLog::create(array_merge([
            'user_id'        => null,
            'email'          => 'test@example.com',
            'success'        => true,
            'ip_address'     => '127.0.0.1',
            'user_agent'     => 'phpunit',
            'failure_reason' => null,
        ], $attrs));
    }

    // ---- AUTH / PERMISOS ----

    public function test_sin_autenticacion()
    {
        $response = $this->getJson('/api/v1/admin/login-logs');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_UNAUTHENTICATED');
    }

    public function test_sin_permiso()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/admin/login-logs');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');
    }

    // ---- LISTADO ----

    public function test_listado_exitoso()
    {
        $admin = $this->createAdminUser();
        $this->makeLog(['user_id' => $admin->id, 'email' => $admin->email]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/login-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [[
                    'id', 'user', 'email', 'success',
                    'ip', 'user_agent', 'failure_reason', 'created_at',
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

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/login-logs?per_page=2&page=1');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('meta.per_page'));
        $this->assertLessThanOrEqual(2, count($response->json('data')));
    }

    // ---- FILTROS ----

    public function test_busqueda_por_email()
    {
        $admin = $this->createAdminUser();
        $this->makeLog(['email' => 'buscable@example.com']);
        $this->makeLog(['email' => 'otro@example.com']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/login-logs?search=buscable');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($data));
        foreach ($data as $log) {
            $this->assertStringContainsString('buscable', strtolower($log['email']));
        }
    }

    public function test_filtro_user_id()
    {
        $admin = $this->createAdminUser();
        $other = factory(User::class)->create();
        $this->makeLog(['user_id' => $admin->id, 'email' => $admin->email]);
        $this->makeLog(['user_id' => $other->id, 'email' => $other->email]);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/login-logs?user_id={$admin->id}");

        $response->assertStatus(200);
        foreach ($response->json('data') as $log) {
            $this->assertEquals($admin->id, $log['user']['id'] ?? null);
        }
    }

    public function test_filtro_email()
    {
        $admin = $this->createAdminUser();
        $this->makeLog(['email' => 'exacto@example.com']);
        $this->makeLog(['email' => 'otro@example.com']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/login-logs?email=exacto@example.com');

        $response->assertStatus(200);
        foreach ($response->json('data') as $log) {
            $this->assertEquals('exacto@example.com', $log['email']);
        }
    }

    public function test_filtro_success_true()
    {
        $admin = $this->createAdminUser();
        $this->makeLog(['success' => true]);
        $this->makeLog(['success' => false]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/login-logs?success=1');

        $response->assertStatus(200);
        foreach ($response->json('data') as $log) {
            $this->assertTrue($log['success']);
        }
    }

    public function test_filtro_success_false()
    {
        $admin = $this->createAdminUser();
        $this->makeLog(['success' => true]);
        $this->makeLog(['success' => false, 'failure_reason' => 'invalid_password']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/login-logs?success=0');

        $response->assertStatus(200);
        foreach ($response->json('data') as $log) {
            $this->assertFalse($log['success']);
        }
    }

    public function test_filtro_ip()
    {
        $admin = $this->createAdminUser();
        $this->makeLog(['ip_address' => '192.168.1.1']);
        $this->makeLog(['ip_address' => '10.0.0.1']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/login-logs?ip=192.168');

        $response->assertStatus(200);
        foreach ($response->json('data') as $log) {
            $this->assertStringContainsString('192.168', $log['ip']);
        }
    }

    public function test_filtro_date_from()
    {
        $admin = $this->createAdminUser();
        DB::table('login_logs')->insert([
            'email'      => 'old@example.com',
            'success'    => true,
            'created_at' => now()->subDays(10),
        ]);
        $this->makeLog(['email' => 'recent@example.com']);

        $from     = now()->subDay()->toDateString();
        $response = $this->actingAs($admin)->getJson("/api/v1/admin/login-logs?date_from={$from}");

        $response->assertStatus(200);
        foreach ($response->json('data') as $log) {
            $this->assertNotEquals('old@example.com', $log['email']);
        }
    }

    public function test_filtro_date_to()
    {
        $admin = $this->createAdminUser();
        $this->makeLog(['email' => 'current@example.com']);
        DB::table('login_logs')->insert([
            'email'      => 'future@example.com',
            'success'    => true,
            'created_at' => now()->addDays(5),
        ]);

        $to       = now()->addDay()->toDateString();
        $response = $this->actingAs($admin)->getJson("/api/v1/admin/login-logs?date_to={$to}");

        $response->assertStatus(200);
        foreach ($response->json('data') as $log) {
            $this->assertNotEquals('future@example.com', $log['email']);
        }
    }

    // ---- ESTADOS ----

    public function test_login_exitoso_success_true()
    {
        $admin = $this->createAdminUser();
        $this->makeLog(['success' => true]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/login-logs');

        $response->assertStatus(200);
        $log = collect($response->json('data'))->first(function ($l) {
            return $l['success'] === true;
        });
        $this->assertNotNull($log);
        $this->assertNull($log['failure_reason']);
    }

    public function test_login_fallido_success_false()
    {
        $admin = $this->createAdminUser();
        $this->makeLog(['success' => false, 'failure_reason' => 'invalid_password']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/login-logs');

        $response->assertStatus(200);
        $log = collect($response->json('data'))->first(function ($l) {
            return $l['success'] === false;
        });
        $this->assertNotNull($log);
        $this->assertEquals('invalid_password', $log['failure_reason']);
    }

    public function test_usuario_null_cuando_user_id_es_null()
    {
        $admin = $this->createAdminUser();
        $this->makeLog(['user_id' => null, 'email' => 'nouser@example.com']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/login-logs');

        $response->assertStatus(200);
        $log = collect($response->json('data'))->first(function ($l) {
            return $l['email'] === 'nouser@example.com';
        });
        $this->assertNotNull($log);
        $this->assertNull($log['user']);
    }

    public function test_no_expone_campos_sensibles()
    {
        $admin = $this->createAdminUser();
        $this->makeLog();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/login-logs');

        $content = $response->getContent();
        $this->assertStringNotContainsString('access_token', $content);
        $this->assertStringNotContainsString('refresh_token', $content);
        $this->assertStringNotContainsString('password', $content);
    }

    // ---- TRACE ID ----

    public function test_trace_id_presente()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/login-logs');

        $traceId = $response->json('trace_id');
        $this->assertNotNull($traceId);
        $this->assertRegExp(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $traceId
        );
    }
}
