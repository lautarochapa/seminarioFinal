<?php

namespace Tests\Feature\Api\V1\ShoppingListCompletion;

use App\Brand;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Ingredient;
use App\Product;
use App\Recipe;
use App\RecipeIngredient;
use App\ShoppingList;
use App\ShoppingListItem;
use App\StockItem;
use App\StockMovement;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingListCompletionTest extends TestCase
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
            'code' => 'slc_'.uniqid(),
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

    private function product(?Ingredient $ingredient, UnitMeasure $unit)
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
            'ingredient_id' => $ingredient ? $ingredient->id : null,
            'default_unit_id' => $unit->id,
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    private function list(FamilyGroup $group, User $user, array $data = [])
    {
        return ShoppingList::create(array_merge([
            'family_group_id' => $group->id,
            'created_by' => $user->id,
            'source_type' => 'manual',
            'status' => 'active',
        ], $data));
    }

    private function item(ShoppingList $list, UnitMeasure $unit, array $data = [])
    {
        return ShoppingListItem::create(array_merge([
            'shopping_list_id' => $list->id,
            'quantity' => 2,
            'unit_id' => $unit->id,
            'status' => 'purchased',
        ], $data));
    }

    public function test_complete_with_product_creates_stock_and_movement()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $product = $this->product(null, $unit);
        $list = $this->list($group, $user);
        $item = $this->item($list, $unit, ['product_id' => $product->id, 'quantity' => 3]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/complete', [
                'items' => [
                    ['shopping_list_item_id' => $item->id, 'add_to_stock' => true],
                ],
            ])->assertStatus(200);

        $response->assertJsonPath('data.items_added_to_stock_count', 1)
            ->assertJsonPath('data.stock_items_created', 1)
            ->assertJsonPath('data.stock_movements_created', 1);

        $this->assertDatabaseHas('stock_items', [
            'family_group_id' => $group->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'family_group_id' => $group->id,
            'product_id' => $product->id,
            'movement_type' => 'entry',
            'reason' => 'shopping_list_completion',
        ]);
        $this->assertDatabaseHas('shopping_lists', ['id' => $list->id, 'status' => 'completed']);
        $this->assertNotNull(ShoppingListItem::find($item->id)->stock_processed_at);
    }

    public function test_complete_increments_compatible_existing_stock()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $product = $this->product(null, $unit);
        StockItem::create([
            'family_group_id' => $group->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_id' => $unit->id,
            'status' => 'active',
        ]);
        $list = $this->list($group, $user);
        $item = $this->item($list, $unit, ['product_id' => $product->id, 'quantity' => 3]);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/complete', [
                'items' => [
                    ['shopping_list_item_id' => $item->id, 'add_to_stock' => true],
                ],
            ])->assertStatus(200)
            ->assertJsonPath('data.stock_items_updated', 1)
            ->assertJsonPath('data.stock_items_created', 0);

        $this->assertDatabaseHas('stock_items', [
            'family_group_id' => $group->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);
        $this->assertEquals(1, StockItem::where('family_group_id', $group->id)->where('product_id', $product->id)->count());
    }

    public function test_unpurchased_item_is_not_added_to_stock()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $product = $this->product(null, $unit);
        $list = $this->list($group, $user);
        $pending = ShoppingListItem::create([
            'shopping_list_id' => $list->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_id' => $unit->id,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/complete', [
                'items' => [
                    ['shopping_list_item_id' => $pending->id, 'add_to_stock' => true],
                ],
            ])->assertStatus(200)
            ->assertJsonPath('data.items_added_to_stock_count', 0);

        $this->assertDatabaseMissing('stock_items', ['product_id' => $product->id]);
    }

    public function test_item_not_confirmed_for_stock_is_omitted()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $list = $this->list($group, $user);
        $item = $this->item($list, $unit, ['free_text_name' => 'detergente']);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/complete', [
                'items' => [
                    ['shopping_list_item_id' => $item->id, 'add_to_stock' => false],
                ],
            ])->assertStatus(200);

        $response->assertJsonPath('data.items_omitted_count', 1)
            ->assertJsonPath('data.items_added_to_stock_count', 0);
        $this->assertDatabaseMissing('stock_items', ['family_group_id' => $group->id]);
    }

    public function test_ingredient_only_item_from_recipe_requires_explicit_product_association()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $ingredient = $this->ingredient($unit);
        $product = $this->product($ingredient, $unit);
        $list = $this->list($group, $user);
        $item = $this->item($list, $unit, ['ingredient_id' => $ingredient->id, 'quantity' => 1]);

        // Without an explicit product association the backend must not guess.
        $noAssoc = $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/complete', [
                'items' => [
                    ['shopping_list_item_id' => $item->id, 'add_to_stock' => true],
                ],
            ])->assertStatus(200);
        $noAssoc->assertJsonPath('data.items_omitted_count', 1);
        $this->assertEquals('INGREDIENT_WITHOUT_PRODUCT_ASSOCIATION', $noAssoc->json('data.warnings.0.reason'));
    }

    public function test_ingredient_item_associated_to_existing_product_is_added_to_stock()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $ingredient = $this->ingredient($unit);
        $product = $this->product($ingredient, $unit);
        $list = $this->list($group, $user);
        $item = $this->item($list, $unit, ['ingredient_id' => $ingredient->id, 'quantity' => 1]);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/complete', [
                'items' => [
                    ['shopping_list_item_id' => $item->id, 'add_to_stock' => true, 'product_id' => $product->id],
                ],
            ])->assertStatus(200)
            ->assertJsonPath('data.items_added_to_stock_count', 1);

        $this->assertDatabaseHas('stock_items', ['family_group_id' => $group->id, 'product_id' => $product->id]);
    }

    public function test_free_text_item_creates_pending_product_and_stock()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $list = $this->list($group, $user);
        $item = $this->item($list, $unit, ['free_text_name' => 'detergente en polvo', 'quantity' => 1]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/complete', [
                'items' => [
                    [
                        'shopping_list_item_id' => $item->id,
                        'add_to_stock' => true,
                        'create_pending_product' => true,
                        'name' => 'Detergente en polvo',
                    ],
                ],
            ])->assertStatus(200);

        $response->assertJsonPath('data.items_added_to_stock_count', 1);

        $this->assertDatabaseHas('products', [
            'name' => 'Detergente en polvo',
            'status' => 'pending_review',
            'origin' => 'user_created',
            'family_group_id' => $group->id,
        ]);
        $product = Product::where('name', 'Detergente en polvo')->first();
        $this->assertDatabaseHas('stock_items', ['family_group_id' => $group->id, 'product_id' => $product->id]);
    }

    public function test_retrying_complete_does_not_duplicate_stock_or_purchase()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $product = $this->product(null, $unit);
        $list = $this->list($group, $user);
        $item = $this->item($list, $unit, ['product_id' => $product->id, 'quantity' => 2]);

        $body = ['items' => [['shopping_list_item_id' => $item->id, 'add_to_stock' => true]]];

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/complete', $body)
            ->assertStatus(200);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/complete', $body)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SHOPPING_LIST_ALREADY_COMPLETED');

        $this->assertEquals(1, StockItem::where('product_id', $product->id)->count());
        $this->assertEquals(1, StockMovement::where('product_id', $product->id)->count());
        $this->assertEquals(1, \App\Purchase::where('shopping_list_id', $list->id)->count());
    }

    public function test_cannot_complete_list_of_another_group()
    {
        [$user, $group] = $this->groupWithMember();
        [$other, $otherGroup] = $this->groupWithMember();
        $list = $this->list($otherGroup, $other);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/complete', [])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'SHOPPING_LIST_NOT_FOUND');
    }

    public function test_unit_mismatch_creates_separate_stock_item()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $otherUnit = $this->unit();
        $product = $this->product(null, $unit);
        StockItem::create([
            'family_group_id' => $group->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_id' => $otherUnit->id,
            'status' => 'active',
        ]);
        $list = $this->list($group, $user);
        $item = $this->item($list, $unit, ['product_id' => $product->id, 'quantity' => 2]);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/complete', [
                'items' => [['shopping_list_item_id' => $item->id, 'add_to_stock' => true]],
            ])->assertStatus(200)
            ->assertJsonPath('data.stock_items_created', 1);

        $this->assertEquals(2, StockItem::where('product_id', $product->id)->count());
    }

    public function test_keeps_actual_price_on_stock_and_purchase_item()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $product = $this->product(null, $unit);
        $list = $this->list($group, $user);
        $item = $this->item($list, $unit, ['product_id' => $product->id, 'quantity' => 2]);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/complete', [
                'items' => [
                    ['shopping_list_item_id' => $item->id, 'add_to_stock' => true, 'actual_price' => 150.5],
                ],
            ])->assertStatus(200);

        $this->assertDatabaseHas('purchase_items', ['product_id' => $product->id, 'unit_price' => 150.5]);
        $this->assertDatabaseHas('stock_items', ['product_id' => $product->id, 'estimated_purchase_price' => 150.5]);
    }

    public function test_recipe_becomes_available_after_completing_purchase()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $ingredient = $this->ingredient($unit);
        $product = $this->product($ingredient, $unit);

        $recipe = Recipe::create([
            'name' => 'Receta '.uniqid(),
            'nombre' => 'Receta',
            'normalized_name' => 'receta_'.uniqid(),
            'descripcion' => '', 'tiempo' => '', 'img' => '', 'video' => '', 'porcion' => '', 'calorias' => 0,
            'source_type' => 'user', 'status' => 'active', 'is_public' => true, 'is_official' => false,
            'is_verified' => false, 'servings' => 2,
        ]);
        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'unit_id' => $unit->id,
            'quantity' => 2,
            'is_optional' => false,
            'sort_order' => 0,
        ]);

        $before = $this->actingAs($user)
            ->getJson('/api/v1/recipes/'.$recipe->id.'/cost?family_group_id='.$group->id);
        $availabilityBefore = $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/recipes/available');
        // Sanity: recipe should not be listed as available yet (no stock).
        $this->assertNotContains($recipe->id, collect($availabilityBefore->json('data'))->pluck('id')->all());

        $list = $this->list($group, $user);
        $item = $this->item($list, $unit, ['ingredient_id' => $ingredient->id, 'quantity' => 2]);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/complete', [
                'items' => [
                    ['shopping_list_item_id' => $item->id, 'add_to_stock' => true, 'product_id' => $product->id],
                ],
            ])->assertStatus(200);

        $availabilityAfter = $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/recipes/available');
        $this->assertContains($recipe->id, collect($availabilityAfter->json('data'))->pluck('id')->all());
    }

    public function test_completed_list_cannot_be_processed_again()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $list = $this->list($group, $user, ['status' => 'completed']);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/complete', [])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SHOPPING_LIST_ALREADY_COMPLETED');
    }
}
