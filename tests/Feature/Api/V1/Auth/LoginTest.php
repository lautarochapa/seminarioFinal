<?php

namespace Tests\Feature\Api\V1\Auth;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser($overrides = [])
    {
        return factory(User::class)->create(array_merge([
            'email'    => 'user@example.com',
            'password' => Hash::make('password123'),
            'status'   => 'active',
        ], $overrides));
    }

    public function test_login_exitoso()
    {
        $this->makeUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'email', 'status'], 'trace_id'])
            ->assertJsonPath('data.email', 'user@example.com');

        $this->assertTrue($response->headers->has('X-Trace-Id'));
    }

    public function test_credenciales_invalidas_retorna_401()
    {
        $this->makeUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_INVALID_CREDENTIALS');
    }

    public function test_email_inexistente_retorna_401_generico()
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'noexiste@example.com',
            'password' => 'whatever',
        ]);

        // Misma respuesta para no exponer si el email existe
        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_INVALID_CREDENTIALS');
    }

    public function test_usuario_inactivo_retorna_403()
    {
        $this->makeUser(['status' => 'inactive']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_USER_INACTIVE');
    }

    public function test_usuario_eliminado_retorna_401_generico()
    {
        $user = $this->makeUser();
        $user->delete();

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_INVALID_CREDENTIALS');
    }

    public function test_validacion_campos_requeridos()
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_login_log_exitoso_registrado()
    {
        $this->makeUser();

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('login_logs', [
            'email'   => 'user@example.com',
            'success' => true,
        ]);
    }

    public function test_login_log_fallido_registrado()
    {
        $this->makeUser();

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'wrongpass',
        ]);

        $this->assertDatabaseHas('login_logs', [
            'email'   => 'user@example.com',
            'success' => false,
        ]);
    }

    public function test_no_expone_password_en_respuesta()
    {
        $this->makeUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $content = $response->getContent();
        $this->assertStringNotContainsString('password123', $content);
        $this->assertStringNotContainsString('remember_token', $content);
    }

    public function test_trace_id_en_error()
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'no@example.com',
            'password' => 'bad',
        ]);

        $this->assertNotNull($response->json('trace_id'));
    }
}
