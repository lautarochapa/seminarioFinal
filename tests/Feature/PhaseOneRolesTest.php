<?php

namespace Tests\Feature;

use App\ApiToken;
use App\Role;
use App\User;
use App\Services\Auth\RolePolicy;
use App\Services\Auth\ApiTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseOneRolesTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        $user = factory(User::class)->create(['status' => 'active', 'password' => Hash::make('Testing-role-1234')]);
        $user->roles()->sync([Role::where('code', $role)->firstOrFail()->id]);
        return $user;
    }

    public function test_only_four_seeded_roles_are_active_and_policy_is_idempotent(): void
    {
        RolePolicy::synchronize();
        RolePolicy::synchronize();
        $codes = Role::where('status', 'active')->orderBy('code')->pluck('code')->all();
        $this->assertSame(['catalog_admin', 'recipe_admin', 'super_admin', 'user'], $codes);
        $this->assertSame(0, DB::table('permissions')->where('status', 'active')->where('code', 'like', 'professional.%')->count());
    }

    public function test_supermarket_admin_is_merged_without_duplicate_assignments(): void
    {
        $old = Role::firstOrCreate(['code' => 'supermarket_admin'], ['name' => 'Old', 'status' => 'active']);
        $user = $this->account('catalog_admin');
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role_id' => $old->id, 'created_at' => now()]);
        RolePolicy::synchronize();
        RolePolicy::synchronize();
        $this->assertSame(['catalog_admin'], $user->fresh()->roles->pluck('code')->all());
    }

    public function test_admin_scopes_are_separate(): void
    {
        $catalog = $this->account('catalog_admin');
        foreach (['catalog.manage', 'supermarkets.manage', 'scraping.manage'] as $code) $this->assertTrue($catalog->hasPermission($code), $code);
        $this->assertFalse($catalog->canUseMobile());
        foreach (['rbac.manage', 'recipes.manage', 'users.manage', 'config.manage'] as $code) $this->assertFalse($catalog->hasPermission($code), $code);
        $this->actingAs($catalog)->get('/admin-web/products')->assertOk();
        $this->actingAs($catalog)->get('/admin-web/supermarkets')->assertOk();
        $this->actingAs($catalog)->get('/admin-web/official-recipes')->assertForbidden();
        $chef = $this->account('recipe_admin');
        $this->actingAs($chef)->get('/admin-web/meal-types')->assertOk();
        $this->actingAs($catalog)->get('/admin-web/meal-types')->assertForbidden();
        $this->assertTrue($chef->hasPermission('recipes.manage'));
        $this->assertFalse($chef->hasPermission('catalog.manage'));
        $this->actingAs($chef)->get('/admin-web/official-recipes')->assertOk();
        $this->actingAs($chef)->get('/admin-web')->assertSee('/admin-web/recipe-tags')->assertDontSee('/admin-web/users');
        $this->actingAs($chef)->getJson('/api/v1/ingredients')->assertOk();
        $this->actingAs($chef)->getJson('/api/v1/units')->assertOk();
        $this->actingAs($chef)->postJson('/api/v1/admin/products', [])->assertForbidden();
        $this->actingAs($chef)->get('/admin-web/users')->assertForbidden();
    }

    public function test_superadmin_has_all_current_permissions_but_no_retired_routes(): void
    {
        $admin = $this->account('super_admin');
        foreach (DB::table('permissions')->where('status', 'active')->pluck('code') as $code) $this->assertTrue($admin->hasPermission($code), $code);
        $this->assertFalse($admin->canUseMobile());
        $this->assertFalse($admin->hasPermission('professional.view'));
        foreach (['/teacher-web', '/web/professional-permissions', '/admin-web/thesis-docs', '/admin-web/demo-scenarios', '/api/v1/professional-links', '/api/v1/professional/linked-users', '/api/v1/thesis-documents', '/api/v1/admin/demo-scenarios'] as $path) {
            $this->actingAs($admin)->getJson($path)->assertNotFound();
        }
    }

    public function test_android_login_only_allows_common_user(): void
    {
        foreach (['super_admin', 'catalog_admin', 'recipe_admin'] as $role) {
            Auth::logout();
            $admin = $this->account($role);
            $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'Testing-role-1234'], ['X-CCC-Client' => 'android'])
                ->assertStatus(403)->assertJsonFragment(['code' => 'AUTH_WEB_ONLY']);
            $this->assertSame(0, ApiToken::where('user_id', $admin->id)->count());
        }
        $user = $this->account('user');
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Testing-role-1234'], ['X-CCC-Client' => 'android'])->assertOk();
        $this->assertSame('android', ApiToken::where('user_id', $user->id)->firstOrFail()->name);
    }

    public function test_all_four_roles_can_log_in_on_web(): void
    {
        foreach (RolePolicy::CURRENT as $role) {
            Auth::logout();
            $user = $this->account($role);
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Testing-role-1234'])->assertOk();
        }
    }

    public function test_android_token_is_revoked_when_role_changes_even_without_client_header(): void
    {
        $user = $this->account('user');
        $token = app(ApiTokenService::class)->issue($user, 'android')['access_token'];
        $user->roles()->syncWithoutDetaching([Role::where('code', 'catalog_admin')->firstOrFail()->id]);
        Auth::logout();
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$token])->assertStatus(403);
        $this->assertNotNull(ApiToken::where('user_id', $user->id)->firstOrFail()->revoked_at);
    }

    public function test_admin_session_cannot_bypass_android_guard(): void
    {
        $this->actingAs($this->account('super_admin'))->getJson('/api/v1/auth/me', ['X-CCC-Client' => 'android'])->assertStatus(403);
    }

    public function test_chef_can_manage_official_recipes_but_catalog_admin_cannot(): void
    {
        $chef = $this->account('recipe_admin');
        $catalog = $this->account('catalog_admin');
        foreach (['recipe-categories', 'recipe-tags', 'meal-types', 'recipes'] as $resource) {
            $this->actingAs($chef)->getJson('/api/v1/admin/'.$resource)->assertOk();
            $this->actingAs($catalog)->getJson('/api/v1/admin/'.$resource)->assertForbidden();
        }
        $recipe = \App\Recipe::create([
            'name' => 'Oficial', 'nombre' => 'Oficial', 'normalized_name' => 'oficial',
            'descripcion' => '', 'tiempo' => '', 'img' => '', 'video' => '', 'porcion' => '',
            'calorias' => 0, 'source_type' => 'official', 'status' => 'active',
            'is_public' => true, 'is_official' => true, 'is_verified' => true,
        ]);
        $ingredient = \App\Ingredient::create(['name'=>'Arroz', 'normalized_name'=>'arroz', 'status'=>'active', 'is_generic'=>true]);
        $unit = \App\UnitMeasure::create(['code'=>'unit_test', 'name'=>'Unidad', 'status'=>'active']);
        $ingredientPayload = ['ingredient_id'=>$ingredient->id, 'unit_id'=>$unit->id, 'quantity'=>1];
        $this->actingAs($catalog)->patchJson('/api/v1/recipes/'.$recipe->id, ['name'=>'No permitido'])->assertForbidden();
        $this->actingAs($catalog)->postJson('/api/v1/recipes/'.$recipe->id.'/ingredients', $ingredientPayload)->assertForbidden();
        $this->actingAs($catalog)->postJson('/api/v1/recipes/'.$recipe->id.'/steps', ['description'=>'No permitido'])->assertForbidden();
        $this->actingAs($chef)->patchJson('/api/v1/recipes/'.$recipe->id, ['name'=>'Oficial editada'])->assertOk();
        $this->actingAs($chef)->postJson('/api/v1/recipes/'.$recipe->id.'/ingredients', $ingredientPayload)->assertStatus(201);
        $this->actingAs($chef)->postJson('/api/v1/recipes/'.$recipe->id.'/steps', ['description'=>'Hervir'])->assertStatus(201);
    }

    public function test_deleted_account_token_stops_working(): void
    {
        $user = $this->account('user');
        $token = app(ApiTokenService::class)->issue($user)['access_token'];
        $user->delete();
        Auth::logout();
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
    }
}
