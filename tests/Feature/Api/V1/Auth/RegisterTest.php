<?php

namespace Tests\Feature\Api\V1\Auth;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload($overrides = [])
    {
        return array_merge([
            'name'                  => 'Juan Pérez',
            'email'                 => 'juan@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ], $overrides);
    }

    public function test_registro_exitoso()
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'status'],
                'trace_id',
            ])
            ->assertJsonPath('data.email', 'juan@example.com')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('users', ['email' => 'juan@example.com']);
        $this->assertTrue($response->headers->has('X-Trace-Id'));
    }

    public function test_password_se_guarda_hasheada()
    {
        $this->postJson('/api/v1/auth/register', $this->validPayload([
            'email'    => 'hash@example.com',
            'password' => 'plaintext99',
            'password_confirmation' => 'plaintext99',
        ]));

        $user = User::where('email', 'hash@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotEquals('plaintext99', $user->password);
        $this->assertTrue(Hash::check('plaintext99', $user->password));
    }

    public function test_email_normalizado_a_minusculas()
    {
        $this->postJson('/api/v1/auth/register', $this->validPayload([
            'email' => '  UPPER@EXAMPLE.COM  ',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]));

        $this->assertDatabaseHas('users', ['email' => 'upper@example.com']);
    }

    public function test_email_duplicado_retorna_409()
    {
        factory(User::class)->create(['email' => 'juan@example.com']);

        $response = $this->postJson('/api/v1/auth/register', $this->validPayload());

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'AUTH_EMAIL_ALREADY_EXISTS')
            ->assertJsonStructure(['error' => ['code', 'message'], 'trace_id']);
    }

    public function test_validacion_campos_requeridos()
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['field_errors'], 'trace_id']);
    }

    public function test_password_confirmation_obligatoria()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'     => 'Test',
            'email'    => 'test@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(422);
    }

    public function test_password_minimo_8_caracteres()
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validPayload([
            'password'              => 'short',
            'password_confirmation' => 'short',
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_trace_id_presente_en_respuesta()
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validPayload());

        $traceId = $response->json('trace_id');
        $this->assertNotNull($traceId);
        $this->assertRegExp(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $traceId
        );
    }

    public function test_no_expone_password_en_respuesta()
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validPayload());

        $content = $response->getContent();
        $this->assertStringNotContainsString('secret123', $content);
        $this->assertStringNotContainsString('remember_token', $content);
    }

    public function test_login_log_registrado_en_registro()
    {
        $this->postJson('/api/v1/auth/register', $this->validPayload());

        $this->assertDatabaseHas('login_logs', [
            'email'   => 'juan@example.com',
            'success' => true,
        ]);
    }
}
