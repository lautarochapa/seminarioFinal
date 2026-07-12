<?php

namespace Tests\Feature\Api\V1\ShoppingAlternatives;

use App\AuditLog;
use App\Brand;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Ingredient;
use App\IngredientEquivalence;
use App\Product;
use App\ShoppingList;
use App\ShoppingListItem;
use App\ShoppingListItemAlternative;
use App\SupermarketChain;
use App\SupermarketProduct;
use App\SupermarketProductPrice;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingAlternativesTest extends TestCase
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
        $ingredient = $this->ingredient($unit, 'base');
        $list = ShoppingList::create([
            'family_group_id' => $group->id,
            'created_by' => $user->id,
            'source_type' => 'manual',
            'status' => 'draft',
        ]);
        $item = ShoppingListItem::create([
            'shopping_list_id' => $list->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 1,
            'unit_id' => $unit->id,
            'status' => 'pending',
        ]);

        return [$user, $group, $list, $item, $ingredient, $unit];
    }

    private function unit()
    {
        return UnitMeasure::create([
            'code' => 'alt_'.uniqid(),
            'name' => 'Unidad',
            'type' => 'unit',
            'symbol' => 'u',
            'status' => 'active',
        ]);
    }

    private function ingredient(UnitMeasure $unit, string $suffix)
    {
        return Ingredient::create([
            'name' => 'Ingrediente '.$suffix.' '.uniqid(),
            'normalized_name' => 'ingrediente_'.$suffix.'_'.uniqid(),
            'base_unit_id' => $unit->id,
            'is_generic' => true,
            'is_preparation' => false,
            'is_supplement' => false,
            'status' => 'active',
        ]);
    }

    private function product(Ingredient $ingredient, UnitMeasure $unit, string $name = null, string $status = 'active')
    {
        $productName = $name ?: 'Producto '.uniqid();
        $brandName = 'Marca '.uniqid();
        $brand = Brand::create([
            'nombre' => $brandName,
            'name' => $brandName,
            'normalized_name' => strtolower($brandName),
            'status' => 'active',
            'padre' => 0,
        ]);

        return Product::create([
            'name' => $productName,
            'normalized_name' => strtolower($productName),
            'nombre' => $productName,
            'brand_id' => $brand->id,
            'codigo' => 'ALT'.uniqid(),
            'img' => 'product.png',
            'habilitado' => $status === 'active' ? 1 : 0,
            'supply_id' => 0,
            'ingredient_id' => $ingredient->id,
            'default_unit_id' => $unit->id,
            'is_active' => $status === 'active',
            'status' => $status,
        ]);
    }

    private function price(Product $product, float $price)
    {
        $chain = SupermarketChain::create([
            'name' => 'Cadena '.uniqid(),
            'code' => 'chain_'.uniqid(),
            'status' => 'active',
        ]);
        $supermarketProduct = SupermarketProduct::create([
            'product_id' => $product->id,
            'supermarket_chain_id' => $chain->id,
            'status' => 'active',
        ]);
        SupermarketProductPrice::create([
            'supermarket_product_id' => $supermarketProduct->id,
            'price' => $price,
            'unit_price' => $price,
            'currency' => 'ARS',
            'status' => 'active',
            'scraped_at' => now(),
        ]);

        return $supermarketProduct;
    }

    public function test_auth_required()
    {
        $this->getJson('/api/v1/family-groups/1/shopping-lists/1/alternatives')->assertStatus(401);
    }

    public function test_access_to_other_group_rejected()
    {
        [$user, $group] = $this->context();
        [$otherUser, $otherGroup, $otherList] = $this->context();

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$otherList->id.'/alternatives')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'SHOPPING_LIST_NOT_FOUND');
    }

    public function test_alternatives_by_same_ingredient()
    {
        [$user, $group, $list, $item, $ingredient, $unit] = $this->context();
        $this->price($this->product($ingredient, $unit, 'Opcion misma'), 100);

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/alternatives')
            ->assertStatus(200)
            ->assertJsonPath('data.0.item_id', $item->id)
            ->assertJsonPath('data.0.alternatives.0.reason', 'equivalent');
    }

    public function test_cheaper_reason_uses_price()
    {
        [$user, $group, $list, $item, $ingredient, $unit] = $this->context();
        $item->update(['estimated_price' => 150]);
        $this->price($this->product($ingredient, $unit, 'Opcion barata'), 90);

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/alternatives')
            ->assertStatus(200)
            ->assertJsonPath('data.0.alternatives.0.reason', 'cheaper');
    }

    public function test_incompatible_products_excluded()
    {
        [$user, $group, $list, $item, $ingredient, $unit] = $this->context();
        $otherIngredient = $this->ingredient($unit, 'other');
        $this->price($this->product($otherIngredient, $unit, 'Incompatible'), 10);

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/alternatives')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.0.alternatives');
    }

    public function test_select_valid_alternative()
    {
        [$user, $group, $list, $item, $ingredient, $unit] = $this->context();
        $product = $this->product($ingredient, $unit);
        $supermarketProduct = $this->price($product, 88);
        $alternative = ShoppingListItemAlternative::create([
            'shopping_list_item_id' => $item->id,
            'product_id' => $product->id,
            'supermarket_product_id' => $supermarketProduct->id,
            'price' => 88,
            'reason' => 'equivalent',
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items/'.$item->id.'/select-alternative', [
                'alternative_id' => $alternative->id,
            ])->assertStatus(200)
            ->assertJsonPath('data.product.id', $product->id)
            ->assertJsonPath('data.estimated_price', '88.00');
    }

    public function test_invalid_alternative_rejected()
    {
        [$user, $group, $list, $item] = $this->context();

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items/'.$item->id.'/select-alternative', [
                'alternative_id' => 999999,
            ])->assertStatus(404)
            ->assertJsonPath('error.code', 'SHOPPING_ALTERNATIVE_NOT_FOUND');
    }

    public function test_selection_is_atomic()
    {
        [$user, $group, $list, $item, $ingredient, $unit] = $this->context();
        $product = $this->product($ingredient, $unit);
        $supermarketProduct = $this->price($product, 88);
        $alternative = ShoppingListItemAlternative::create([
            'shopping_list_item_id' => $item->id,
            'product_id' => $product->id,
            'supermarket_product_id' => $supermarketProduct->id,
            'price' => 88,
            'reason' => 'equivalent',
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items/'.$item->id.'/select-alternative', [
                'alternative_id' => $alternative->id,
            ])->assertStatus(200);

        $this->assertDatabaseHas('shopping_list_item_alternatives', ['id' => $alternative->id, 'is_selected' => true]);
        $this->assertDatabaseHas('shopping_list_items', ['id' => $item->id, 'product_id' => $product->id]);
    }

    public function test_writes_are_audited()
    {
        [$user, $group, $list, $item, $ingredient, $unit] = $this->context();
        $product = $this->product($ingredient, $unit);
        $supermarketProduct = $this->price($product, 88);
        $alternative = ShoppingListItemAlternative::create([
            'shopping_list_item_id' => $item->id,
            'product_id' => $product->id,
            'supermarket_product_id' => $supermarketProduct->id,
            'price' => 88,
            'reason' => 'equivalent',
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/items/'.$item->id.'/select-alternative', [
                'alternative_id' => $alternative->id,
            ])->assertStatus(200);

        $this->assertTrue(AuditLog::where('entity_name', 'shopping_list_items')->where('action', 'shopping_list_item.alternative_selected')->exists());
    }
}
