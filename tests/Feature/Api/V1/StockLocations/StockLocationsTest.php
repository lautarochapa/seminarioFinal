<?php

namespace Tests\Feature\Api\V1\StockLocations;

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

class StockLocationsTest extends TestCase
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

    private function location(FamilyGroup $group, array $data = [])
    {
        return StockLocation::create(array_merge([
            'family_group_id' => $group->id,
            'name' => 'Alacena '.uniqid(),
            'type' => 'pantry',
            'status' => 'active',
        ], $data));
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

    private function product()
    {
        $name = 'Producto '.uniqid();
        $brand = Brand::create([
            'nombre' => 'Marca '.uniqid(),
            'name' => 'Marca '.uniqid(),
            'normalized_name' => 'marca '.uniqid(),
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

    public function test_auth_required()
    {
        $this->getJson('/api/v1/family-groups/1/stock-locations')->assertStatus(401);
    }

    public function test_internal_permission_required_for_writes()
    {
        [$owner, $group] = $this->groupWithMember('owner');
        $member = $this->addMember($group, 'member');

        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock-locations', [
            'name' => 'Heladera',
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'FAMILY_GROUP_ACCESS_DENIED');
    }

    public function test_list_only_group_locations()
    {
        [$owner, $group] = $this->groupWithMember('owner');
        [$other, $otherGroup] = $this->groupWithMember('owner');
        $own = $this->location($group, ['name' => 'Heladera']);
        $this->location($otherGroup, ['name' => 'Freezer']);

        $response = $this->actingAs($owner)->getJson('/api/v1/family-groups/'.$group->id.'/stock-locations');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_create_location()
    {
        [$owner, $group] = $this->groupWithMember('owner');

        $response = $this->actingAs($owner)->postJson('/api/v1/family-groups/'.$group->id.'/stock-locations', [
            'name' => '  Heladera  ',
            'type' => 'cold',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Heladera')
            ->assertJsonPath('data.family_group_id', $group->id);
    }

    public function test_duplicate_name_rejected()
    {
        [$owner, $group] = $this->groupWithMember('owner');
        $this->location($group, ['name' => 'Heladera']);

        $this->actingAs($owner)->postJson('/api/v1/family-groups/'.$group->id.'/stock-locations', [
            'name' => 'Heladera',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'STOCK_LOCATION_NAME_ALREADY_EXISTS');
    }

    public function test_update_location()
    {
        [$owner, $group] = $this->groupWithMember('owner');
        $location = $this->location($group, ['name' => 'Vieja', 'type' => 'pantry']);

        $this->actingAs($owner)->patchJson('/api/v1/family-groups/'.$group->id.'/stock-locations/'.$location->id, [
            'name' => 'Nueva',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Nueva')
            ->assertJsonPath('data.type', 'pantry');
    }

    public function test_delete_location()
    {
        [$owner, $group] = $this->groupWithMember('owner');
        $location = $this->location($group);

        $this->actingAs($owner)->deleteJson('/api/v1/family-groups/'.$group->id.'/stock-locations/'.$location->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertSoftDeleted('stock_locations', ['id' => $location->id]);
    }

    public function test_access_to_other_group_location_rejected()
    {
        [$owner, $group] = $this->groupWithMember('owner');
        [$other, $otherGroup] = $this->groupWithMember('owner');
        $location = $this->location($otherGroup);

        $this->actingAs($owner)->patchJson('/api/v1/family-groups/'.$group->id.'/stock-locations/'.$location->id, [
            'name' => 'Nope',
        ])->assertStatus(404)
            ->assertJsonPath('error.code', 'STOCK_LOCATION_NOT_FOUND');
    }

    public function test_delete_preserves_stock_items()
    {
        [$owner, $group] = $this->groupWithMember('owner');
        $location = $this->location($group);
        $item = StockItem::create([
            'family_group_id' => $group->id,
            'product_id' => $this->product()->id,
            'stock_location_id' => $location->id,
            'quantity' => 1,
            'unit_id' => $this->unit()->id,
            'status' => 'active',
        ]);

        $this->actingAs($owner)->deleteJson('/api/v1/family-groups/'.$group->id.'/stock-locations/'.$location->id)
            ->assertStatus(200);

        $this->assertDatabaseHas('stock_items', ['id' => $item->id, 'stock_location_id' => $location->id]);
    }

    public function test_audit_and_routes()
    {
        [$owner, $group] = $this->groupWithMember('owner');

        $this->actingAs($owner)->postJson('/api/v1/family-groups/'.$group->id.'/stock-locations', [
            'name' => 'Freezer',
        ])->assertStatus(201);

        $this->assertTrue(AuditLog::where('entity_name', 'stock_locations')->where('action', 'stock-location.created')->exists());
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/family-groups/1/stock-locations', 'GET')));
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/family-groups/1/stock-locations/1', 'PATCH')));
    }
}
