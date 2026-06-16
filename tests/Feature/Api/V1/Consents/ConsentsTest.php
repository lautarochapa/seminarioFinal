<?php

namespace Tests\Feature\Api\V1\Consents;

use App\AuditLog;
use App\User;
use App\UserConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_acceso_sin_autenticacion()
    {
        $this->getJson('/api/v1/users/me/consents')->assertStatus(401);
    }

    public function test_get_devuelve_estado_propio()
    {
        $user = factory(User::class)->create();
        UserConsent::create([
            'user_id' => $user->id,
            'consent_type' => 'health_data_consent',
            'accepted' => true,
            'accepted_at' => now(),
        ]);

        $this->actingAs($user)->getJson('/api/v1/users/me/consents')
            ->assertStatus(200)
            ->assertJsonPath('data.consents.health_data_consent.accepted', true)
            ->assertJsonPath('data.consents.health_data_consent.required', false);
    }

    public function test_auth_consents_mismo_contrato()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user)->getJson('/api/v1/auth/consents')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['consents' => ['privacy_consent']], 'trace_id']);
    }

    public function test_patch_acepta_consentimientos()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user)->patchJson('/api/v1/users/me/consents', [
            'health_data_consent' => true,
            'privacy_consent' => true,
        ])->assertStatus(200)
            ->assertJsonPath('data.consents.health_data_consent.accepted', true)
            ->assertJsonPath('data.consents.privacy_consent.accepted', true);

        $this->assertDatabaseHas('user_consents', ['user_id' => $user->id, 'consent_type' => 'privacy_consent', 'accepted' => true]);
    }

    public function test_patch_parcial_conserva_valores()
    {
        $user = factory(User::class)->create();
        UserConsent::create(['user_id' => $user->id, 'consent_type' => 'privacy_consent', 'accepted' => true, 'accepted_at' => now()]);

        $this->actingAs($user)->patchJson('/api/v1/users/me/consents', [
            'health_data_consent' => true,
        ])->assertStatus(200)
            ->assertJsonPath('data.consents.privacy_consent.accepted', true)
            ->assertJsonPath('data.consents.health_data_consent.accepted', true);
    }

    public function test_revocacion_valida()
    {
        $user = factory(User::class)->create();
        UserConsent::create(['user_id' => $user->id, 'consent_type' => 'professional_access_consent', 'accepted' => true, 'accepted_at' => now()]);

        $response = $this->actingAs($user)->patchJson('/api/v1/users/me/consents', [
            'professional_access_consent' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.consents.professional_access_consent.accepted', false);
        $this->assertNotNull($response->json('data.consents.professional_access_consent.revoked_at'));
    }

    public function test_campos_desconocidos_rechazados()
    {
        $this->actingAs(factory(User::class)->create())->patchJson('/api/v1/users/me/consents', [
            'unknown_consent' => true,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_valores_no_booleanos_rechazados()
    {
        $this->actingAs(factory(User::class)->create())->patchJson('/api/v1/users/me/consents', [
            'privacy_consent' => 'yes',
        ])->assertStatus(422);
    }

    public function test_user_id_rechazado()
    {
        $this->actingAs(factory(User::class)->create())->patchJson('/api/v1/users/me/consents', [
            'user_id' => 123,
            'privacy_consent' => true,
        ])->assertStatus(422);
    }

    public function test_campos_internos_no_modificables()
    {
        $this->actingAs(factory(User::class)->create())->patchJson('/api/v1/users/me/consents', [
            'accepted_at' => now()->subYear()->toDateTimeString(),
            'privacy_consent' => true,
        ])->assertStatus(422);
    }

    public function test_fechas_ip_user_agent_generados_por_servidor()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user)
            ->withHeader('User-Agent', 'ConsentTestAgent')
            ->patchJson('/api/v1/users/me/consents', ['terms_accepted' => true])
            ->assertStatus(200);

        $consent = UserConsent::where('user_id', $user->id)->where('consent_type', 'terms_accepted')->first();
        $this->assertNotNull($consent->accepted_at);
        $this->assertNotNull($consent->ip_address);
        $this->assertEquals('ConsentTestAgent', $consent->user_agent);
    }

    public function test_no_modifica_otro_usuario()
    {
        $user = factory(User::class)->create();
        $other = factory(User::class)->create();
        UserConsent::create(['user_id' => $other->id, 'consent_type' => 'privacy_consent', 'accepted' => false]);

        $this->actingAs($user)->patchJson('/api/v1/users/me/consents', ['privacy_consent' => true])
            ->assertStatus(200);

        $this->assertDatabaseHas('user_consents', ['user_id' => $other->id, 'consent_type' => 'privacy_consent', 'accepted' => false]);
    }

    public function test_auditoria_en_cambios_y_no_auditoria_sin_cambios()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user)->patchJson('/api/v1/users/me/consents', ['privacy_consent' => true])
            ->assertStatus(200);
        $this->assertEquals(1, AuditLog::where('action', 'user-consents.updated')->count());

        $this->actingAs($user)->patchJson('/api/v1/users/me/consents', ['privacy_consent' => true])
            ->assertStatus(200);
        $this->assertEquals(1, AuditLog::where('action', 'user-consents.updated')->count());
    }

    public function test_ausencia_de_campos_sensibles()
    {
        $response = $this->actingAs(factory(User::class)->create())->getJson('/api/v1/users/me/consents');

        $response->assertStatus(200);
        $json = json_encode($response->json('data'));
        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString('remember_token', $json);
        $this->assertStringNotContainsString('user_id', $json);
    }
}
