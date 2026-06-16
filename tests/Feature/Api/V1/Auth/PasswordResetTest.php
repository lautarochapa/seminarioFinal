<?php

namespace Tests\Feature\Api\V1\Auth;

use App\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    // --- Forgot Password ---

    public function test_forgot_password_email_existente_envia_notificacion()
    {
        Notification::fake();
        $user = factory(User::class)->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'user@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['message'], 'trace_id']);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_email_inexistente_misma_respuesta()
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'noexiste@example.com',
        ]);

        // Misma respuesta para no enumerar emails
        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['message'], 'trace_id']);
    }

    public function test_forgot_password_validacion_email_invalido()
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'no-es-un-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_forgot_password_no_expone_token()
    {
        Notification::fake();
        factory(User::class)->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'user@example.com',
        ]);

        $content = $response->getContent();
        $this->assertStringNotContainsString('token', $content);
    }

    public function test_forgot_password_trace_id_presente()
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'any@example.com',
        ]);

        $this->assertNotNull($response->json('trace_id'));
    }

    // --- Reset Password ---

    public function test_reset_password_token_invalido_retorna_422()
    {
        factory(User::class)->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => 'token_invalido',
            'email'                 => 'user@example.com',
            'password'              => 'nueva_pass123',
            'password_confirmation' => 'nueva_pass123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'AUTH_RESET_TOKEN_INVALID')
            ->assertJsonStructure(['error' => ['code', 'message'], 'trace_id']);
    }

    public function test_reset_password_validacion_campos_requeridos()
    {
        $response = $this->postJson('/api/v1/auth/reset-password', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_reset_password_no_registra_token_ni_password()
    {
        Notification::fake();
        $user = factory(User::class)->create(['email' => 'user@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'user@example.com']);

        // Capture the token from the notification
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;
            return true;
        });

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => $token,
            'email'                 => 'user@example.com',
            'password'              => 'nuevapass123',
            'password_confirmation' => 'nuevapass123',
        ]);

        $response->assertStatus(200);

        // The response must not expose the token or password
        $content = $response->getContent();
        $this->assertStringNotContainsString($token, $content);
        $this->assertStringNotContainsString('nuevapass123', $content);
    }
}
