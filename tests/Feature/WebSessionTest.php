<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(VerifyCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends VerifyCsrfToken {
                protected function runningUnitTests() { return false; }
            };
        });
    }

    private function account()
    {
        $user = factory(User::class)->create(['password' => Hash::make('Local-tests-only-9081')]);
        $user->roles()->sync([Role::where('code', 'user')->value('id')]);
        return $user;
    }

    public function test_web_login_requires_csrf_and_does_not_issue_api_tokens()
    {
        $user = $this->account();
        $credentials = ['email' => $user->email, 'password' => 'Local-tests-only-9081'];
        $this->postJson('/web-session/login', $credentials)->assertStatus(419);
        $response = $this->withSession(['_token' => 'local-csrf'])
            ->postJson('/web-session/login', $credentials, ['X-CSRF-TOKEN' => 'local-csrf'])->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->assertArrayNotHasKey('token', $response->json());
        $this->assertDatabaseMissing('api_tokens', ['user_id' => $user->id]);
        $this->getJson('/api/v1/family-groups')->assertOk();
        $this->patchJson('/api/v1/auth/me', ['name' => 'Changed'])->assertStatus(419);
        $this->patchJson('/api/v1/auth/me', ['name' => 'Changed'],
            ['X-CSRF-TOKEN' => $response->json('csrf_token')])->assertOk();
        $this->postJson('/web-session/logout', [], ['X-CSRF-TOKEN' => $response->json('csrf_token')])->assertNoContent();
        $this->assertGuest();
        $this->getJson('/api/v1/family-groups')->assertStatus(401);
    }

    public function test_android_token_login_and_writes_keep_working_without_csrf()
    {
        $user = $this->account();
        $response = $this->postJson('/api/v1/auth/login',
            ['email' => $user->email, 'password' => 'Local-tests-only-9081'], ['X-CCC-Client' => 'android'])->assertOk();
        $token = $response->json('token.access_token');
        $this->assertNotEmpty($token);
        $this->patchJson('/api/v1/auth/me', ['name' => 'Android'],
            ['Authorization' => 'Bearer '.$token, 'X-CCC-Client' => 'android'])->assertOk();
        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => 'Bearer '.$token])->assertNoContent();
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$token])->assertStatus(401);
    }

    public function test_web_registration_creates_a_session_and_default_role_without_a_bearer_token()
    {
        $response = $this->withSession(['_token' => 'qa-register'])->postJson('/web-session/register', [
            'name' => 'Registro QA', 'email' => 'web-session-qa@example.invalid',
            'password' => 'Local-tests-only-9081', 'password_confirmation' => 'Local-tests-only-9081',
        ], ['X-CSRF-TOKEN' => 'qa-register'])->assertCreated();
        $user = User::where('email', 'web-session-qa@example.invalid')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole('user'));
        $this->assertDatabaseMissing('api_tokens', ['user_id' => $user->id]);
        $this->assertNotEmpty($response->json('csrf_token'));
    }

    public function test_invalid_bearer_cannot_bypass_csrf_using_a_cookie_session()
    {
        $user = $this->account();
        $this->actingAs($user)->patchJson('/api/v1/auth/me', ['name' => 'Not changed'],
            ['Authorization' => 'Bearer invalid-test-token'])->assertStatus(401);
        $this->assertNotEquals('Not changed', $user->fresh()->name);
    }
}
