<?php

namespace Tests\Feature\Api\V1\PurchaseItems;

use App\AuditLog;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Product;
use App\Purchase;
use App\PurchaseItem;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseItemsTest extends TestCase
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

    private function product(string $status = 'active')
    {
        return Product::create([
            'name'       => 'Leche ' . uniqid(),
            'nombre'     => 'Leche',
            'brand_id'   => 0,
            'codigo'     => 'TST' . uniqid(),
            'img'        => '',
            'habilitado' => 1,
            'supply_id'  => 0,
            'is_active'  => true,
            'status'     => $status,
        ]);
    }

    private function unit()
    {
        return UnitMeasure::create([
            'code'   => 'u_' . uniqid(),
            'name'   => 'Unidad',
            'type'   => 'count',
            'symbol' => 'u',
            'status' => 'active',
        ]);
    }

    private function item(Purchase $purchase, Product $product, UnitMeasure $unit, array $overrides = [])
    {
        return PurchaseItem::create(array_merge([
            'purchase_id' => $purchase->id,
            'product_id'  => $product->id,
            'quantity'    => 2,
            'unit_id'     => $unit->id,
            'unit_price'  => 100,
            'total_price' => 200,
        ], $overrides));
    }

    private function baseUrl(int $groupId, int $purchaseId)
    {
        return "/api/v1/family-groups/{$groupId}/purchases/{$purchaseId}/items";
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/v1/family-groups/1/purchases/1/items')->assertStatus(401);
    }

    public function test_access_to_foreign_group_purchase_is_denied()
    {
        [$user]         = $this->groupWithMember();
        [, $otherGroup] = $this->groupWithMember();
        $otherUser      = User::first();
        $purchase       = $this->purchase($otherGroup, $otherUser);

        $this->actingAs($user)
            ->getJson($this->baseUrl($otherGroup->id, $purchase->id))
            ->assertStatus(403);
    }

    public function test_list_returns_items_for_purchase()
    {
        [$user, $group] = $this->groupWithMember();
        $purchase       = $this->purchase($group, $user);
        $product        = $this->product();
        $unit           = $this->unit();
        $this->item($purchase, $product, $unit);

        $response = $this->actingAs($user)
            ->getJson($this->baseUrl($group->id, $purchase->id))
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertArrayHasKey('trace_id', $response->json());
    }

    public function test_create_item_returns_201_recalculates_total_and_logs_audit()
    {
        [$user, $group] = $this->groupWithMember();
        $purchase       = $this->purchase($group, $user);
        $product        = $this->product();
        $unit           = $this->unit();

        $response = $this->actingAs($user)
            ->postJson($this->baseUrl($group->id, $purchase->id), [
                'product_id' => $product->id,
                'quantity'   => 3,
                'unit_id'    => $unit->id,
                'unit_price' => 50,
            ])
            ->assertStatus(201);

        $this->assertEquals(150.0, $response->json('data.total_price'));
        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'actual_total' => 150]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'purchase_item.create',
            'entity_name' => 'purchase_items',
        ]);
    }

    public function test_create_validates_required_fields()
    {
        [$user, $group] = $this->groupWithMember();
        $purchase       = $this->purchase($group, $user);

        $this->actingAs($user)
            ->postJson($this->baseUrl($group->id, $purchase->id), [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_create_rejects_inactive_product()
    {
        [$user, $group] = $this->groupWithMember();
        $purchase       = $this->purchase($group, $user);
        $product        = $this->product('inactive');
        $unit           = $this->unit();

        $this->actingAs($user)
            ->postJson($this->baseUrl($group->id, $purchase->id), [
                'product_id' => $product->id,
                'quantity'   => 1,
                'unit_id'    => $unit->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PURCHASE_ITEM_PRODUCT_INACTIVE');
    }

    public function test_update_recalculates_item_and_purchase_totals()
    {
        [$user, $group] = $this->groupWithMember();
        $purchase       = $this->purchase($group, $user, ['actual_total' => 200]);
        $product        = $this->product();
        $unit           = $this->unit();
        $existingItem   = $this->item($purchase, $product, $unit, ['quantity' => 2, 'unit_price' => 100, 'total_price' => 200]);

        $response = $this->actingAs($user)
            ->patchJson($this->baseUrl($group->id, $purchase->id) . '/' . $existingItem->id, [
                'quantity'   => 5,
                'unit_price' => 20,
            ])
            ->assertStatus(200);

        $this->assertEquals(100.0, $response->json('data.total_price'));
        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'actual_total' => 100]);
    }

    public function test_delete_removes_item_and_recalculates_purchase_total()
    {
        [$user, $group] = $this->groupWithMember();
        $purchase       = $this->purchase($group, $user);
        $product        = $this->product();
        $unit           = $this->unit();
        $item1          = $this->item($purchase, $product, $unit, ['quantity' => 2, 'unit_price' => 100, 'total_price' => 200]);
        $item2          = $this->item($purchase, $product, $unit, ['quantity' => 1, 'unit_price' => 50,  'total_price' => 50]);

        $this->actingAs($user)
            ->deleteJson($this->baseUrl($group->id, $purchase->id) . '/' . $item1->id)
            ->assertStatus(204);

        $this->assertDatabaseMissing('purchase_items', ['id' => $item1->id]);
        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'actual_total' => 50]);
    }

    public function test_cannot_modify_item_on_cancelled_purchase()
    {
        [$user, $group] = $this->groupWithMember();
        $purchase       = $this->purchase($group, $user, ['status' => 'cancelled']);
        $purchase->delete();
        $product        = $this->product();
        $unit           = $this->unit();

        $this->actingAs($user)
            ->postJson($this->baseUrl($group->id, $purchase->id), [
                'product_id' => $product->id,
                'quantity'   => 1,
                'unit_id'    => $unit->id,
            ])
            ->assertStatus(404);
    }

}
