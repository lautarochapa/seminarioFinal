<?php

namespace Tests\Feature\Api\V1\Auth;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_retorna_usuario_autenticado()
    {
        $user = factory(User::class)->create(['email' => 'me@example.com']);

        $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'me@example.com')
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'status'], 'trace_id']);
    }

    public function test_me_no_autenticado_retorna_401()
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_UNAUTHENTICATED');
    }

    public function test_me_no_expone_campos_sensibles()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

        $content = $response->getContent();
        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('remember_token', $content);
        $this->assertStringNotContainsString('nivel_acceso', $content);
    }

    public function test_me_trace_id_presente()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

        $this->assertNotNull($response->json('trace_id'));
        $this->assertTrue($response->headers->has('X-Trace-Id'));
    }

    // --- PATCH /me ---

    public function test_actualizar_campos_permitidos()
    {
        $user = factory(User::class)->create(['name' => 'Original', 'phone' => null]);

        $response = $this->actingAs($user)->patchJson('/api/v1/auth/me', [
            'name'  => 'Nuevo Nombre',
            'phone' => '+54911000000',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Nuevo Nombre')
            ->assertJsonPath('data.phone', '+54911000000');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nuevo Nombre']);
    }

    public function test_campos_protegidos_ignorados()
    {
        $user = factory(User::class)->create(['email' => 'original@example.com']);

        $this->actingAs($user)->patchJson('/api/v1/auth/me', [
            'email'       => 'hacked@example.com',
            'password'    => 'newpassword',
            'status'      => 'inactive',
            'nivel_acceso'=> 3,
            'name'        => 'Nuevo',
        ]);

        // Campos protegidos no deben cambiar
        $this->assertDatabaseHas('users', [
            'id'    => $user->id,
            'email' => 'original@example.com',
        ]);
        $this->assertDatabaseMissing('users', ['id' => $user->id, 'email' => 'hacked@example.com']);
    }

    public function test_patch_me_no_autenticado_retorna_401()
    {
        $response = $this->patchJson('/api/v1/auth/me', ['name' => 'Test']);

        $response->assertStatus(401);
    }

    public function test_username_duplicado_retorna_422()
    {
        $other = factory(User::class)->create(['username' => 'taken_name']);
        $user  = factory(User::class)->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/auth/me', [
            'username' => 'taken_name',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_auditoria_registrada_al_actualizar()
    {
        $user = factory(User::class)->create(['name' => 'Original']);

        $this->actingAs($user)->patchJson('/api/v1/auth/me', ['name' => 'Nuevo']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'update',
            'entity_name' => 'users',
            'entity_id'   => $user->id,
        ]);
    }
}
