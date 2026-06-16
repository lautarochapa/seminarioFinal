<?php

namespace Tests\Feature\Api\V1\UserProfile;

use App\AuditLog;
use App\User;
use App\UserPrioritySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPrioritySettingTest extends TestCase
{
    use RefreshDatabase;

    // ---- AUTH ----

    public function test_acceso_sin_autenticacion()
    {
        $response = $this->getJson('/api/v1/users/me/priority-settings');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_UNAUTHENTICATED');
    }

    // ---- GET ----

    public function test_obtener_configuracion_propia()
    {
        $user = factory(User::class)->create();
        factory(UserPrioritySetting::class)->create([
            'user_id'       => $user->id,
            'health_weight' => 3.5,
            'budget_weight' => 2.0,
            'time_weight'   => 1.0,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/users/me/priority-settings');

        $response->assertStatus(200)
            ->assertJsonPath('data.health_weight', 3.5)
            ->assertJsonStructure(['data' => ['health_weight', 'budget_weight', 'time_weight', 'stock_usage_weight'], 'trace_id']);
        // assertEquals (loose) because json_encode(2.0) = "2" in PHP 7.4
        $this->assertEquals(2.0, $response->json('data.budget_weight'));
        $this->assertEquals(1.0, $response->json('data.time_weight'));
    }

    public function test_estructura_estable_sin_configuracion()
    {
        $user = factory(User::class)->create();
        // No UserPrioritySetting record

        $response = $this->actingAs($user)->getJson('/api/v1/users/me/priority-settings');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['health_weight', 'budget_weight', 'time_weight', 'stock_usage_weight']]);
        // Defaults should be zeros
        $this->assertEquals(0, $response->json('data.health_weight'));
        $this->assertEquals(0, $response->json('data.budget_weight'));
    }

    // ---- PATCH ----

    public function test_actualizacion_parcial()
    {
        $user = factory(User::class)->create();
        factory(UserPrioritySetting::class)->create([
            'user_id'       => $user->id,
            'health_weight' => 1.0,
            'budget_weight' => 2.0,
        ]);

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/priority-settings', [
            'health_weight' => 5.0,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(5.0, $response->json('data.health_weight'));

        $this->assertDatabaseHas('user_priority_settings', [
            'user_id'       => $user->id,
            'health_weight' => 5.0,
        ]);
    }

    public function test_conservacion_de_valores_no_enviados()
    {
        $user = factory(User::class)->create();
        factory(UserPrioritySetting::class)->create([
            'user_id'       => $user->id,
            'health_weight' => 3.0,
            'budget_weight' => 2.5,
            'time_weight'   => 1.5,
        ]);

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/priority-settings', [
            'health_weight' => 4.0,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('user_priority_settings', [
            'user_id'       => $user->id,
            'budget_weight' => 2.5,
            'time_weight'   => 1.5,
        ]);
    }

    public function test_valores_validos()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/priority-settings', [
            'health_weight'      => 10.0,
            'budget_weight'      => 5.5,
            'time_weight'        => 3.25,
            'stock_usage_weight' => 0,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.budget_weight', 5.5);
        $this->assertEquals(10.0, $response->json('data.health_weight'));
    }

    public function test_valores_fuera_de_rango()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/priority-settings', [
            'health_weight' => -1,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_rechazo_de_claves_desconocidas()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/priority-settings', [
            'unknown_weight' => 5.0,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_crea_configuracion_si_no_existe()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/priority-settings', [
            'health_weight' => 3.0,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(3.0, $response->json('data.health_weight'));

        $this->assertDatabaseHas('user_priority_settings', [
            'user_id'       => $user->id,
            'health_weight' => 3.0,
        ]);
    }

    // ---- AUDITORÍA ----

    public function test_auditoria()
    {
        $user = factory(User::class)->create();
        $auditCount = AuditLog::where('user_id', $user->id)->count();

        $this->actingAs($user)->patchJson('/api/v1/users/me/priority-settings', [
            'health_weight' => 5.0,
        ]);

        $this->assertGreaterThan($auditCount, AuditLog::where('user_id', $user->id)->count());
    }

    public function test_sin_auditoria_cuando_no_hay_cambios()
    {
        $user = factory(User::class)->create();
        factory(UserPrioritySetting::class)->create([
            'user_id'       => $user->id,
            'health_weight' => 3.0,
        ]);

        $auditCount = AuditLog::count();

        $this->actingAs($user)->patchJson('/api/v1/users/me/priority-settings', [
            'health_weight' => 3.0,
        ]);

        $this->assertEquals($auditCount, AuditLog::count());
    }

    // ---- TRACE ID ----

    public function test_trace_id_presente()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/users/me/priority-settings');

        $traceId = $response->json('trace_id');
        $this->assertNotNull($traceId);
        $this->assertRegExp(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $traceId
        );
    }
}
