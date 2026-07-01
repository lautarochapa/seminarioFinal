<?php

namespace Tests\Feature;

use App\Permission;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for all user web screens.
 * Ensures every screen key defined in UserWebScreenController::screens()
 * returns 200 with the correct blade section when the user has the required permission.
 */
class WebUserScreensRouteTest extends TestCase
{
    use RefreshDatabase;

    // Maps screenKey → permission code (only those with non-default permissions)
    private $customPermissions = [
        'payment-methods' => 'web.user.profile-objectives',
    ];

    // Screens that fall back to the generic dashboard panel (no dedicated section)
    private $dashboardFallbacks = ['dashboard'];

    // All user screens that must exist and return 200
    private $screens = [
        'stock'                   => ['data-*' => 'data-user-stock'],
        'recipes'                 => ['data-*' => 'data-user-recipes'],
        'recipe-search'           => ['data-*' => 'data-recipe-search'],
        'recipe-favorites'        => ['data-*' => 'data-user-fav'],
        'recipe-suggestions'      => ['data-*' => 'data-recipe-sugg'],
        'planning'                => ['data-*' => 'data-user-meal-plans'],
        'shopping-list'           => ['data-*' => 'data-user-shopping-list'],
        'shopping-session'        => ['data-*' => 'data-user-shopping-session'],
        'purchases'               => ['data-*' => 'data-user-purchases'],
        'notifications'           => ['data-*' => 'data-user-notifications'],
        'supplements'             => ['data-*' => 'data-user-supplements'],
        'budget'                  => ['data-*' => 'data-user-budget'],
        'reports'                 => ['data-*' => 'data-user-reports'],
        'family-group'            => ['data-*' => 'data-family-groups'],
        'profile-objectives'      => ['data-*' => 'data-user-profile'],
        'payment-methods'         => ['data-*' => 'data-user-payment-methods'],
        'professional-permissions'=> ['data-*' => 'data-professional-links'],
        'catalog'                 => ['data-*' => 'data-user-catalog'],
        'barcode-scanner'         => ['data-*' => 'data-user-barcode'],
        'supermarkets'            => ['data-*' => 'data-user-supermarkets'],
        'branches'                => ['data-*' => 'data-user-branches'],
    ];

