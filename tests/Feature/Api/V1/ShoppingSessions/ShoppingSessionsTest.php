<?php

namespace Tests\Feature\Api\V1\ShoppingSessions;

use App\AuditLog;
use App\Brand;
use App\City;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Ingredient;
use App\Product;
use App\ProductBarcode;
use App\Purchase;
use App\ShoppingList;
use App\ShoppingListItem;
use App\ShoppingSession;
use App\ShoppingSessionScan;
use App\StockItem;
use App\StockLocation;
use App\StockMovement;
use App\SupermarketBranch;
use App\SupermarketChain;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingSessionsTest extends TestCase
{
    use RefreshDatabase;

    private function context()
    {
        $user = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id, 'status' => 'active']);
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id' => $user->id,
            'role_in_group' => 'owner',
            'status' => 'active',
        ]);
        $unit = $this->unit();
        $ingredient = $this->ingredient($unit);
        $product = $this->product($ingredient, $unit);
        $barcode = '779'.str_pad((string) $product->id, 10, '0', STR_PAD_LEFT);
        ProductBarcode::create(['product_id' => $product->id, 'barcode' => $barcode, 'type' => 'ean13', 'status' => 'active']);
        $list = ShoppingList::create(['family_group_id' => $group->id, 'created_by' => $user->id, 'source_type' => 'manual', 'status' => 'active']);
        $item = ShoppingListItem::create([
            'shopping_list_id' => $list->id,
            'ingredient_id' => $ingredient->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_id' => $unit->id,
            'status' => 'pending',
        ]);

        return [$user, $group, $list, $item, $product, $unit, $barcode];
    }

    private function unit()
    {
        return UnitMeasure::create(['code' => 'ss_'.uniqid(), 'name' => 'Unidad', 'type' => 'unit', 'symbol' => 'u', 'status' => 'active']);
    }

    private function ingredient(UnitMeasure $unit)
    {
        return Ingredient::create([
            'name' => 'Ingrediente '.uniqid(),
            'normalized_name' => 'ingrediente_'.uniqid(),
            'base_unit_id' => $unit->id,
            'is_generic' => true,
            'is_preparation' => false,
            'is_supplement' => false,
            'status' => 'active',
        ]);
    }

    private function product(Ingredient $ingredient, UnitMeasure $unit)
    {
        $name = 'Producto '.uniqid();
        $brandName = 'Marca '.uniqid();
        $brand = Brand::create(['nombre' => $brandName, 'name' => $brandName, 'normalized_name' => strtolower($brandName), 'status' => 'active', 'padre' => 0]);

        return Product::create([
            'name' => $name,
            'normalized_name' => strtolower($name),
            'nombre' => $name,
            'brand_id' => $brand->id,
            'codigo' => 'SS'.uniqid(),
            'img' => 'product.png',
            'habilitado' => 1,
            'supply_id' => 0,
            'ingredient_id' => $ingredient->id,
            'default_unit_id' => $unit->id,
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    private function branch()
    {
        $city = City::create(['name' => 'Ciudad '.uniqid(), 'province' => 'BA', 'country' => 'Argentina', 'status' => 'active']);
        $chain = SupermarketChain::create(['name' => 'Carrefour', 'code' => 'carrefour_'.uniqid(), 'status' => 'active']);

        return SupermarketBranch::create([
            'supermarket_chain_id' => $chain->id,
            'city_id' => $city->id,
            'name' => 'Sucursal',
            'address' => 'Calle 1',
            'status' => 'active',
        ]);
    }

    public function test_auth_required()
    {
        $this->postJson('/api/v1/family-groups/1/shopping-lists/1/start-session')->assertStatus(401);
    }

    public function test_access_to_other_group_rejected()
    {
        [$user, $group] = $this->context();
        [$otherUser, $otherGroup, $otherList] = $this->context();

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$otherList->id.'/start-session')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'SHOPPING_LIST_NOT_FOUND');
    }

    public function test_start_session()
    {
        [$user, $group, $list] = $this->context();

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/start-session')
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_duplicate_session_rejected()
    {
        [$user, $group, $list] = $this->context();
        ShoppingSession::create(['shopping_list_id' => $list->id, 'family_group_id' => $group->id, 'user_id' => $user->id, 'started_at' => now(), 'status' => 'active']);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/start-session')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SHOPPING_SESSION_ALREADY_ACTIVE');
    }

    public function test_scan_valid_barcode()
    {
        [$user, $group, $list, $item, $product, $unit, $barcode] = $this->context();
        $session = ShoppingSession::create(['shopping_list_id' => $list->id, 'family_group_id' => $group->id, 'user_id' => $user->id, 'started_at' => now(), 'status' => 'active']);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-sessions/'.$session->id.'/scan', [
                'barcode' => $barcode,
                'quantity' => 2,
                'price' => 100,
            ])->assertStatus(201)
            ->assertJsonPath('data.scan_result', 'matched');

        $this->assertDatabaseHas('shopping_list_items', ['id' => $item->id, 'status' => 'purchased']);
    }

    public function test_unknown_barcode_rejected()
    {
        [$user, $group, $list] = $this->context();
        $session = ShoppingSession::create(['shopping_list_id' => $list->id, 'family_group_id' => $group->id, 'user_id' => $user->id, 'started_at' => now(), 'status' => 'active']);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-sessions/'.$session->id.'/scan', ['barcode' => '999'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'SHOPPING_SCAN_PRODUCT_NOT_FOUND');
    }

    public function test_double_scan_rejected()
    {
        [$user, $group, $list, $item, $product, $unit, $barcode] = $this->context();
        $session = ShoppingSession::create(['shopping_list_id' => $list->id, 'family_group_id' => $group->id, 'user_id' => $user->id, 'started_at' => now(), 'status' => 'active']);
        ShoppingSessionScan::create(['shopping_session_id' => $session->id, 'barcode' => $barcode, 'product_id' => $product->id, 'shopping_list_item_id' => $item->id, 'quantity' => 1, 'scan_result' => 'matched']);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-sessions/'.$session->id.'/scan', ['barcode' => $barcode])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SHOPPING_SCAN_ALREADY_REGISTERED');
    }

    public function test_update_session_branch()
    {
        [$user, $group, $list] = $this->context();
        $branch = $this->branch();
        $session = ShoppingSession::create(['shopping_list_id' => $list->id, 'family_group_id' => $group->id, 'user_id' => $user->id, 'started_at' => now(), 'status' => 'active']);

        $this->actingAs($user)
            ->patchJson('/api/v1/family-groups/'.$group->id.'/shopping-sessions/'.$session->id, ['supermarket_branch_id' => $branch->id])
            ->assertStatus(200)
            ->assertJsonPath('data.supermarket_branch_id', $branch->id);
    }

    public function test_finish_session_and_double_finish()
    {
        [$user, $group, $list, $item, $product] = $this->context();
        $session = ShoppingSession::create(['shopping_list_id' => $list->id, 'family_group_id' => $group->id, 'user_id' => $user->id, 'started_at' => now(), 'status' => 'active']);
        ShoppingSessionScan::create(['shopping_session_id' => $session->id, 'barcode' => '7790000000011', 'product_id' => $product->id, 'shopping_list_item_id' => $item->id, 'quantity' => 2, 'price' => 100, 'scan_result' => 'matched']);
        $item->update(['status' => 'purchased']);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-sessions/'.$session->id.'/finish')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'finished');

        $this->assertTrue(Purchase::where('shopping_list_id', $list->id)->exists());
        $this->assertSame(Purchase::where('shopping_list_id', $list->id)->latest('id')->value('id'), $response->json('data.purchase_id'));

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-sessions/'.$session->id.'/finish')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SHOPPING_SESSION_ALREADY_FINISHED');
    }

    public function test_finish_creates_stock_item_for_matched_scan()
    {
        [$user, $group, $list, $item, $product] = $this->context();
        $session = ShoppingSession::create(['shopping_list_id' => $list->id, 'family_group_id' => $group->id, 'user_id' => $user->id, 'started_at' => now(), 'status' => 'active']);
        ShoppingSessionScan::create(['shopping_session_id' => $session->id, 'barcode' => '7790000000011', 'product_id' => $product->id, 'shopping_list_item_id' => $item->id, 'quantity' => 2, 'price' => 100, 'scan_result' => 'matched']);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-sessions/'.$session->id.'/finish')
            ->assertStatus(200);

        $response->assertJsonPath('summary.stock_created_count', 1);
        $response->assertJsonPath('summary.stock_updated_count', 0);
        $this->assertDatabaseHas('stock_items', [
            'family_group_id' => $group->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'status' => 'active',
        ]);
        $this->assertTrue(StockMovement::where('product_id', $product->id)->where('movement_type', 'entry')->exists());
    }

    public function test_finish_increments_existing_stock_for_same_product_and_unit()
    {
        [$user, $group, $list, $item, $product, $unit] = $this->context();
        StockItem::create([
            'family_group_id' => $group->id,
            'product_id' => $product->id,
            'stock_location_id' => null,
            'quantity' => 5,
            'unit_id' => $unit->id,
            'status' => 'active',
        ]);
        $session = ShoppingSession::create(['shopping_list_id' => $list->id, 'family_group_id' => $group->id, 'user_id' => $user->id, 'started_at' => now(), 'status' => 'active']);
        ShoppingSessionScan::create(['shopping_session_id' => $session->id, 'barcode' => '7790000000011', 'product_id' => $product->id, 'shopping_list_item_id' => $item->id, 'quantity' => 3, 'price' => 100, 'scan_result' => 'matched']);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-sessions/'.$session->id.'/finish')
            ->assertStatus(200);

        $response->assertJsonPath('summary.stock_created_count', 0);
        $response->assertJsonPath('summary.stock_updated_count', 1);
        $this->assertDatabaseHas('stock_items', [
            'family_group_id' => $group->id,
            'product_id' => $product->id,
            'quantity' => 8,
        ]);
        $this->assertSame(1, StockItem::where('product_id', $product->id)->count());
    }

    public function test_finish_skips_scan_without_product_with_warning()
    {
        [$user, $group, $list, $item] = $this->context();
        $session = ShoppingSession::create(['shopping_list_id' => $list->id, 'family_group_id' => $group->id, 'user_id' => $user->id, 'started_at' => now(), 'status' => 'active']);
        ShoppingSessionScan::create(['shopping_session_id' => $session->id, 'barcode' => '000', 'product_id' => null, 'shopping_list_item_id' => $item->id, 'quantity' => 1, 'scan_result' => 'matched']);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-sessions/'.$session->id.'/finish')
            ->assertStatus(200);

        $response->assertJsonPath('summary.stock_created_count', 0);
        $response->assertJsonPath('summary.stock_skipped_count', 1);
        $this->assertSame('ITEM_WITHOUT_PRODUCT', $response->json('summary.stock_warnings.0.reason'));
        $this->assertDatabaseCount('stock_items', 0);
    }

    public function test_finish_skips_zero_quantity_scan()
    {
        [$user, $group, $list, $item, $product] = $this->context();
        $session = ShoppingSession::create(['shopping_list_id' => $list->id, 'family_group_id' => $group->id, 'user_id' => $user->id, 'started_at' => now(), 'status' => 'active']);
        ShoppingSessionScan::create(['shopping_session_id' => $session->id, 'barcode' => '7790000000011', 'product_id' => $product->id, 'shopping_list_item_id' => $item->id, 'quantity' => 0, 'scan_result' => 'matched']);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-sessions/'.$session->id.'/finish')
            ->assertStatus(200);

        $response->assertJsonPath('summary.stock_skipped_count', 1);
        $this->assertSame('ZERO_QUANTITY', $response->json('summary.stock_warnings.0.reason'));
        $this->assertDatabaseCount('stock_items', 0);
    }

    public function test_finish_uses_single_group_location_as_default()
    {
        [$user, $group, $list, $item, $product] = $this->context();
        $location = StockLocation::create(['family_group_id' => $group->id, 'name' => 'Alacena', 'type' => 'pantry', 'status' => 'active']);
        $session = ShoppingSession::create(['shopping_list_id' => $list->id, 'family_group_id' => $group->id, 'user_id' => $user->id, 'started_at' => now(), 'status' => 'active']);
        ShoppingSessionScan::create(['shopping_session_id' => $session->id, 'barcode' => '7790000000011', 'product_id' => $product->id, 'shopping_list_item_id' => $item->id, 'quantity' => 2, 'price' => 100, 'scan_result' => 'matched']);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-sessions/'.$session->id.'/finish')
            ->assertStatus(200);

        $this->assertDatabaseHas('stock_items', [
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
        ]);
    }

    public function test_finish_rejects_invalid_stock_location()
    {
        [$user, $group, $list, $item, $product] = $this->context();
        $session = ShoppingSession::create(['shopping_list_id' => $list->id, 'family_group_id' => $group->id, 'user_id' => $user->id, 'started_at' => now(), 'status' => 'active']);
        ShoppingSessionScan::create(['shopping_session_id' => $session->id, 'barcode' => '7790000000011', 'product_id' => $product->id, 'shopping_list_item_id' => $item->id, 'quantity' => 2, 'price' => 100, 'scan_result' => 'matched']);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-sessions/'.$session->id.'/finish', ['stock_location_id' => 999999])
            ->assertStatus(422);
    }

    public function test_finish_computes_estimated_and_actual_purchase_totals()
    {
        [$user, $group, $list, $item, $product] = $this->context();
        $item->update(['estimated_price' => 90]);
        $session = ShoppingSession::create(['shopping_list_id' => $list->id, 'family_group_id' => $group->id, 'user_id' => $user->id, 'started_at' => now(), 'status' => 'active']);
        ShoppingSessionScan::create(['shopping_session_id' => $session->id, 'barcode' => '7790000000011', 'product_id' => $product->id, 'shopping_list_item_id' => $item->id, 'quantity' => 2, 'price' => 100, 'scan_result' => 'matched']);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-sessions/'.$session->id.'/finish')
            ->assertStatus(200);

        $this->assertDatabaseHas('purchases', [
            'shopping_list_id' => $list->id,
            'estimated_total' => 180,
            'actual_total' => 200,
        ]);
    }

    public function test_writes_are_audited()
    {
        [$user, $group, $list] = $this->context();

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/start-session')
            ->assertStatus(201);

        $this->assertTrue(AuditLog::where('entity_name', 'shopping_sessions')->where('action', 'shopping_session.started')->exists());
    }
}
