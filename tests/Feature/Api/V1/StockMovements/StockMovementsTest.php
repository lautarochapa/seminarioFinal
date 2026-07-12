<?php

namespace Tests\Feature\Api\V1\StockMovements;

use App\AuditLog;
use App\Brand;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Product;
use App\StockItem;
use App\StockLocation;
use App\StockMovement;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementsTest extends TestCase
{
    use RefreshDatabase;

    private function groupWithMember($role = 'owner')
    {
        $user = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id, 'status' => 'active']);

        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id' => $user->id,
            'role_in_group' => $role,
            'status' => 'active',
        ]);

        return [$user, $group];
    }

    private function addMember(FamilyGroup $group, $role = 'member')
    {
        $user = factory(User::class)->create();
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id' => $user->id,
            'role_in_group' => $role,
            'status' => 'active',
        ]);

        return $user;
    }

    private function unit()
    {
        return UnitMeasure::create([
            'code' => 'mov_'.uniqid(),
            'name' => 'Unidad',
            'type' => 'unit',
            'symbol' => 'u',
            'status' => 'active',
        ]);
    }

    private function product()
    {
        $name = 'Producto '.uniqid();
        $brandName = 'Marca '.uniqid();
        $brand = Brand::create([
            'nombre' => $brandName,
            'name' => $brandName,
            'normalized_name' => strtolower($brandName),
            'status' => 'active',
            'padre' => 0,
        ]);

        return Product::create([
            'nombre' => $name,
            'brand_id' => $brand->id,
            'codigo' => 'BC'.uniqid(),
            'img' => 'product.png',
            'habilitado' => 1,
            'supply_id' => 0,
            'name' => $name,
            'normalized_name' => strtolower($name),
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    private function location(FamilyGroup $group)
    {
        return StockLocation::create([
            'family_group_id' => $group->id,
            'name' => 'Alacena '.uniqid(),
            'type' => 'pantry',
            'status' => 'active',
        ]);
    }

    private function stockItem(FamilyGroup $group, array $data = [])
    {
        return StockItem::create(array_merge([
            'family_group_id' => $group->id,
            'product_id' => $this->product()->id,
            'stock_location_id' => $this->location($group)->id,
            'quantity' => 10,
            'unit_id' => $this->unit()->id,
            'status' => 'active',
        ], $data));
    }

    public function test_auth_required()
    {
        $this->getJson('/api/v1/family-groups/1/stock-movements')->assertStatus(401);
    }

    public function test_access_to_other_group_rejected()
    {
        [$owner, $group] = $this->groupWithMember();
        [$other, $otherGroup] = $this->groupWithMember();
        $item = $this->stockItem($otherGroup);

        $this->actingAs($owner)->postJson('/api/v1/family-groups/'.$group->id.'/stock/'.$item->id.'/consume', [
            'quantity' => 1,
        ])->assertStatus(404)
            ->assertJsonPath('error.code', 'STOCK_ITEM_NOT_FOUND');
    }

    public function test_list_movements_for_group()
    {
        [$owner, $group] = $this->groupWithMember();
        [$other, $otherGroup] = $this->groupWithMember();
        $item = $this->stockItem($group);
        $otherItem = $this->stockItem($otherGroup);

        StockMovement::create([
            'family_group_id' => $group->id,
            'stock_item_id' => $item->id,
            'product_id' => $item->product_id,
            'movement_type' => 'adjustment',
            'quantity' => 2,
            'unit_id' => $item->unit_id,
            'created_by' => $owner->id,
            'created_at' => now(),
        ]);
        StockMovement::create([
            'family_group_id' => $otherGroup->id,
            'stock_item_id' => $otherItem->id,
            'product_id' => $otherItem->product_id,
            'movement_type' => 'discard',
            'quantity' => 1,
            'unit_id' => $otherItem->unit_id,
            'created_by' => $other->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($owner)->getJson('/api/v1/family-groups/'.$group->id.'/stock-movements');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.movement_type', 'adjustment')
            ->assertJsonPath('data.0.product.id', $item->product_id);
    }

    public function test_adjust_sets_quantity_and_records_movement()
    {
        [$owner, $group] = $this->groupWithMember();
        $item = $this->stockItem($group, ['quantity' => 10]);

        $response = $this->actingAs($owner)->postJson('/api/v1/family-groups/'.$group->id.'/stock/'.$item->id.'/adjust', [
            'quantity' => 15,
            'mode' => 'set',
            'reason' => 'Conteo manual',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.quantity', '15.0000');

        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $item->id,
            'movement_type' => 'adjustment',
            'quantity' => '5.0000',
        ]);
    }

    public function test_consume_subtracts_quantity()
    {
        [$owner, $group] = $this->groupWithMember();
        $item = $this->stockItem($group, ['quantity' => 10]);

        $this->actingAs($owner)->postJson('/api/v1/family-groups/'.$group->id.'/stock/'.$item->id.'/consume', [
            'quantity' => 3,
        ])->assertStatus(200)
            ->assertJsonPath('data.quantity', '7.0000');
    }

    public function test_consume_rejects_insufficient_stock()
    {
        [$owner, $group] = $this->groupWithMember();
        $item = $this->stockItem($group, ['quantity' => 2]);

        $this->actingAs($owner)->postJson('/api/v1/family-groups/'.$group->id.'/stock/'.$item->id.'/consume', [
            'quantity' => 3,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'STOCK_INSUFFICIENT_QUANTITY');
    }

    public function test_discard_subtracts_quantity_with_reason()
    {
        [$owner, $group] = $this->groupWithMember();
        $item = $this->stockItem($group, ['quantity' => 10]);

        $this->actingAs($owner)->postJson('/api/v1/family-groups/'.$group->id.'/stock/'.$item->id.'/discard', [
            'quantity' => 2,
            'reason' => 'Vencido',
        ])->assertStatus(200)
            ->assertJsonPath('data.quantity', '8.0000');
    }

    public function test_invalid_quantity_rejected()
    {
        [$owner, $group] = $this->groupWithMember();
        $item = $this->stockItem($group);

        $this->actingAs($owner)->postJson('/api/v1/family-groups/'.$group->id.'/stock/'.$item->id.'/consume', [
            'quantity' => -1,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_operation_is_atomic_when_movement_fails()
    {
        [$owner, $group] = $this->groupWithMember();
        $item = $this->stockItem($group, ['quantity' => 10]);

        $this->actingAs($owner)->postJson('/api/v1/family-groups/'.$group->id.'/stock/'.$item->id.'/consume', [
            'quantity' => 11,
        ])->assertStatus(409);

        $this->assertDatabaseHas('stock_items', [
            'id' => $item->id,
            'quantity' => '10.0000',
        ]);
        $this->assertDatabaseMissing('stock_movements', [
            'stock_item_id' => $item->id,
        ]);
    }

    public function test_writes_are_audited()
    {
        [$owner, $group] = $this->groupWithMember();
        $item = $this->stockItem($group);

        $this->actingAs($owner)->postJson('/api/v1/family-groups/'.$group->id.'/stock/'.$item->id.'/consume', [
            'quantity' => 1,
        ])->assertStatus(200);

        $this->assertTrue(AuditLog::where('entity_name', 'stock_items')->where('action', 'stock-item.consumed')->exists());
    }
}
