<?php

namespace Tests\Feature\Api\V1\Auth;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_exitoso()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->postJson('/api/v1/auth/logout');

        $response->assertStatus(204);
        $this->assertTrue($response->headers->has('X-Trace-Id'));
    }

    public function test_logout_no_autenticado_retorna_401()
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_UNAUTHENTICATED');
    }
}
