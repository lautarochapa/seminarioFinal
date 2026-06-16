<?php

namespace Tests\Feature\Api\V1\HouseholdStock;

use App\AuditLog;
use App\Brand;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Product;
use App\StockItem;
use App\StockLocation;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HouseholdStockTest extends TestCase
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
            'code' => 'u_'.uniqid(),
            'name' => 'Unidad',
            'type' => 'unit',
            'symbol' => 'u',
            'status' => 'active',
        ]);
    }

    private function product(array $data = [])
    {
        $name = $data['name'] ?? 'Producto '.uniqid();
        $brandName = 'Marca '.uniqid();
        $brand = Brand::create([
            'nombre' => $brandName,
            'name' => $brandName,
            'normalized_name' => strtolower($brandName),
            'status' => 'active',
            'padre' => 0,
        ]);

        return Product::create(array_merge([
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
        ], $data));
    }

    private function location(FamilyGroup $group, array $data = [])
    {
        return StockLocation::create(array_merge([
            'family_group_id' => $group->id,
            'name' => 'Alacena '.uniqid(),
            'type' => 'pantry',
            'status' => 'active',
        ], $data));
    }

    private function stockItem(FamilyGroup $group, StockLocation $location = null, array $data = [])
    {
        return StockItem::create(array_merge([
            'family_group_id' => $group->id,
            'product_id' => $this->product()->id,
            'stock_location_id' => $location ? $location->id : null,
            'quantity' => 2,
            'unit_id' => $this->unit()->id,
            'expiration_date' => null,
            'estimated_purchase_price' => null,
            'status' => 'active',
        ], $data));
    }

    public function test_auth_required()
    {
        $this->getJson('/api/v1/family-groups/1/stock')->assertStatus(401);
    }

    public function test_internal_permission_required_for_writes()
    {
        [$owner, $group] = $this->groupWithMember('owner');
        $member = $this->addMember($group, 'member');
        $location = $this->location($group);

        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock', [
            'product_id' => $this->product()->id,
            'stock_location_id' => $location->id,
            'quantity' => 1,
            'unit_id' => $this->unit()->id,
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'FAMILY_GROUP_ACCESS_DENIED');
    }

    public function test_list_only_group_stock()
    {
        [$owner, $group] = $this->groupWithMember('owner');
        [$other, $otherGroup] = $this->groupWithMember('owner');
        $location = $this->location($group);
        $own = $this->stockItem($group, $location);
        $this->stockItem($otherGroup, $this->location($otherGroup));

        $response = $this->actingAs($owner)->getJson('/api/v1/family-groups/'.$group->id.'/stock');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_create_stock_item()
    {
        [$owner, $group] = $this->groupWithMember('owner');
        $location = $this->location($group);
        $product = $this->product();
        $unit = $this->unit();

        $response = $this->actingAs($owner)->postJson('/api/v1/family-groups/'.$group->id.'/stock', [
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => 3,
            'unit_id' => $unit->id,
            'purchase_price' => 100,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.family_group_id', $group->id)
            ->assertJsonPath('data.purchase_price', '100.00');
    }

    public function test_duplicate_updates_existing_item()
    {
        [$owner, $group] = $this->groupWithMember('owner');
        $location = $this->location($group);
        $product = $this->product();
        $unit = $this->unit();
        $item = $this->stockItem($group, $location, ['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => 2]);

        $response = $this->actingAs($owner)->postJson('/api/v1/family-groups/'.$group->id.'/stock', [
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => 5,
            'unit_id' => $unit->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $item->id)
            ->assertJsonPath('data.quantity', '7.0000');
    }

    public function test_update_stock_item()
    {
        [$owner, $group] = $this->groupWithMember('owner');
        $item = $this->stockItem($group, $this->location($group), ['quantity' => 2]);

        $this->actingAs($owner)->patchJson('/api/v1/family-groups/'.$group->id.'/stock/'.$item->id, [
            'quantity' => 4,
        ])->assertStatus(200)
            ->assertJsonPath('data.quantity', '4.0000');
    }

    public function test_delete_stock_item()
    {
        [$owner, $group] = $this->groupWithMember('owner');
        $item = $this->stockItem($group, $this->location($group));

        $this->actingAs($owner)->deleteJson('/api/v1/family-groups/'.$group->id.'/stock/'.$item->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertSoftDeleted('stock_items', ['id' => $item->id]);
    }

    public function test_access_to_other_group_stock_rejected()
    {
        [$owner, $group] = $this->groupWithMember('owner');
        [$other, $otherGroup] = $this->groupWithMember('owner');
        $item = $this->stockItem($otherGroup, $this->location($otherGroup));

        $this->actingAs($owner)->patchJson('/api/v1/family-groups/'.$group->id.'/stock/'.$item->id, [
            'quantity' => 4,
        ])->assertStatus(404)
            ->assertJsonPath('error.code', 'STOCK_ITEM_NOT_FOUND');
    }

    public function test_summary()
    {
        [$owner, $group] = $this->groupWithMember('owner');
        $location = $this->location($group, ['name' => 'Heladera']);
        $this->stockItem($group, $location, ['expiration_date' => now()->addDays(3)->toDateString()]);
        $this->stockItem($group, $location);

        $response = $this->actingAs($owner)->getJson('/api/v1/family-groups/'.$group->id.'/stock/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_items', 2)
            ->assertJsonPath('data.items_by_location.0.location_name', 'Heladera')
            ->assertJsonPath('data.expiring_soon', 1);
    }

    public function test_value()
    {
        [$owner, $group] = $this->groupWithMember('owner');
        $location = $this->location($group);
        $this->stockItem($group, $location, ['quantity' => 2, 'estimated_purchase_price' => 100]);
        $this->stockItem($group, $location, ['quantity' => 3, 'estimated_purchase_price' => null]);

        $response = $this->actingAs($owner)->getJson('/api/v1/family-groups/'.$group->id.'/stock/value');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_value', 200)
            ->assertJsonPath('data.valued_items', 1)
            ->assertJsonPath('data.currency', 'ARS');
    }

    public function test_validation_rejects_invalid_relations_and_quantity()
    {
        [$owner, $group] = $this->groupWithMember('owner');

        $this->actingAs($owner)->postJson('/api/v1/family-groups/'.$group->id.'/stock', [
            'product_id' => 999999,
            'quantity' => -1,
            'unit_id' => 999999,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_audit_and_routes()
    {
        [$owner, $group] = $this->groupWithMember('owner');
        $location = $this->location($group);

        $this->actingAs($owner)->postJson('/api/v1/family-groups/'.$group->id.'/stock', [
            'product_id' => $this->product()->id,
            'stock_location_id' => $location->id,
            'quantity' => 1,
            'unit_id' => $this->unit()->id,
        ])->assertStatus(201);

        $this->assertTrue(AuditLog::where('entity_name', 'stock_items')->where('action', 'stock-item.created')->exists());
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/family-groups/1/stock', 'GET')));
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/family-groups/1/stock/summary', 'GET')));
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/family-groups/1/stock/value', 'GET')));
    }
}
