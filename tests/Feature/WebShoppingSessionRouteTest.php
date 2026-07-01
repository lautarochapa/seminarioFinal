<?php

namespace Tests\Feature;

use App\Permission;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebShoppingSessionRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login()
    {
        $this->get('/web/shopping-session')->assertRedirect('/login');
    }

    public function test_authenticated_user_with_permission_can_open_shopping_session_screen()
    {
        $user = factory(User::class)->create();
        $this->grantPermission($user, 'web.user.shopping-session');

        $response = $this->actingAs($user)->get('/web/shopping-session');

        $response->assertStatus(200);
        $response->assertSee('data-user-shopping-session', false);
        $response->assertSee('user-shopping-session.js', false);
    }

    private function grantPermission(User $user, string $code): void
    {
        $role = Role::firstOrCreate(
            ['code' => 'web_route_test_user'],
            ['name' => 'Web route test user', 'description' => null, 'status' => 'active']
        );

        $permission = Permission::firstOrCreate(
            ['code' => $code],
            ['module' => 'web.user', 'action' => 'access', 'description' => $code, 'status' => 'active']
        );

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}