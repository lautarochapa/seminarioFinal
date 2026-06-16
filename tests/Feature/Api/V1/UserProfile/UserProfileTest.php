<?php

namespace Tests\Feature\Api\V1\UserProfile;

use App\AuditLog;
use App\Objective;
use App\User;
use App\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    // ---- AUTH ----

    public function test_acceso_sin_autenticacion()
    {
        $response = $this->getJson('/api/v1/users/me/profile');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_UNAUTHENTICATED');
    }

    // ---- GET PERFIL ----

    public function test_obtener_perfil_propio()
    {
        $user = factory(User::class)->create(['name' => 'Juan', 'email' => 'juan@example.com']);
        factory(UserProfile::class)->create(['user_id' => $user->id, 'height_cm' => 175]);

        $response = $this->actingAs($user)->getJson('/api/v1/users/me/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Juan')
            ->assertJsonPath('data.email', 'juan@example.com')
            ->assertJsonPath('data.height_cm', 175)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'birth_date', 'gender', 'height_cm', 'current_weight_kg', 'activity_level', 'objectives', 'preferences'],
                'trace_id',
            ]);
    }

    public function test_no_exposicion_de_campos_sensibles()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/users/me/profile');

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('remember_token', $content);
        $this->assertStringNotContainsString('nivel_acceso', $content);
    }

    public function test_respuesta_estable_con_perfil_inexistente()
    {
        $user = factory(User::class)->create();
        // No UserProfile record

        $response = $this->actingAs($user)->getJson('/api/v1/users/me/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.birth_date', null)
            ->assertJsonPath('data.height_cm', null)
            ->assertJsonPath('data.current_weight_kg', null)
            ->assertJsonPath('data.objectives', []);
    }

    public function test_objetivos_en_respuesta()
    {
        $user = factory(User::class)->create();
        $objective = factory(Objective::class)->create(['code' => 'improve_health', 'status' => 'active']);
        \Illuminate\Support\Facades\DB::table('user_objectives')->insert([
            'user_id'      => $user->id,
            'objective_id' => $objective->id,
            'is_active'    => true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/users/me/profile');

        $response->assertStatus(200);
        $objectives = $response->json('data.objectives');
        $this->assertNotEmpty($objectives);
        $codes = array_column($objectives, 'code');
        $this->assertContains('improve_health', $codes);
    }

    // ---- PATCH PERFIL ----

    public function test_actualizacion_parcial()
    {
        $user = factory(User::class)->create(['name' => 'Original', 'phone' => '123456']);
        factory(UserProfile::class)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'name' => 'Actualizado',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Actualizado');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Actualizado']);
    }

    public function test_campos_no_enviados_se_conservan()
    {
        $user = factory(User::class)->create(['name' => 'Original', 'phone' => '999111']);
        factory(UserProfile::class)->create(['user_id' => $user->id, 'height_cm' => 180]);

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'name' => 'Cambiado',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'phone' => '999111']);
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id, 'height_cm' => 180]);
    }

    public function test_actualizacion_de_usuario_y_perfil()
    {
        $user = factory(User::class)->create(['name' => 'Antes']);
        factory(UserProfile::class)->create(['user_id' => $user->id, 'height_cm' => 160]);

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'name'      => 'Después',
            'height_cm' => 175,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Después')
            ->assertJsonPath('data.height_cm', 175);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Después']);
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id, 'height_cm' => 175]);
    }

    public function test_rechazo_de_campos_protegidos()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'email' => 'otro@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    // ---- VALIDACIONES ----

    public function test_peso_valido()
    {
        $user = factory(User::class)->create();
        factory(UserProfile::class)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'current_weight_kg' => 72.5,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.current_weight_kg', 72.5);
    }

    public function test_peso_invalido()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'current_weight_kg' => -5,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_altura_valida()
    {
        $user = factory(User::class)->create();
        factory(UserProfile::class)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'height_cm' => 170,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.height_cm', 170);
    }

    public function test_altura_invalida()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'height_cm' => -10,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_fecha_futura_rechazada()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'birth_date' => now()->addYear()->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_nivel_de_actividad_valido()
    {
        $user = factory(User::class)->create();
        factory(UserProfile::class)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'activity_level' => 'moderate',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.activity_level', 'moderate');
    }

    public function test_nivel_de_actividad_invalido()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'activity_level' => 'turbo_active',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_objetivos_validos()
    {
        $user = factory(User::class)->create();
        $objective = factory(Objective::class)->create(['status' => 'active']);

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'objective_ids' => [$objective->id],
        ]);

        $response->assertStatus(200);
        $codes = array_column($response->json('data.objectives'), 'id');
        $this->assertContains($objective->id, $codes);
    }

    public function test_objetivo_invalido()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'objective_ids' => [99999],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_preferencias_validas()
    {
        $user = factory(User::class)->create();
        factory(UserProfile::class)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'preferences' => ['uses_app_for_health' => true],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.preferences.uses_app_for_health', true);
    }

    public function test_clave_de_preferencia_no_permitida()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'preferences' => ['unknown_preference' => true],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_normalizacion_de_strings()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'name' => '  Juan  ',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Juan');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Juan']);
    }

    // ---- AUDITORÍA ----

    public function test_auditoria_before_after()
    {
        $user = factory(User::class)->create(['name' => 'Antes']);
        $auditCount = AuditLog::where('user_id', $user->id)->count();

        $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'name' => 'Después',
        ]);

        $this->assertGreaterThan($auditCount, AuditLog::where('user_id', $user->id)->count());
    }

    public function test_sin_auditoria_cuando_no_hay_cambios()
    {
        $user = factory(User::class)->create(['name' => 'Igual']);
        factory(UserProfile::class)->create(['user_id' => $user->id, 'activity_level' => 'moderate']);

        $auditCount = AuditLog::count();

        $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'name'           => 'Igual',
            'activity_level' => 'moderate',
        ]);

        $this->assertEquals($auditCount, AuditLog::count());
    }

    // ---- TRACE ID ----

    public function test_trace_id_presente()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/users/me/profile');

        $traceId = $response->json('trace_id');
        $this->assertNotNull($traceId);
        $this->assertRegExp(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $traceId
        );
    }
}
