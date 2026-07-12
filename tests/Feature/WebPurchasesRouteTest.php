<?php

namespace Tests\Feature;

use App\Permission;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPurchasesRouteTest extends TestCase
{
    use RefreshDatabase;

    // ── /web/purchases ────────────────────────────────────────────────────────

    public function test_purchases_guest_is_redirected_to_login()
    {
        $this->get('/web/purchases')->assertRedirect('/login');
    }

    public function test_purchases_authenticated_user_with_permission_gets_200()
    {
        $user = factory(User::class)->create();
        $this->grantPermission($user, 'web.user.purchases');

        $response = $this->actingAs($user)->get('/web/purchases');

        $response->assertStatus(200);
    }

    public function test_purchases_blade_section_is_rendered()
    {
        $user = factory(User::class)->create();
        $this->grantPermission($user, 'web.user.purchases');

        $response = $this->actingAs($user)->get('/web/purchases');

        $response->assertSee('data-user-purchases', false);
    }

    public function test_purchases_js_is_loaded_in_layout()
    {
        $user = factory(User::class)->create();
        $this->grantPermission($user, 'web.user.purchases');

        $response = $this->actingAs($user)->get('/web/purchases');

        $response->assertSee('user-purchases.js', false);
    }

    public function test_purchases_without_permission_gets_403()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->get('/web/purchases');

        $response->assertStatus(403);
    }

    // ── /web/shopping-session ─────────────────────────────────────────────────

    public function test_shopping_session_guest_is_redirected_to_login()
    {
        $this->get('/web/shopping-session')->assertRedirect('/login');
    }

    public function test_shopping_session_authenticated_user_with_permission_gets_200()
    {
        $user = factory(User::class)->create();
        $this->grantPermission($user, 'web.user.shopping-session');

        $response = $this->actingAs($user)->get('/web/shopping-session');

        $response->assertStatus(200);
    }

    public function test_shopping_session_blade_section_is_rendered()
    {
        $user = factory(User::class)->create();
        $this->grantPermission($user, 'web.user.shopping-session');

        $response = $this->actingAs($user)->get('/web/shopping-session');

        $response->assertSee('data-user-shopping-session', false);
    }

    public function test_shopping_session_js_is_loaded_in_layout()
    {
        $user = factory(User::class)->create();
        $this->grantPermission($user, 'web.user.shopping-session');

        $response = $this->actingAs($user)->get('/web/shopping-session');

        $response->assertSee('user-shopping-session.js', false);
    }

    public function test_shopping_session_without_permission_gets_403()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->get('/web/shopping-session');

        $response->assertStatus(403);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

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
