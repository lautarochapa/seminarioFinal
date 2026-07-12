<?php

namespace Tests\Feature\Api\V1\WasteReport;

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
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WasteReportTest extends TestCase
{
    use RefreshDatabase;

    private function groupWithMember()
    {
        $user = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id, 'status' => 'active']);

        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id' => $user->id,
            'role_in_group' => 'member',
            'status' => 'active',
        ]);

        return [$user, $group];
    }

    private function unit()
    {
        return UnitMeasure::create([
            'code' => 'waste_'.uniqid(),
            'name' => 'Unidad',
            'type' => 'unit',
            'symbol' => 'u',
            'status' => 'active',
        ]);
    }

    private function product($name = null)
    {
        $name = $name ?: 'Producto '.uniqid();
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

    private function location(FamilyGroup $group, $name = null)
    {
        return StockLocation::create([
            'family_group_id' => $group->id,
            'name' => $name ?: 'Alacena '.uniqid(),
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
            'estimated_purchase_price' => 100,
            'status' => 'active',
        ], $data));
    }

    private function movement(FamilyGroup $group, StockItem $item, array $data = [])
    {
        return StockMovement::create(array_merge([
            'family_group_id' => $group->id,
            'stock_item_id' => $item->id,
            'product_id' => $item->product_id,
            'movement_type' => 'discard',
            'quantity' => -2,
            'unit_id' => $item->unit_id,
            'reason' => 'Vencido',
            'created_by' => null,
            'created_at' => now(),
        ], $data));
    }

    public function test_auth_required()
    {
        $this->getJson('/api/v1/family-groups/1/reports/waste')->assertStatus(401);
    }

    public function test_access_to_other_group_rejected()
    {
        [$user, $group] = $this->groupWithMember();
        [$other, $otherGroup] = $this->groupWithMember();

        $this->actingAs($user)->getJson('/api/v1/family-groups/'.$otherGroup->id.'/reports/waste')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FAMILY_GROUP_ACCESS_DENIED');
    }

    public function test_discard_movements_are_reported()
    {
        [$user, $group] = $this->groupWithMember();
        $item = $this->stockItem($group);
        $this->movement($group, $item, ['movement_type' => 'discard', 'quantity' => -2, 'reason' => 'Roto']);

        $response = $this->actingAs($user)->getJson('/api/v1/family-groups/'.$group->id.'/reports/waste');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.reason', 'Roto')
            ->assertJsonPath('data.0.quantity', 2);
    }

    public function test_expiration_movements_are_reported()
    {
        [$user, $group] = $this->groupWithMember();
        $item = $this->stockItem($group);
        $this->movement($group, $item, ['movement_type' => 'expiration', 'quantity' => -1]);

        $this->actingAs($user)->getJson('/api/v1/family-groups/'.$group->id.'/reports/waste')
            ->assertStatus(200)
            ->assertJsonPath('data.0.type', 'expiration');
    }

    public function test_filters_by_product_reason_and_location()
    {
        [$user, $group] = $this->groupWithMember();
        $location = $this->location($group, 'Freezer');
        $product = $this->product('Arroz');
        $item = $this->stockItem($group, ['product_id' => $product->id, 'stock_location_id' => $location->id]);
        $this->movement($group, $item, ['reason' => 'Quemado']);
        $other = $this->stockItem($group);
        $this->movement($group, $other, ['reason' => 'Vencido']);

        $url = '/api/v1/family-groups/'.$group->id.'/reports/waste?product_id='.$product->id.'&reason=Quemado&stock_location_id='.$location->id;
        $response = $this->actingAs($user)->getJson($url);

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.product.id', $product->id)
            ->assertJsonPath('data.0.location.name', 'Freezer');
    }

    public function test_loss_is_calculated_when_price_exists()
    {
        [$user, $group] = $this->groupWithMember();
        $item = $this->stockItem($group, ['estimated_purchase_price' => 50]);
        $this->movement($group, $item, ['quantity' => -3]);

        $this->actingAs($user)->getJson('/api/v1/family-groups/'.$group->id.'/reports/waste')
            ->assertStatus(200)
            ->assertJsonPath('data.0.estimated_loss', 150);
    }

    public function test_items_without_price_return_null_loss()
    {
        [$user, $group] = $this->groupWithMember();
        $item = $this->stockItem($group, ['estimated_purchase_price' => null]);
        $this->movement($group, $item, ['quantity' => -3]);

        $this->actingAs($user)->getJson('/api/v1/family-groups/'.$group->id.'/reports/waste')
            ->assertStatus(200)
            ->assertJsonPath('data.0.estimated_loss', null);
    }

    public function test_totals_include_quantity_and_loss()
    {
        [$user, $group] = $this->groupWithMember();
        $first = $this->stockItem($group, ['estimated_purchase_price' => 10]);
        $second = $this->stockItem($group, ['estimated_purchase_price' => null]);
        $this->movement($group, $first, ['quantity' => -2]);
        $this->movement($group, $second, ['quantity' => -3]);

        $this->actingAs($user)->getJson('/api/v1/family-groups/'.$group->id.'/reports/waste')
            ->assertStatus(200)
            ->assertJsonPath('totals.discarded_quantity', 5)
            ->assertJsonPath('totals.estimated_loss', 20)
            ->assertJsonPath('totals.items_without_price', 1);
    }

    public function test_waste_route_is_handled_by_waste_report_controller()
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($r) {
                return $r->uri() === 'api/v1/family-groups/{id}/reports/waste'
                    && in_array('GET', $r->methods());
            })
            ->first();

        $this->assertNotNull($route, 'Route GET api/v1/family-groups/{id}/reports/waste not found.');
        $this->assertStringContainsString(
            'WasteReportController',
            $route->getActionName(),
            'Expected WasteReportController to handle the waste route, got: '.$route->getActionName()
        );
    }
}
