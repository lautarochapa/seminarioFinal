<?php

namespace Tests\Feature\Api\V1\HouseholdStock;

use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Product;
use App\ProductBarcode;
use App\ProductRequest;
use App\StockItem;
use App\StockLocation;
use App\StockMovement;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualProductStockTest extends TestCase
{
    use RefreshDatabase;

    private function groupWithMember($role = 'member')
    {
        $owner = factory(User::class)->create();
        $user = $role === 'owner' ? $owner : factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $owner->id, 'status' => 'active']);

        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id' => $user->id,
            'role_in_group' => $role,
            'status' => 'active',
        ]);

        return [$user, $group];
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

    private function location(FamilyGroup $group)
    {
        return StockLocation::create([
            'family_group_id' => $group->id,
            'name' => 'Alacena '.uniqid(),
            'type' => 'pantry',
            'status' => 'active',
        ]);
    }

    private function activeProduct(UnitMeasure $unit, $barcode = '7791234567890')
    {
        $product = Product::create([
            'name' => 'Leche existente '.uniqid(),
            'normalized_name' => 'leche existente '.uniqid(),
            'brand_id' => 0,
            'default_unit_id' => $unit->id,
            'status' => 'active',
            'is_active' => true,
            'nombre' => 'Leche existente',
            'codigo' => $barcode,
            'img' => '',
            'habilitado' => 1,
            'supply_id' => 0,
        ]);

        ProductBarcode::create([
            'product_id' => $product->id,
            'barcode' => $barcode,
            'status' => 'active',
        ]);

        return $product;
    }

    public function test_user_creates_pending_product_stock_movement_and_request()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $location = $this->location($group);

        $response = $this->actingAs($user)->postJson('/api/v1/family-groups/'.$group->id.'/stock/manual-product', [
            'product' => [
                'name' => 'Leche de almendras',
                'barcode' => '7790000000011',
                'unit_id' => $unit->id,
            ],
            'stock' => [
                'quantity' => 2,
                'unit_id' => $unit->id,
                'stock_location_id' => $location->id,
                'purchase_price' => 1500,
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.review_status', 'pending_review')
            ->assertJsonPath('data.matched_existing_product', false)
            ->assertJsonPath('data.stock_item.quantity', '2.0000');

        $productId = $response->json('data.product.id');
        $stockItemId = $response->json('data.stock_item.id');

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'status' => 'pending_review',
            'origin' => 'user_created',
            'family_group_id' => $group->id,
        ]);
        $this->assertDatabaseHas('stock_items', ['id' => $stockItemId, 'product_id' => $productId]);
        $this->assertDatabaseHas('stock_movements', ['stock_item_id' => $stockItemId, 'movement_type' => 'manual_product_created']);
        $this->assertDatabaseHas('product_requests', ['product_id' => $productId, 'status' => ProductRequest::STATUS_PENDING]);
    }

    public function test_existing_active_barcode_uses_existing_product()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $product = $this->activeProduct($unit, '7790000000022');

        $response = $this->actingAs($user)->postJson('/api/v1/family-groups/'.$group->id.'/stock/manual-product', [
            'product' => [
                'name' => 'Nombre ingresado',
                'barcode' => '7790000000022',
                'unit_id' => $unit->id,
            ],
            'stock' => [
                'quantity' => 1,
                'unit_id' => $unit->id,
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.product.id', $product->id)
            ->assertJsonPath('data.matched_existing_product', true)
            ->assertJsonPath('data.review_status', 'approved');

        $this->assertSame(0, Product::where('status', 'pending_review')->count());
        $this->assertDatabaseMissing('product_requests', ['name' => 'Nombre ingresado']);
    }

    public function test_pending_barcode_in_same_group_is_reused_and_accumulates_stock()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();

        $payload = [
            'product' => [
                'name' => 'Yogur manual',
                'barcode' => '7790000000033',
                'unit_id' => $unit->id,
            ],
            'stock' => [
                'quantity' => 1,
                'unit_id' => $unit->id,
            ],
        ];

        $first = $this->actingAs($user)->postJson('/api/v1/family-groups/'.$group->id.'/stock/manual-product', $payload);
        $second = $this->actingAs($user)->postJson('/api/v1/family-groups/'.$group->id.'/stock/manual-product', $payload);

        $first->assertStatus(201);
        $second->assertStatus(200)
            ->assertJsonPath('data.reused_pending_product', true)
            ->assertJsonPath('data.stock_item.quantity', '2.0000');

        $this->assertSame(1, Product::where('status', 'pending_review')->count());
        $this->assertSame(1, StockItem::count());
        $this->assertSame(2, StockMovement::count());
    }

    public function test_barcode_pending_in_another_group_returns_409()
    {
        [$user, $group] = $this->groupWithMember();
        [$otherUser, $otherGroup] = $this->groupWithMember();
        $unit = $this->unit();

        // El otro grupo ya tiene un producto pendiente con este barcode.
        $this->actingAs($otherUser)->postJson('/api/v1/family-groups/'.$otherGroup->id.'/stock/manual-product', [
            'product' => ['name' => 'Producto de otro grupo', 'barcode' => '7790000000099', 'unit_id' => $unit->id],
            'stock' => ['quantity' => 1, 'unit_id' => $unit->id],
        ])->assertStatus(201);

        $response = $this->actingAs($user)->postJson('/api/v1/family-groups/'.$group->id.'/stock/manual-product', [
            'product' => ['name' => 'Mismo barcode', 'barcode' => '7790000000099', 'unit_id' => $unit->id],
            'stock' => ['quantity' => 1, 'unit_id' => $unit->id],
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'PRODUCT_BARCODE_ALREADY_EXISTS');
    }

    public function test_validation_error_returns_422()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();

        $this->actingAs($user)->postJson('/api/v1/family-groups/'.$group->id.'/stock/manual-product', [
            'product' => ['barcode' => '7790000000055', 'unit_id' => $unit->id], // falta 'name'
            'stock' => ['quantity' => 1, 'unit_id' => $unit->id],
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_client_supplied_family_group_id_is_rejected()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();

        $this->actingAs($user)->postJson('/api/v1/family-groups/'.$group->id.'/stock/manual-product', [
            'product' => ['name' => 'Producto', 'unit_id' => $unit->id],
            'stock' => ['quantity' => 1, 'unit_id' => $unit->id],
            'family_group_id' => 999999,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_invalid_location_returns_404()
    {
        [$user, $group] = $this->groupWithMember();
        [, $otherGroup] = $this->groupWithMember();
        $unit = $this->unit();
        $otherLocation = $this->location($otherGroup);

        $this->actingAs($user)->postJson('/api/v1/family-groups/'.$group->id.'/stock/manual-product', [
            'product' => ['name' => 'Producto manual', 'unit_id' => $unit->id],
            'stock' => ['quantity' => 1, 'unit_id' => $unit->id, 'stock_location_id' => $otherLocation->id],
        ])->assertStatus(404)
            ->assertJsonPath('error.code', 'STOCK_LOCATION_NOT_FOUND');
    }

    public function test_foreign_group_is_forbidden()
    {
        [$user] = $this->groupWithMember();
        [, $otherGroup] = $this->groupWithMember();
        $unit = $this->unit();

        $this->actingAs($user)->postJson('/api/v1/family-groups/'.$otherGroup->id.'/stock/manual-product', [
            'product' => ['name' => 'Producto manual', 'unit_id' => $unit->id],
            'stock' => ['quantity' => 1, 'unit_id' => $unit->id],
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'FAMILY_GROUP_ACCESS_DENIED');
    }

    public function test_search_returns_own_pending_but_not_foreign_pending()
    {
        [$user, $group] = $this->groupWithMember();
        [, $otherGroup] = $this->groupWithMember();
        $unit = $this->unit();

        Product::create([
            'name' => 'Pendiente visible',
            'normalized_name' => 'pendiente visible g'.$group->id,
            'brand_id' => 0,
            'default_unit_id' => $unit->id,
            'status' => 'pending_review',
            'origin' => 'user_created',
            'family_group_id' => $group->id,
            'is_active' => true,
            'nombre' => 'Pendiente visible',
            'codigo' => 'manual-visible',
            'img' => '',
            'habilitado' => 1,
            'supply_id' => 0,
        ]);

        Product::create([
            'name' => 'Pendiente ajeno',
            'normalized_name' => 'pendiente ajeno g'.$otherGroup->id,
            'brand_id' => 0,
            'default_unit_id' => $unit->id,
            'status' => 'pending_review',
            'origin' => 'user_created',
            'family_group_id' => $otherGroup->id,
            'is_active' => true,
            'nombre' => 'Pendiente ajeno',
            'codigo' => 'manual-ajeno',
            'img' => '',
            'habilitado' => 1,
            'supply_id' => 0,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/products?family_group_id='.$group->id.'&search=Pendiente&per_page=20');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Pendiente visible', $names);
        $this->assertNotContains('Pendiente ajeno', $names);
    }
}
