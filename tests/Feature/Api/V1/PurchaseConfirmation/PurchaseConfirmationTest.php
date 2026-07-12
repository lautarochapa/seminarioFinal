<?php

namespace Tests\Feature\Api\V1\PurchaseConfirmation;

use App\AuditLog;
use App\Budget;
use App\BudgetAlert;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Product;
use App\Purchase;
use App\PurchaseItem;
use App\StockItem;
use App\StockMovement;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseConfirmationTest extends TestCase
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

    private function purchase(FamilyGroup $group, User $user, array $overrides = [])
    {
        return Purchase::create(array_merge([
            'family_group_id' => $group->id,
            'user_id'         => $user->id,
            'purchase_date'   => '2026-06-01',
            'status'          => 'confirmed',
        ], $overrides));
    }

    private function product()
    {
        return Product::create([
            'name'       => 'Prod_' . uniqid(),
            'nombre'     => 'Prod',
            'brand_id'   => 0,
            'codigo'     => 'C' . uniqid(),
            'img'        => '',
            'habilitado' => 1,
            'supply_id'  => 0,
            'is_active'  => true,
            'status'     => 'active',
        ]);
    }

    private function unit()
    {
        return UnitMeasure::create(['code' => 'u_' . uniqid(), 'name' => 'Unidad', 'type' => 'count', 'symbol' => 'u', 'status' => 'active']);
    }

    private function purchaseWithItem(FamilyGroup $group, User $user, array $purchaseOverrides = [])
    {
        $purchase = $this->purchase($group, $user, $purchaseOverrides);
        $product  = $this->product();
        $unit     = $this->unit();
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id'  => $product->id,
            'quantity'    => 2,
            'unit_id'     => $unit->id,
            'unit_price'  => 150,
            'total_price' => 300,
        ]);
        return [$purchase, $product, $unit];
    }

    private function budget(FamilyGroup $group, array $overrides = [])
    {
        return Budget::create(array_merge([
            'family_group_id' => $group->id,
            'year' => 2026,
            'month' => 6,
            'total_amount' => 500,
            'currency' => 'ARS',
            'status' => 'active',
        ], $overrides));
    }

    public function test_unauthenticated_confirm_is_rejected()
    {
        $this->postJson('/api/v1/family-groups/1/purchases/1/confirm')->assertStatus(401);
    }

    public function test_confirm_on_foreign_group_is_denied()
    {
        [$user]         = $this->groupWithMember();
        [, $otherGroup] = $this->groupWithMember();
        $otherUser      = User::orderBy('id')->first();
        $purchase       = $this->purchase($otherGroup, $otherUser);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$otherGroup->id}/purchases/{$purchase->id}/confirm")
            ->assertStatus(403);
    }

    public function test_confirm_purchase_without_items_returns_422()
    {
        [$user, $group] = $this->groupWithMember();
        $purchase       = $this->purchase($group, $user);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/purchases/{$purchase->id}/confirm")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PURCHASE_HAS_NO_ITEMS');
    }

    public function test_confirm_recalculates_total_and_returns_purchase()
    {
        [$user, $group]      = $this->groupWithMember();
        [$purchase]          = $this->purchaseWithItem($group, $user);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/purchases/{$purchase->id}/confirm")
            ->assertStatus(200);

        $this->assertEquals(300.0, $response->json('data.actual_total'));
        $this->assertArrayHasKey('trace_id', $response->json());
    }

    public function test_confirm_generates_budget_alert_without_storing_duplicate_spent_amount()
    {
        [$user, $group] = $this->groupWithMember();
        $budget = $this->budget($group, ['total_amount' => 300]);
        [$purchase] = $this->purchaseWithItem($group, $user);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/purchases/{$purchase->id}/confirm")
            ->assertStatus(200);

        $this->assertDatabaseHas('budget_alerts', [
            'budget_id' => $budget->id,
            'alert_type' => 'limit_exceeded',
            'severity' => 'critical',
            'status' => 'unread',
        ]);
        $this->assertFalse(array_key_exists('spent_amount', $budget->fresh()->getAttributes()));
    }

    public function test_confirm_does_not_duplicate_unread_budget_alerts()
    {
        [$user, $group] = $this->groupWithMember();
        $budget = $this->budget($group, ['total_amount' => 300]);
        BudgetAlert::create([
            'budget_id' => $budget->id,
            'alert_type' => 'limit_exceeded',
            'message' => 'Existente',
            'severity' => 'critical',
            'status' => 'unread',
            'created_at' => now(),
        ]);
        [$purchase] = $this->purchaseWithItem($group, $user);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/purchases/{$purchase->id}/confirm")
            ->assertStatus(200);

        $this->assertSame(1, BudgetAlert::where('budget_id', $budget->id)->where('alert_type', 'limit_exceeded')->count());
    }

    public function test_double_confirm_after_stock_added_is_rejected()
    {
        [$user, $group] = $this->groupWithMember();
        [$purchase]     = $this->purchaseWithItem($group, $user, ['status' => 'stock_added']);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/purchases/{$purchase->id}/confirm")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PURCHASE_ALREADY_CONFIRMED');
    }

    public function test_add_to_stock_creates_stock_item_and_movement()
    {
        [$user, $group]             = $this->groupWithMember();
        [$purchase, $product, $unit] = $this->purchaseWithItem($group, $user);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/purchases/{$purchase->id}/add-to-stock")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'stock_added');

        $this->assertDatabaseHas('stock_items', [
            'family_group_id' => $group->id,
            'product_id'      => $product->id,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'family_group_id'     => $group->id,
            'product_id'          => $product->id,
            'movement_type'       => 'purchase_entry',
            'related_purchase_id' => $purchase->id,
        ]);
    }

    public function test_add_to_stock_accumulates_quantity_on_existing_stock()
    {
        [$user, $group]             = $this->groupWithMember();
        [$purchase, $product, $unit] = $this->purchaseWithItem($group, $user);

        StockItem::create([
            'family_group_id' => $group->id,
            'product_id'      => $product->id,
            'quantity'        => 5,
            'unit_id'         => $unit->id,
            'status'          => 'active',
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/purchases/{$purchase->id}/add-to-stock")
            ->assertStatus(200);

        $this->assertDatabaseHas('stock_items', [
            'family_group_id' => $group->id,
            'product_id'      => $product->id,
            'quantity'        => 7,
        ]);
    }

    public function test_double_add_to_stock_is_rejected()
    {
        [$user, $group]             = $this->groupWithMember();
        [$purchase, $product, $unit] = $this->purchaseWithItem($group, $user, ['status' => 'stock_added']);

        $stockItem = StockItem::create([
            'family_group_id' => $group->id,
            'product_id'      => $product->id,
            'quantity'        => 2,
            'unit_id'         => $unit->id,
            'status'          => 'active',
        ]);

        PurchaseItem::where('purchase_id', $purchase->id)
            ->update(['created_stock_item_id' => $stockItem->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/purchases/{$purchase->id}/add-to-stock")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PURCHASE_STOCK_ALREADY_ADDED');
    }

    public function test_add_to_stock_logs_audit_entry()
    {
        [$user, $group]  = $this->groupWithMember();
        [$purchase]      = $this->purchaseWithItem($group, $user);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/purchases/{$purchase->id}/add-to-stock")
            ->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'purchase.stock_added',
            'entity_name' => 'purchases',
        ]);
    }

    public function test_add_to_stock_sets_created_stock_item_id_on_items()
    {
        [$user, $group]  = $this->groupWithMember();
        [$purchase]      = $this->purchaseWithItem($group, $user);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/purchases/{$purchase->id}/add-to-stock")
            ->assertStatus(200);

        $item = PurchaseItem::where('purchase_id', $purchase->id)->first();
        $this->assertNotNull($item->created_stock_item_id);
    }
}
