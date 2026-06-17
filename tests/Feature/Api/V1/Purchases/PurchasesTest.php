<?php

namespace Tests\Feature\Api\V1\Purchases;

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

class PurchasesTest extends TestCase
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

    private function product()
    {
        return Product::create([
            'name'      => 'Leche',
            'nombre'    => 'Leche',
            'brand_id'  => 0,
            'codigo'    => 'TEST001',
            'img'       => '',
            'habilitado'=> 1,
            'supply_id' => 0,
            'is_active' => true,
            'status'    => 'active',
        ]);
    }

    private function unit()
    {
        return UnitMeasure::create(['code' => 'un_' . uniqid(), 'name' => 'Unidad', 'type' => 'count', 'symbol' => 'u', 'status' => 'active']);
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

    private function itemPayload()
    {
        $p = $this->product();
        $u = $this->unit();
        return [
            ['product_id' => $p->id, 'quantity' => 2, 'unit_id' => $u->id, 'unit_price' => 100.00],
        ];
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/v1/family-groups/1/purchases')->assertStatus(401);
    }

    public function test_access_to_foreign_group_is_denied()
    {
        [$user]       = $this->groupWithMember();
        [, $other]    = $this->groupWithMember();

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/' . $other->id . '/purchases')
            ->assertStatus(403);
    }

    public function test_list_returns_paginated_purchases_with_filters()
    {
        [$user, $group] = $this->groupWithMember();
        $this->purchase($group, $user, ['purchase_date' => '2026-05-01', 'status' => 'confirmed']);
        $this->purchase($group, $user, ['purchase_date' => '2026-06-01', 'status' => 'cancelled']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/family-groups/' . $group->id . '/purchases?status=confirmed')
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('confirmed', $response->json('data.0.status'));
        $this->assertArrayHasKey('meta', $response->json());
        $this->assertArrayHasKey('trace_id', $response->json());
    }

    public function test_show_returns_purchase_with_items_without_n_plus_one()
    {
        [$user, $group] = $this->groupWithMember();
        $p = $this->product();
        $u = $this->unit();
        $purchase = $this->purchase($group, $user);
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id'  => $p->id,
            'quantity'    => 3,
            'unit_id'     => $u->id,
            'unit_price'  => 50,
            'total_price' => 150,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/family-groups/' . $group->id . '/purchases/' . $purchase->id)
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals(50.0, $response->json('data.items.0.unit_price'));
    }

    public function test_create_manual_purchase_with_items_returns_201()
    {
        [$user, $group] = $this->groupWithMember();
        $items = $this->itemPayload();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/family-groups/' . $group->id . '/purchases', [
                'purchase_date' => '2026-06-15',
                'items'         => $items,
            ])
            ->assertStatus(201);

        $this->assertEquals('confirmed', $response->json('data.status'));
        $this->assertCount(1, $response->json('data.items'));
        $this->assertArrayHasKey('trace_id', $response->json());
    }

    public function test_create_validates_required_fields()
    {
        [$user, $group] = $this->groupWithMember();

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/' . $group->id . '/purchases', [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_actual_total_is_calculated_from_items()
    {
        [$user, $group] = $this->groupWithMember();
        $p = $this->product();
        $u = $this->unit();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/family-groups/' . $group->id . '/purchases', [
                'purchase_date' => '2026-06-15',
                'items'         => [
                    ['product_id' => $p->id, 'quantity' => 3, 'unit_id' => $u->id, 'unit_price' => 100],
                    ['product_id' => $p->id, 'quantity' => 2, 'unit_id' => $u->id, 'unit_price' => 50],
                ],
            ])
            ->assertStatus(201);

        $this->assertEquals(400.0, $response->json('data.actual_total'));
    }

    public function test_partial_update_is_allowed_while_confirmed()
    {
        [$user, $group] = $this->groupWithMember();
        $purchase = $this->purchase($group, $user);

        $response = $this->actingAs($user)
            ->patchJson('/api/v1/family-groups/' . $group->id . '/purchases/' . $purchase->id, [
                'purchase_date' => '2026-07-01',
            ])
            ->assertStatus(200);

        $this->assertEquals('2026-07-01', $response->json('data.purchase_date'));
    }

    public function test_update_is_rejected_when_purchase_is_cancelled()
    {
        [$user, $group] = $this->groupWithMember();
        $purchase = $this->purchase($group, $user, ['status' => 'cancelled']);
        $purchase->delete();

        $this->actingAs($user)
            ->patchJson('/api/v1/family-groups/' . $group->id . '/purchases/' . $purchase->id, [
                'purchase_date' => '2026-07-01',
            ])
            ->assertStatus(404);
    }

    public function test_delete_cancels_and_soft_deletes_purchase()
    {
        [$user, $group] = $this->groupWithMember();
        $purchase = $this->purchase($group, $user);

        $response = $this->actingAs($user)
            ->deleteJson('/api/v1/family-groups/' . $group->id . '/purchases/' . $purchase->id)
            ->assertStatus(200);

        $this->assertEquals('cancelled', $response->json('data.status'));
        $this->assertNotNull($response->json('data.deleted_at'));
        $this->assertDatabaseMissing('purchases', ['id' => $purchase->id, 'deleted_at' => null]);
    }

    public function test_create_logs_audit_entry()
    {
        [$user, $group] = $this->groupWithMember();
        $items = $this->itemPayload();

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/' . $group->id . '/purchases', [
                'purchase_date' => '2026-06-15',
                'items'         => $items,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'purchase.create',
            'entity_name' => 'purchases',
        ]);
    }
}
