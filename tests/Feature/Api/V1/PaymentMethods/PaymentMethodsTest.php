<?php

namespace Tests\Feature\Api\V1\PaymentMethods;

use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    public function retiredRoutes(): array
    {
        return [
            ['GET', 'admin/payment-methods'], ['POST', 'admin/payment-methods'],
            ['GET', 'admin/payment-methods/1'], ['PATCH', 'admin/payment-methods/1'],
            ['DELETE', 'admin/payment-methods/1'], ['PATCH', 'admin/payment-methods/1/restore'],
            ['GET', 'payment-methods'], ['GET', 'users/me/payment-methods'],
            ['POST', 'users/me/payment-methods'], ['DELETE', 'users/me/payment-methods/1'],
        ];
    }

    /** @dataProvider retiredRoutes */
    public function test_retired_endpoint_is_unavailable_to_guests_and_every_current_role(string $method, string $path): void
    {
        $this->json($method, '/api/v1/'.$path)->assertStatus(404);
        foreach (['user', 'catalog_admin', 'recipe_admin', 'super_admin'] as $code) {
            $user = factory(User::class)->create();
            $user->roles()->sync([Role::where('code', $code)->firstOrFail()->id]);
            $this->actingAs($user)->json($method, '/api/v1/'.$path)->assertStatus(404);
        }
    }
}
