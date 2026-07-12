<?php

namespace Tests\Feature\Api\V1\GroupReports;

use App\Budget;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Purchase;
use App\Recipe;
use App\RecipeCookLog;
use App\StockItem;
use App\StockMovement;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupReportsTest extends TestCase
{
    use RefreshDatabase;

    private function groupWithMember()
    {
        $user  = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id, 'status' => 'active']);
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id'         => $user->id,
            'role_in_group'   => 'owner',
            'status'          => 'active',
        ]);
        return [$user, $group];
    }

    private function unit(): UnitMeasure
    {
        return UnitMeasure::create(['name' => 'kg', 'code' => 'KG_' . uniqid(), 'type' => 'mass', 'status' => 'active']);
    }

    private function product(): \App\Product
    {
        return \App\Product::create([
            'name'       => 'Prod ' . uniqid(),
            'nombre'     => 'Prod',
            'brand_id'   => 0,
            'codigo'     => 'C' . uniqid(),
            'img'        => '',
            'habilitado' => 1,
            'supply_id'  => 0,
            'status'     => 'active',
        ]);
    }

    private function recipe(): Recipe
    {
        return Recipe::create([
            'nombre'     => 'Receta Test',
            'descripcion' => 'desc',
            'tiempo'     => '30 min',
            'img'        => '',
            'video'      => '',
            'porcion'    => '2',
            'calorias'   => 300,
        ]);
    }

    private function stockItem(FamilyGroup $group, UnitMeasure $unit, \App\Product $product, array $overrides = []): StockItem
    {
        return StockItem::create(array_merge([
            'family_group_id' => $group->id,
            'product_id'      => $product->id,
            'unit_id'         => $unit->id,
            'quantity'        => 2,
            'status'          => 'active',
        ], $overrides));
    }

    private function purchase(FamilyGroup $group, User $user, float $amount, string $date, string $status = 'confirmed')
    {
        return Purchase::create([
            'family_group_id' => $group->id,
            'user_id'         => $user->id,
            'purchase_date'   => $date,
            'status'          => $status,
            'actual_total'    => $amount,
        ]);
    }

    private function budget(FamilyGroup $group, float $total = 10000, int $year = 2026, int $month = 6)
    {
        return Budget::create([
            'family_group_id' => $group->id,
            'year'            => $year,
            'month'           => $month,
            'total_amount'    => $total,
            'currency'        => 'ARS',
            'status'          => 'active',
        ]);
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/v1/family-groups/1/reports/stock')->assertStatus(401);
        $this->getJson('/api/v1/family-groups/1/reports/purchases')->assertStatus(401);
    }

    public function test_access_to_foreign_group_is_denied()
    {
        [$user]         = $this->groupWithMember();
        [, $otherGroup] = $this->groupWithMember();

        $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$otherGroup->id}/reports/stock")
            ->assertStatus(403);
    }

    public function test_stock_report_returns_item_counts()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $prod = $this->product();
        $this->stockItem($group, $unit, $prod);
        $this->stockItem($group, $unit, $prod);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/reports/stock")
            ->assertStatus(200);

        $this->assertEquals(2, $response->json('data.total_items'));
        $this->assertArrayHasKey('by_category', $response->json('data'));
        $this->assertArrayHasKey('trace_id', $response->json());
    }

    public function test_stock_value_report_returns_estimated_totals()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $prod = $this->product();
        $this->stockItem($group, $unit, $prod, ['quantity' => 3, 'estimated_purchase_price' => 100]);
        $this->stockItem($group, $unit, $prod, ['quantity' => 2]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/reports/stock-value")
            ->assertStatus(200);

        $this->assertEquals(300.0, $response->json('data.total_value'));
        $this->assertEquals(1,     $response->json('data.items_with_price'));
        $this->assertEquals(1,     $response->json('data.items_without_price'));
    }

    public function test_expiring_products_filters_by_days_window()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $prod = $this->product();
        $this->stockItem($group, $unit, $prod, ['expiration_date' => now()->addDays(3)->toDateString()]);
        $this->stockItem($group, $unit, $prod, ['expiration_date' => now()->addDays(30)->toDateString()]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/reports/expiring-products?days=7")
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('data.expiring_count'));
        $this->assertEquals(7, $response->json('data.days_window'));
    }

    public function test_waste_report_returns_paginated_movements_with_totals()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();

        StockMovement::create([
            'family_group_id' => $group->id,
            'movement_type'   => 'discard',
            'quantity'        => -2,
            'unit_id'         => $unit->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/reports/waste")
            ->assertStatus(200);

        $this->assertEquals(1,   $response->json('meta.total'));
        $this->assertEquals(2.0, $response->json('totals.discarded_quantity'));
        $this->assertEquals(0.0, $response->json('totals.estimated_loss'));
        $this->assertArrayHasKey('trace_id', $response->json());
    }

    public function test_purchases_report_excludes_cancelled_and_filters_by_date()
    {
        [$user, $group] = $this->groupWithMember();
        $this->purchase($group, $user, 1500, '2026-06-01');
        $this->purchase($group, $user, 2000, '2026-06-15');
        $this->purchase($group, $user, 1000, '2026-06-20', 'cancelled');
        $this->purchase($group, $user, 3000, '2026-05-10');

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/reports/purchases?date_from=2026-06-01&date_to=2026-06-30")
            ->assertStatus(200);

        $this->assertEquals(3500.0, $response->json('data.total_spent'));
        $this->assertEquals(2,      $response->json('data.total_count'));
        $this->assertArrayHasKey('by_month', $response->json('data'));
    }

    public function test_budget_vs_actual_returns_per_period_comparison()
    {
        [$user, $group] = $this->groupWithMember();
        $this->budget($group, 10000, 2026, 6);
        $this->purchase($group, $user, 4000, '2026-06-10');

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/reports/budget-vs-actual")
            ->assertStatus(200);

        $period = $response->json('data.periods.0');
        $this->assertEquals(10000.0, $period['total_amount']);
        $this->assertEquals(4000.0,  $period['spent_amount']);
        $this->assertEquals(6000.0,  $period['available']);
        $this->assertEquals(40.0,    $period['consumed_percent']);
    }

    public function test_recipes_cooked_returns_totals_and_top_recipes()
    {
        [$user, $group] = $this->groupWithMember();
        $recipe = $this->recipe();

        RecipeCookLog::create([
            'family_group_id' => $group->id,
            'user_id'         => $user->id,
            'recipe_id'       => $recipe->id,
            'servings'        => 4,
            'cooked_at'       => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/reports/recipes-cooked")
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('data.total_cooked'));
        $this->assertEquals(4, $response->json('data.total_servings'));
        $this->assertCount(1,  $response->json('data.top_recipes'));
    }

    public function test_nutrition_estimate_returns_null_when_no_nutrition_data()
    {
        [$user, $group] = $this->groupWithMember();
        $recipe = $this->recipe();

        RecipeCookLog::create([
            'family_group_id' => $group->id,
            'user_id'         => $user->id,
            'recipe_id'       => $recipe->id,
            'servings'        => 2,
            'cooked_at'       => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/reports/nutrition-estimate")
            ->assertStatus(200);

        $this->assertNull($response->json('data.estimated_calories'));
        $this->assertEquals(1, $response->json('data.logs_without_nutrition'));
        $this->assertArrayHasKey('note', $response->json('data'));
    }

    public function test_budget_report_lists_active_budgets()
    {
        [$user, $group] = $this->groupWithMember();
        $this->budget($group, 5000, 2026, 5);
        $this->budget($group, 8000, 2026, 6);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/reports/budget")
            ->assertStatus(200);

        $this->assertEquals(2, $response->json('data.total_budgets'));
    }
}
