<?php

namespace Tests\Feature\Api\V1\BodyMeasurement;

use App\AuditLog;
use App\BodyMeasurement;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BodyMeasurementTest extends TestCase
{
    use RefreshDatabase;

    // 1. Acceso sin autenticación
    public function test_acceso_sin_autenticacion()
    {
        $response = $this->getJson('/api/v1/users/me/body-measurements');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_UNAUTHENTICATED');
    }

    // 2. Listado devuelve solo registros propios
    public function test_listado_solo_propios()
    {
        $user  = factory(User::class)->create();
        $other = factory(User::class)->create();

        factory(BodyMeasurement::class)->create(['user_id' => $user->id,  'weight_kg' => 70]);
        factory(BodyMeasurement::class)->create(['user_id' => $other->id, 'weight_kg' => 90]);

        $response = $this->actingAs($user)->getJson('/api/v1/users/me/body-measurements');

        $response->assertStatus(200);
        $items = $response->json('data');
        $this->assertCount(1, $items);
        $this->assertEquals(70, $items[0]['weight_kg']);
    }

    // 3. Creación exitosa
    public function test_creacion_exitosa()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->postJson('/api/v1/users/me/body-measurements', [
            'measured_at' => '2025-01-15',
            'weight_kg'   => 75.5,
            'notes'       => 'Medición de control',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.weight_kg', 75.5)
            ->assertJsonStructure(['data' => ['id', 'measured_at', 'weight_kg', 'notes'], 'trace_id']);

        $this->assertDatabaseHas('body_measurements', [
            'user_id'   => $user->id,
            'weight_kg' => 75.5,
        ]);
    }

    // 4. Creación exige al menos una medición corporal
    public function test_creacion_exige_al_menos_una_medicion()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->postJson('/api/v1/users/me/body-measurements', [
            'notes' => 'solo notas, sin mediciones',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    // 5. Peso inválido rechazado
    public function test_peso_invalido_rechazado()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->postJson('/api/v1/users/me/body-measurements', [
            'weight_kg' => -5,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    // 6 & 7. Presión incompleta e inconsistente rechazadas
    public function test_presion_invalida_rechazada()
    {
        $user = factory(User::class)->create();

        // Solo sistólica (sin diastólica)
        $r1 = $this->actingAs($user)->postJson('/api/v1/users/me/body-measurements', [
            'blood_pressure_systolic' => 120,
        ]);
        $r1->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        // Sistólica menor que diastólica
        $r2 = $this->actingAs($user)->postJson('/api/v1/users/me/body-measurements', [
            'blood_pressure_systolic'  => 60,
            'blood_pressure_diastolic' => 80,
        ]);
        $r2->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    // 8. Creación ignora user_id externo
    public function test_creacion_ignora_user_id()
    {
        $user  = factory(User::class)->create();
        $other = factory(User::class)->create();

        $response = $this->actingAs($user)->postJson('/api/v1/users/me/body-measurements', [
            'user_id'   => $other->id,
            'weight_kg' => 70.0,
        ]);

        // Accepted (user_id is ignored) OR rejected — either way the record must belong to $user
        if ($response->status() === 201) {
            $this->assertDatabaseHas('body_measurements', ['user_id' => $user->id, 'weight_kg' => 70.0]);
            $this->assertDatabaseMissing('body_measurements', ['user_id' => $other->id, 'weight_kg' => 70.0]);
        } else {
            $response->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
        }
    }

    // 9. Actualización parcial exitosa
    public function test_actualizacion_parcial_exitosa()
    {
        $user        = factory(User::class)->create();
        $measurement = factory(BodyMeasurement::class)->create([
            'user_id'   => $user->id,
            'weight_kg' => 80.0,
            'notes'     => 'original',
        ]);

        $response = $this->actingAs($user)->patchJson(
            "/api/v1/users/me/body-measurements/{$measurement->id}",
            ['notes' => 'actualizado']
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.notes', 'actualizado');

        $this->assertDatabaseHas('body_measurements', [
            'id'        => $measurement->id,
            'notes'     => 'actualizado',
            'weight_kg' => 80.0,
        ]);
    }

    // 10. Actualización de medición ajena rechazada (IDOR)
    public function test_actualizacion_de_medicion_ajena_rechazada()
    {
        $user        = factory(User::class)->create();
        $other       = factory(User::class)->create();
        $measurement = factory(BodyMeasurement::class)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->patchJson(
            "/api/v1/users/me/body-measurements/{$measurement->id}",
            ['notes' => 'hack']
        );

        $response->assertStatus(404);
    }

    // 11. Eliminación propia exitosa
    public function test_eliminacion_propia_exitosa()
    {
        $user        = factory(User::class)->create();
        $measurement = factory(BodyMeasurement::class)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson(
            "/api/v1/users/me/body-measurements/{$measurement->id}"
        );

        $response->assertStatus(200);
        $this->assertDatabaseMissing('body_measurements', ['id' => $measurement->id]);
    }

    // 12. Eliminación ajena rechazada (IDOR)
    public function test_eliminacion_ajena_rechazada()
    {
        $user        = factory(User::class)->create();
        $other       = factory(User::class)->create();
        $measurement = factory(BodyMeasurement::class)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->deleteJson(
            "/api/v1/users/me/body-measurements/{$measurement->id}"
        );

        $response->assertStatus(404);
        $this->assertDatabaseHas('body_measurements', ['id' => $measurement->id]);
    }

    // 13. Orden descendente por fecha
    public function test_orden_descendente_por_fecha()
    {
        $user = factory(User::class)->create();

        factory(BodyMeasurement::class)->create(['user_id' => $user->id, 'measurement_date' => '2024-01-01', 'weight_kg' => 60]);
        factory(BodyMeasurement::class)->create(['user_id' => $user->id, 'measurement_date' => '2024-06-15', 'weight_kg' => 65]);
        factory(BodyMeasurement::class)->create(['user_id' => $user->id, 'measurement_date' => '2024-03-10', 'weight_kg' => 62]);

        $response = $this->actingAs($user)->getJson('/api/v1/users/me/body-measurements');

        $response->assertStatus(200);
        $dates = array_column($response->json('data'), 'measured_at');
        $this->assertEquals(['2024-06-15', '2024-03-10', '2024-01-01'], $dates);
    }

    // 14. Auditoría en creación
    public function test_auditoria_en_creacion()
    {
        $user = factory(User::class)->create();

        $countBefore = AuditLog::where('user_id', $user->id)->count();

        $this->actingAs($user)->postJson('/api/v1/users/me/body-measurements', [
            'weight_kg' => 75.0,
        ]);

        $this->assertGreaterThan($countBefore, AuditLog::where('user_id', $user->id)->count());
    }

    // 15. Respuesta sin campos internos
    public function test_respuesta_sin_campos_internos()
    {
        $user        = factory(User::class)->create();
        $measurement = factory(BodyMeasurement::class)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/users/me/body-measurements');

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringNotContainsString('"user_id"', $content);
    }
}
