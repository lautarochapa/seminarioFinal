<?php

namespace Tests\Feature\Api\V1\BudgetCategories;

use App\AuditLog;
use App\Budget;
use App\BudgetCategory;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Product;
use App\ProductCategory;
use App\Purchase;
use App\PurchaseItem;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetCategoriesTest extends TestCase
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

    private function budget(FamilyGroup $group, array $overrides = [])
    {
        return Budget::create(array_merge([
            'family_group_id' => $group->id,
            'year'            => 2026,
            'month'           => 6,
            'total_amount'    => 10000,
            'currency'        => 'ARS',
            'status'          => 'active',
        ], $overrides));
    }

    private function productCategory(string $name = 'Lacteos')
    {
        return ProductCategory::create([
            'name'   => $name . uniqid(),
            'status' => 'active',
        ]);
    }

    private function budgetCategory(Budget $budget, ProductCategory $cat, float $amount = 2000)
    {
        return BudgetCategory::create([
            'budget_id'           => $budget->id,
            'product_category_id' => $cat->id,
            'amount'              => $amount,
            'status'              => 'active',
        ]);
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/v1/family-groups/1/budgets/1/categories')->assertStatus(401);
    }

    public function test_access_to_foreign_group_budget_is_denied()
    {
        [$user]         = $this->groupWithMember();
        [, $otherGroup] = $this->groupWithMember();
        $budget         = $this->budget($otherGroup);

        $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$otherGroup->id}/budgets/{$budget->id}/categories")
            ->assertStatus(403);
    }

    public function test_list_returns_active_categories_with_spent_amount()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group);
        $cat            = $this->productCategory();
        $this->budgetCategory($budget, $cat, 3000);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/categories")
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertArrayHasKey('spent_amount', $response->json('data.0'));
        $this->assertArrayHasKey('available_amount', $response->json('data.0'));
        $this->assertArrayHasKey('trace_id', $response->json());
    }

    public function test_create_assigns_product_category_to_budget_and_audits()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group, ['total_amount' => 10000]);
        $cat            = $this->productCategory();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/categories", [
                'product_category_id' => $cat->id,
                'amount'              => 3000,
            ])
            ->assertStatus(201);

        $this->assertEquals($cat->id, $response->json('data.product_category_id'));
        $this->assertEquals(3000.0,   $response->json('data.amount'));
        $this->assertEquals(0.0,      $response->json('data.spent_amount'));
        $this->assertDatabaseHas('budget_categories', ['budget_id' => $budget->id, 'product_category_id' => $cat->id, 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'budget_category.create']);
    }

    public function test_create_rejects_duplicate_category_for_same_budget()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group);
        $cat            = $this->productCategory();
        $this->budgetCategory($budget, $cat, 1000);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/categories", [
                'product_category_id' => $cat->id,
                'amount'              => 500,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'BUDGET_CATEGORY_DUPLICATE');
    }

    public function test_create_rejects_amount_exceeding_budget_total()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group, ['total_amount' => 5000]);
        $cat            = $this->productCategory();

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/categories", [
                'product_category_id' => $cat->id,
                'amount'              => 6000,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BUDGET_CATEGORY_EXCEEDS_TOTAL');
    }

    public function test_update_changes_amount_and_validates_budget_cap()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group, ['total_amount' => 5000]);
        $cat            = $this->productCategory();
        $bc             = $this->budgetCategory($budget, $cat, 2000);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/categories/{$bc->id}", [
                'amount' => 4500,
            ])
            ->assertStatus(200);

        $this->assertEquals(4500.0, $response->json('data.amount'));

        $this->actingAs($user)
            ->patchJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/categories/{$bc->id}", [
                'amount' => 9000,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BUDGET_CATEGORY_EXCEEDS_TOTAL');
    }

    public function test_delete_sets_status_inactive_and_audits()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group);
        $cat            = $this->productCategory();
        $bc             = $this->budgetCategory($budget, $cat, 2000);

        $this->actingAs($user)
            ->deleteJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/categories/{$bc->id}")
            ->assertStatus(204);

        $this->assertDatabaseHas('budget_categories', ['id' => $bc->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'budget_category.delete']);
    }

    public function test_spent_amount_reflects_real_purchases_in_period()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group, ['year' => 2026, 'month' => 6, 'total_amount' => 10000]);
        $cat            = $this->productCategory();
        $this->budgetCategory($budget, $cat, 5000);

        $product = Product::create([
            'name'        => 'Leche ' . uniqid(),
            'nombre'      => 'Leche',
            'brand_id'    => 0,
            'codigo'      => 'TST' . uniqid(),
            'img'         => '',
            'habilitado'  => 1,
            'supply_id'   => 0,
            'is_active'   => true,
            'status'      => 'active',
            'category_id' => $cat->id,
        ]);

        $unit = UnitMeasure::create(['name' => 'kg', 'code' => 'KG' . uniqid(), 'type' => 'mass', 'status' => 'active']);

        $purchase = Purchase::create([
            'family_group_id' => $group->id,
            'user_id'         => $user->id,
            'purchase_date'   => '2026-06-15',
            'status'          => 'confirmed',
            'actual_total'    => 1200,
        ]);

        PurchaseItem::create([
            'purchase_id'  => $purchase->id,
            'product_id'   => $product->id,
            'unit_id'      => $unit->id,
            'quantity'     => 2,
            'unit_price'   => 600,
            'total_price'  => 1200,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/categories")
            ->assertStatus(200);

        $this->assertEquals(1200.0, $response->json('data.0.spent_amount'));
        $this->assertEquals(3800.0, $response->json('data.0.available_amount'));
    }

    public function test_deleted_category_is_excluded_from_list()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group);
        $cat            = $this->productCategory();
        $bc             = $this->budgetCategory($budget, $cat, 2000);
        $bc->update(['status' => 'inactive']);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/categories")
            ->assertStatus(200);

        $this->assertCount(0, $response->json('data'));
    }
}
