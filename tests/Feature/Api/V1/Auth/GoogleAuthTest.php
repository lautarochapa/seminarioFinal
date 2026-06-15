<?php

namespace Tests\Feature\Api\V1\Auth;

use App\SocialAccount;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function mockSocialiteUser($id, $email, $name = 'Test User', $avatar = null)
    {
        $googleUser = Mockery::mock('Laravel\Socialite\Two\User');
        $googleUser->shouldReceive('getId')->andReturn($id);
        $googleUser->shouldReceive('getEmail')->andReturn($email);
        $googleUser->shouldReceive('getName')->andReturn($name);
        $googleUser->shouldReceive('getAvatar')->andReturn($avatar ?: 'https://avatar.example.com/img.jpg');

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('userFromToken')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function mockSocialiteFailure()
    {
        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('userFromToken')->andThrow(new \Exception('Token inválido'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_google_auth_crea_usuario_nuevo()
    {
        $this->mockSocialiteUser('google_001', 'nuevo@gmail.com', 'Nuevo Usuario');

        $response = $this->postJson('/api/v1/auth/google', ['token' => 'valid_token']);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'email'], 'trace_id'])
            ->assertJsonPath('data.email', 'nuevo@gmail.com');

        $this->assertDatabaseHas('users', ['email' => 'nuevo@gmail.com']);
        $this->assertDatabaseHas('social_accounts', [
            'provider'         => 'google',
            'provider_user_id' => 'google_001',
        ]);
    }

    public function test_google_auth_vincula_cuenta_existente_por_email()
    {
        $user = factory(User::class)->create(['email' => 'existente@gmail.com']);
        $this->mockSocialiteUser('google_002', 'existente@gmail.com');

        $response = $this->postJson('/api/v1/auth/google', ['token' => 'valid_token']);

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'existente@gmail.com');

        $this->assertDatabaseHas('social_accounts', [
            'user_id'          => $user->id,
            'provider'         => 'google',
            'provider_user_id' => 'google_002',
        ]);
    }

    public function test_google_auth_usuario_existente_ya_vinculado()
    {
        $user = factory(User::class)->create(['email' => 'linked@gmail.com']);
        SocialAccount::create([
            'user_id'          => $user->id,
            'provider'         => 'google',
            'provider_user_id' => 'google_003',
            'provider_email'   => 'linked@gmail.com',
        ]);
        $this->mockSocialiteUser('google_003', 'linked@gmail.com');

        $response = $this->postJson('/api/v1/auth/google', ['token' => 'valid_token']);

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'linked@gmail.com');

        // No debe crear un segundo social_account
        $this->assertEquals(1, SocialAccount::where('provider_user_id', 'google_003')->count());
    }

    public function test_google_token_invalido_retorna_401()
    {
        $this->mockSocialiteFailure();

        $response = $this->postJson('/api/v1/auth/google', ['token' => 'bad_token']);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_GOOGLE_FAILED');
    }

    public function test_google_token_campo_requerido()
    {
        $response = $this->postJson('/api/v1/auth/google', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_google_usuario_eliminado_retorna_409()
    {
        $user = factory(User::class)->create(['email' => 'deleted@gmail.com']);
        $user->delete();

        $this->mockSocialiteUser('google_004', 'deleted@gmail.com');

        $response = $this->postJson('/api/v1/auth/google', ['token' => 'valid_token']);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'AUTH_GOOGLE_ACCOUNT_CONFLICT');
    }

    public function test_google_no_expone_tokens()
    {
        $this->mockSocialiteUser('google_005', 'safe@gmail.com');

        $response = $this->postJson('/api/v1/auth/google', ['token' => 'valid_token']);

        $content = $response->getContent();
        $this->assertStringNotContainsString('access_token', $content);
        $this->assertStringNotContainsString('refresh_token', $content);
    }

    public function test_trace_id_presente()
    {
        $this->mockSocialiteUser('google_006', 'trace@gmail.com');

        $response = $this->postJson('/api/v1/auth/google', ['token' => 'valid_token']);

        $this->assertNotNull($response->json('trace_id'));
    }
}
