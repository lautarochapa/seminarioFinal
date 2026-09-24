<?php

namespace Tests\Feature;

use App\Permission;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function account($code = 'user')
    {
        $user = factory(User::class)->create();
        $user->roles()->sync([Role::where('code', $code)->firstOrFail()->id]);
        return $user;
    }

    public function test_html_query_count_is_bounded_and_unused_counters_are_not_loaded()
    {
        $user = $this->account();
        foreach (['stock', 'recipes', 'planning', 'shopping-list', 'budget', 'profile-objectives', 'family-group'] as $screen) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $html = $this->actingAs($user)->get('/web/'.$screen)->assertOk();
            $html->assertSee('turbo-cache-control', false)->assertSee('panel-navigation.js', false);
            $html->assertDontSee('/js/app.js', false)->assertDontSee('leaflet.js', false);
            $this->assertStringContainsString('no-store', $html->headers->get('Cache-Control'));
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $this->assertLessThanOrEqual(12, count($queries), $screen);
            if (in_array($screen, ['stock', 'profile-objectives'], true)) {
                foreach ($queries as $query) {
                    $this->assertStringNotContainsString('count(*)', strtolower($query['query']));
                }
            }
        }
    }

    public function test_permissions_refresh_on_the_next_request_without_leaking_between_users()
    {
        $user = $this->account();
        $admin = $this->account('super_admin');
        $this->actingAs($admin)->get('/admin-web/users')->assertOk();
        $this->actingAs($user)->get('/admin-web/users')->assertForbidden();
        $this->actingAs($user)->get('/web/stock')->assertOk();
        $permission = Permission::where('code', 'web.user.stock')->firstOrFail();
        DB::table('role_permissions')->where('permission_id', $permission->id)
            ->where('role_id', Role::where('code', 'user')->value('id'))->delete();
        $this->actingAs($user)->get('/web/stock')->assertForbidden();
        $this->actingAs($admin)->get('/web/stock')->assertOk();
    }

    public function test_limited_admins_keep_their_existing_boundaries()
    {
        foreach (['catalog_admin' => 'products', 'recipe_admin' => 'official-recipes'] as $code => $screen) {
            $user = $this->account($code);
            $this->actingAs($user)->get('/admin-web/'.$screen)->assertOk();
            $this->get('/admin-web/users')->assertForbidden();
            $this->get('/web/stock')->assertForbidden();
        }
    }
}
