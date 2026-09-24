<?php

namespace Tests\Feature;

use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Role;
use App\StockLocation;
use App\User;
use App\Brand;
use App\Product;
use App\StockItem;
use App\UnitMeasure;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebStockOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginated_overview_matches_stock_totals_with_bounded_eager_queries()
    {
        $user = factory(User::class)->create();
        $user->roles()->sync([Role::where('code', 'user')->value('id')]);
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id]);
        factory(FamilyGroupMember::class)->create(['family_group_id' => $group->id,
            'user_id' => $user->id, 'role_in_group' => 'owner', 'status' => 'active']);
        $location = StockLocation::create(['family_group_id' => $group->id, 'name' => 'Alacena', 'type' => 'pantry', 'status' => 'active']);
        $unit = UnitMeasure::create(['code' => 'u_qa', 'name' => 'Unidad', 'type' => 'unit', 'symbol' => 'u', 'status' => 'active']);
        $brand = Brand::create(['nombre' => 'QA', 'name' => 'QA', 'normalized_name' => 'qa', 'status' => 'active', 'padre' => 0]);
        for ($i = 0; $i < 12; $i++) {
            $product = Product::create(['nombre' => 'Producto '.$i, 'name' => 'Producto '.$i, 'normalized_name' => 'producto '.$i,
                'brand_id' => $brand->id, 'codigo' => 'QA'.$i, 'img' => 'product.png',
                'habilitado' => 1, 'supply_id' => 0, 'is_active' => true, 'status' => 'active']);
            StockItem::create(['family_group_id' => $group->id, 'product_id' => $product->id,
                'stock_location_id' => $location->id, 'quantity' => 2, 'unit_id' => $unit->id,
                'expiration_date' => now()->addDays(3)->toDateString(), 'estimated_purchase_price' => 10, 'status' => 'active']);
        }
        $this->actingAs($user);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->getJson('/web-data/family-groups/'.$group->id.'/stock?per_page=5&page=2');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $response->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('meta.total', 12)
            ->assertJsonPath('locations.0.id', $location->id)->assertJsonPath('summary.total_items', 12)
            ->assertJsonPath('summary.distinct_products', 12)->assertJsonPath('summary.expiring_soon', 12);
        $this->assertEquals(240, $response->json('value.total_value'));
        $this->assertLessThanOrEqual(14, count($queries), 'No per-product N+1 queries');
        $summary = $this->getJson('/api/v1/family-groups/'.$group->id.'/stock/summary')->assertOk()->json('data');
        foreach (['total_items', 'distinct_products', 'expiring_soon'] as $key) {
            $this->assertEquals($summary[$key], $response->json('summary.'.$key));
        }
    }

    public function test_overview_requires_permission_and_household_membership()
    {
        $user = factory(User::class)->create();
        $user->roles()->sync([Role::where('code', 'user')->value('id')]);
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id]);
        $path = '/web-data/family-groups/'.$group->id.'/stock';
        $this->getJson($path)->assertStatus(401);
        $this->actingAs($user)->getJson($path)->assertForbidden();
        factory(FamilyGroupMember::class)->create(['family_group_id' => $group->id,
            'user_id' => $user->id, 'role_in_group' => 'owner', 'status' => 'active']);
        $location = StockLocation::create(['family_group_id' => $group->id, 'name' => 'Alacena', 'type' => 'pantry', 'status' => 'active']);
        $this->getJson($path.'?page=2')->assertOk()->assertJsonPath('summary.total_items', 0)
            ->assertJsonPath('value.valued_items', 0)->assertJsonPath('locations.0.id', $location->id);
        $this->getJson($path.'?per_page=1000')->assertStatus(422);
        $outsider = factory(User::class)->create();
        $outsider->roles()->sync([Role::where('code', 'user')->value('id')]);
        $this->actingAs($outsider)->getJson($path)->assertForbidden();
    }
}