    private function grantPermission(User $user, string $code): void
    {
        $role = Role::firstOrCreate(
            ['code' => 'test_web_user'],
            ['name' => 'Test web user', 'description' => null, 'status' => 'active']
        );

        $permission = Permission::firstOrCreate(
            ['code' => $code],
            ['module' => 'web.user', 'action' => 'access', 'description' => $code, 'status' => 'active']
        );

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    // ── Guest always redirected ───────────────────────────────────────────────

    public function test_web_dashboard_guest_redirected_to_login()
    {
        $this->get('/web')->assertRedirect('/login');
    }

    public function test_web_screen_guest_redirected_to_login()
    {
        $this->get('/web/stock')->assertRedirect('/login');
    }

    // ── Authenticated + permission → 200 ─────────────────────────────────────

    public function test_stock_returns_200_with_correct_section()
    {
        $this->assertScreenOk('stock', 'data-user-stock');
    }

    public function test_recipes_returns_200_with_correct_section()
    {
        $this->assertScreenOk('recipes', 'data-user-recipes');
    }

    public function test_recipe_search_returns_200_with_correct_section()
    {
        $this->assertScreenOk('recipe-search', 'data-recipe-search');
    }

    public function test_recipe_favorites_returns_200_with_correct_section()
    {
        $this->assertScreenOk('recipe-favorites', 'data-user-fav');
    }

    public function test_recipe_suggestions_returns_200_with_correct_section()
    {
        $this->assertScreenOk('recipe-suggestions', 'data-recipe-sugg');
    }

    public function test_planning_returns_200_with_correct_section()
    {
        $this->assertScreenOk('planning', 'data-user-meal-plans');
    }

    public function test_shopping_list_returns_200_with_correct_section()
    {
        $this->assertScreenOk('shopping-list', 'data-user-shopping-list');
    }

    public function test_shopping_session_returns_200_with_correct_section()
    {
        $this->assertScreenOk('shopping-session', 'data-user-shopping-session');
    }

    public function test_purchases_returns_200_with_correct_section()
    {
        $this->assertScreenOk('purchases', 'data-user-purchases');
    }

    public function test_notifications_returns_200_with_correct_section()
    {
        $this->assertScreenOk('notifications', 'data-user-notifications');
    }

    public function test_supplements_returns_200_with_correct_section()
    {
        $this->assertScreenOk('supplements', 'data-user-supplements');
    }

    public function test_budget_returns_200_with_correct_section()
    {
        $this->assertScreenOk('budget', 'data-user-budget');
    }

    public function test_reports_returns_200_with_correct_section()
    {
        $this->assertScreenOk('reports', 'data-user-reports');
    }

    public function test_family_group_returns_200_with_correct_section()
    {
        $this->assertScreenOk('family-group', 'data-family-groups');
    }

    public function test_profile_objectives_returns_200_with_correct_section()
    {
        $this->assertScreenOk('profile-objectives', 'data-user-profile');
    }

    public function test_payment_methods_returns_200_with_correct_section()
    {
        $user = factory(User::class)->create();
        $this->grantPermission($user, 'web.user.profile-objectives');

        $response = $this->actingAs($user)->get('/web/payment-methods');

        $response->assertStatus(200);
        $response->assertSee('data-user-payment-methods', false);
    }

    public function test_professional_permissions_returns_200_with_correct_section()
    {
        $this->assertScreenOk('professional-permissions', 'data-professional-links');
    }

    public function test_catalog_returns_200_with_correct_section()
    {
        $this->assertScreenOk('catalog', 'data-user-catalog');
    }

    public function test_barcode_scanner_returns_200_with_correct_section()
    {
        $this->assertScreenOk('barcode-scanner', 'data-user-barcode');
    }

    public function test_supermarkets_returns_200_with_correct_section()
    {
        $this->assertScreenOk('supermarkets', 'data-user-supermarkets');
    }

    public function test_branches_returns_200_with_correct_section()
    {
        $this->assertScreenOk('branches', 'data-user-branches');
    }

    // ── Without permission → 403 (not 404) ───────────────────────────────────

    public function test_recipe_search_without_permission_returns_403()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user)->get('/web/recipe-search')->assertStatus(403);
    }

    public function test_recipe_favorites_without_permission_returns_403()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user)->get('/web/recipe-favorites')->assertStatus(403);
    }

    public function test_recipe_suggestions_without_permission_returns_403()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user)->get('/web/recipe-suggestions')->assertStatus(403);
    }

    public function test_unknown_screen_returns_404()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user)->get('/web/nonexistent-screen-xyz')->assertStatus(404);
    }

    // ── JS scripts loaded ─────────────────────────────────────────────────────

    public function test_key_scripts_are_present_in_layout()
    {
        $user = factory(User::class)->create();
        $this->grantPermission($user, 'web.user.stock');

        $response = $this->actingAs($user)->get('/web/stock');

        $response->assertStatus(200);
        $response->assertSee('user-shopping-session.js', false);
        $response->assertSee('user-purchases.js', false);
        $response->assertSee('user-recipe-search.js', false);
        $response->assertSee('user-recipe-favorites.js', false);
        $response->assertSee('user-recipe-suggestions.js', false);
        $response->assertSee('user-shopping-lists.js', false);
        $response->assertSee('user-budget.js', false);
        $response->assertSee('family-groups.js', false);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function assertScreenOk(string $screenKey, string $dataAttr): void
    {
        $user = factory(User::class)->create();
        $permCode = 'web.user.' . $screenKey;
        $this->grantPermission($user, $permCode);

        $response = $this->actingAs($user)->get('/web/' . $screenKey);

        $response->assertStatus(200, "Screen '$screenKey' should return 200");
        $response->assertSee($dataAttr, false);
    }
}
