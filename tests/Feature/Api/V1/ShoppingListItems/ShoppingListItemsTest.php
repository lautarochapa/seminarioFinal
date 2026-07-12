<?php

namespace Tests\Feature\Api\V1\ShoppingListItems;

use App\AuditLog;
use App\Brand;
use App\City;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Ingredient;
use App\Product;
use App\ShoppingList;
use App\ShoppingListItem;
use App\SupermarketBranch;
use App\SupermarketChain;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingListItemsTest extends TestCase
{
    use RefreshDatabase;

    private function groupWithMember()
    {
        $user = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id, 'status' => 'active']);
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id' => $user->id,
            'role_in_group' => 'owner',
            'status' => 'active',
        ]);

        return [$user, $group];
    }

    private function unit()
    {
        return UnitMeasure::create([
            'code' => 'sli_'.uniqid(),
            'name' => 'Unidad',
            'type' => 'unit',
            'symbol' => 'u',
            'status' => 'active',
        ]);
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
        $brand = Brand::create([
            'nombre' => $brandName,
            'name' => $brandName,
            'normalized_name' => strtolower($brandName),
            'status' => 'active',
            'padre' => 0,
        ]);

        return Product::create([
            'name' => $name,
            'normalized_name' => strtolower($name),
            'nombre' => $name,
            'brand_id' => $brand->id,
            'codigo' => 'BC'.uniqid(),
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
        $chain = SupermarketChain::create(['name' => 'Chain '.uniqid(), 'code' => 'ch_'.uniqid(), 'status' => 'active']);
        $city = City::create(['name' => 'City '.uniqid(), 'province' => 'Prov', 'country' => 'Argentina', 'status' => 'active']);

        return SupermarketBranch::create([
            'supermarket_chain_id' => $chain->id,
            'city_id' => $city->id,
            'name' => 'Sucursal '.uniqid(),
            'address' => 'Calle 1',
            'status' => 'active',
        ]);
    }

    private function list(FamilyGroup $group, User $user)
    {
        return ShoppingList::create([
            'family_group_id' => $group->id,
            'created_by' => $user->id,
            'source_type' => 'manual',
            'status' => 'draft',
        ]);
    }

    private function item(ShoppingList $list, Ingredient $ingredient, UnitMeasure $unit, array $data = [])
    {
        return ShoppingListItem::create(array_merge([
            'shopping_list_id' => $list->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 2,
            'unit_id' => $unit->id,
            'status' => 'pending',
        ], $data));
    }

    public function test_auth_required()
    {
        $this->getJson('/api/v1/family-groups/1/shopping-lists/1/items')->assertStatus(401);
    }

    public function test_access_to_other_group_rejected()
    {
        [$user, $group] = $this->groupWithMember();
        [$other, $otherGroup] = $this->groupWithMember();
        $list = $this->list($otherGroup, $other);

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'SHOPPING_LIST_NOT_FOUND');
    }

    public function test_list_items()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $ingredient = $this->ingredient($unit);
        $list = $this->list($group, $user);
        $this->item($list, $ingredient, $unit);

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ingredient.id', $ingredient->id);
    }

    public function test_create_item()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $ingredient = $this->ingredient($unit);
        $list = $this->list($group, $user);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items', [
                'ingredient_id' => $ingredient->id,
                'quantity' => 3,
                'unit_id' => $unit->id,
            ])->assertStatus(201)
            ->assertJsonPath('data.quantity', '3.0000');
    }

    public function test_create_free_text_item_without_catalog_reference()
    {
        [$user, $group] = $this->groupWithMember();
        $this->unit();
        $list = $this->list($group, $user);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items', [
                'free_text_name' => 'detergente',
                'notes' => 'cualquier marca',
            ])->assertStatus(201)
            ->assertJsonPath('data.free_text_name', 'detergente')
            ->assertJsonPath('data.display_name', 'detergente');

        $this->assertDatabaseHas('shopping_list_items', [
            'shopping_list_id' => $list->id,
            'free_text_name' => 'detergente',
            'status' => 'pending',
        ]);
    }

    public function test_free_text_item_can_be_updated_purchased_and_deleted()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $list = $this->list($group, $user);
        $item = ShoppingListItem::create([
            'shopping_list_id' => $list->id,
            'free_text_name' => 'servilletas',
            'quantity' => 1,
            'unit_id' => $unit->id,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items/'.$item->id, [
                'free_text_name' => 'servilletas grandes',
                'status' => 'purchased',
            ])->assertStatus(200)
            ->assertJsonPath('data.display_name', 'servilletas grandes')
            ->assertJsonPath('data.status', 'purchased');

        $this->actingAs($user)
            ->deleteJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items/'.$item->id)
            ->assertStatus(204);

        $this->assertDatabaseMissing('shopping_list_items', ['id' => $item->id]);
    }

    public function test_validations()
    {
        [$user, $group] = $this->groupWithMember();
        $list = $this->list($group, $user);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items', [
            'quantity' => 0,
            ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_duplicate_rejected()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $ingredient = $this->ingredient($unit);
        $list = $this->list($group, $user);
        $this->item($list, $ingredient, $unit);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items', [
                'ingredient_id' => $ingredient->id,
                'quantity' => 1,
                'unit_id' => $unit->id,
            ])->assertStatus(409)
            ->assertJsonPath('error.code', 'SHOPPING_LIST_ITEM_DUPLICATE');
    }

    public function test_update_partial()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $ingredient = $this->ingredient($unit);
        $list = $this->list($group, $user);
        $item = $this->item($list, $ingredient, $unit);

        $this->actingAs($user)
            ->patchJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items/'.$item->id, [
                'quantity' => 4,
                'estimated_price' => 120.50,
            ])->assertStatus(200)
            ->assertJsonPath('data.quantity', '4.0000')
            ->assertJsonPath('data.estimated_price', '120.50');
    }

    public function test_update_quantity_only_keeps_existing_price_and_source()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $ingredient = $this->ingredient($unit);
        $product = $this->product($ingredient, $unit);
        $list = $this->list($group, $user);
        $item = $this->item($list, $ingredient, $unit, [
            'product_id' => $product->id,
            'estimated_price' => 100,
            'price_source' => 'branch',
            'price_updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items/'.$item->id, [
                'quantity' => 5,
            ])->assertStatus(200)
            ->assertJsonPath('data.price_source', 'branch')
            ->assertJsonPath('data.estimated_price', '100.00')
            ->assertJsonPath('data.estimated_subtotal', 500);
    }

    public function test_update_product_invalidates_generated_price_estimation()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $ingredient = $this->ingredient($unit);
        $product = $this->product($ingredient, $unit);
        $otherProduct = $this->product($ingredient, $unit);
        $branch = $this->branch();
        $list = $this->list($group, $user);
        $item = $this->item($list, $ingredient, $unit, [
            'product_id' => $product->id,
            'estimated_price' => 100,
            'price_source' => 'branch',
            'price_updated_at' => now(),
            'supermarket_branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($user)
            ->patchJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items/'.$item->id, [
                'product_id' => $otherProduct->id,
            ])->assertStatus(200);

        $response->assertJsonPath('data.price_source', 'manual');
        $this->assertNull($response->json('data.estimated_price'));
        $this->assertDatabaseHas('shopping_list_items', [
            'id' => $item->id,
            'product_id' => $otherProduct->id,
            'price_source' => 'manual',
            'estimated_price' => null,
            'supermarket_branch_id' => null,
        ]);
    }

    public function test_update_unit_invalidates_generated_price_estimation()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $otherUnit = $this->unit();
        $ingredient = $this->ingredient($unit);
        $product = $this->product($ingredient, $unit);
        $list = $this->list($group, $user);
        $item = $this->item($list, $ingredient, $unit, [
            'product_id' => $product->id,
            'estimated_price' => 100,
            'price_source' => 'branch',
            'price_updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items/'.$item->id, [
                'unit_id' => $otherUnit->id,
            ])->assertStatus(200)
            ->assertJsonPath('data.price_source', 'manual');

        $this->assertNull(ShoppingListItem::find($item->id)->estimated_price);
    }

    public function test_update_product_with_explicit_price_keeps_that_price_but_marks_manual()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $ingredient = $this->ingredient($unit);
        $product = $this->product($ingredient, $unit);
        $otherProduct = $this->product($ingredient, $unit);
        $list = $this->list($group, $user);
        $item = $this->item($list, $ingredient, $unit, [
            'product_id' => $product->id,
            'estimated_price' => 100,
            'price_source' => 'branch',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items/'.$item->id, [
                'product_id' => $otherProduct->id,
                'estimated_price' => 250,
            ])->assertStatus(200)
            ->assertJsonPath('data.price_source', 'manual')
            ->assertJsonPath('data.estimated_price', '250.00');
    }

    public function test_status_change_and_delete()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $ingredient = $this->ingredient($unit);
        $list = $this->list($group, $user);
        $item = $this->item($list, $ingredient, $unit);

        $this->actingAs($user)
            ->patchJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items/'.$item->id, [
                'status' => 'purchased',
            ])->assertStatus(200)
            ->assertJsonPath('data.status', 'purchased');

        $this->actingAs($user)
            ->deleteJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items/'.$item->id)
            ->assertStatus(204);

        $this->assertDatabaseMissing('shopping_list_items', ['id' => $item->id]);
    }

    public function test_writes_are_audited()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $ingredient = $this->ingredient($unit);
        $list = $this->list($group, $user);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items', [
                'ingredient_id' => $ingredient->id,
                'quantity' => 3,
                'unit_id' => $unit->id,
            ])->assertStatus(201);

        $this->assertTrue(AuditLog::where('entity_name', 'shopping_list_items')->where('action', 'shopping_list_item.created')->exists());
    }
}
