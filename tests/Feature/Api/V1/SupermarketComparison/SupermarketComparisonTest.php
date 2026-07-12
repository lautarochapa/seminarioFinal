<?php

namespace Tests\Feature\Api\V1\SupermarketComparison;

use App\Brand;
use App\City;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Ingredient;
use App\Product;
use App\Promotion;
use App\ShoppingList;
use App\ShoppingListItem;
use App\SupermarketBranch;
use App\SupermarketChain;
use App\SupermarketProduct;
use App\SupermarketProductPrice;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupermarketComparisonTest extends TestCase
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
        $unit = $this->unit('u');
        $ingredient = $this->ingredient($unit);
        $product = $this->product($ingredient, $unit);
        $list = ShoppingList::create([
            'family_group_id' => $group->id,
            'created_by' => $user->id,
            'source_type' => 'manual',
            'status' => 'draft',
        ]);
        $item = ShoppingListItem::create([
            'shopping_list_id' => $list->id,
            'ingredient_id' => $ingredient->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_id' => $unit->id,
            'status' => 'pending',
        ]);

        return [$user, $group, $list, $item, $product, $unit];
    }

    private function unit(string $suffix)
    {
        return UnitMeasure::create([
            'code' => 'cmp_'.$suffix.'_'.uniqid(),
            'name' => 'Unidad '.$suffix,
            'type' => 'unit',
            'symbol' => $suffix,
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
            'codigo' => 'CMP'.uniqid(),
            'img' => 'product.png',
            'habilitado' => 1,
            'supply_id' => 0,
            'ingredient_id' => $ingredient->id,
            'default_unit_id' => $unit->id,
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    private function branch(string $chainName, string $code)
    {
        $city = City::create([
            'name' => 'Ciudad '.uniqid(),
            'province' => 'BA',
            'country' => 'Argentina',
            'status' => 'active',
        ]);
        $chain = SupermarketChain::create([
            'name' => $chainName,
            'code' => $code.'_'.uniqid(),
            'status' => 'active',
        ]);

        return SupermarketBranch::create([
            'supermarket_chain_id' => $chain->id,
            'city_id' => $city->id,
            'name' => $chainName.' Centro',
            'address' => 'Calle 1',
            'status' => 'active',
        ]);
    }

    private function price(Product $product, SupermarketBranch $branch, float $price, string $currency = 'ARS', ?Promotion $promotion = null)
    {
        $supermarketProduct = SupermarketProduct::create([
            'product_id' => $product->id,
            'supermarket_chain_id' => $branch->supermarket_chain_id,
            'supermarket_branch_id' => $branch->id,
            'status' => 'active',
        ]);

        return SupermarketProductPrice::create([
            'supermarket_product_id' => $supermarketProduct->id,
            'price' => $price,
            'unit_price' => $price,
            'currency' => $currency,
            'promotion_id' => $promotion ? $promotion->id : null,
            'status' => 'active',
            'scraped_at' => now(),
        ]);
    }

    public function test_auth_required()
    {
        $this->getJson('/api/v1/family-groups/1/shopping-lists/1/compare-supermarkets')->assertStatus(401);
    }

    public function test_access_to_other_group_rejected()
    {
        [$user, $group] = $this->context();
        [$otherUser, $otherGroup, $otherList] = $this->context();

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$otherList->id.'/compare-supermarkets')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'SHOPPING_LIST_NOT_FOUND');
    }

    public function test_comparison_returns_branch_total()
    {
        [$user, $group, $list, $item, $product] = $this->context();
        $branch = $this->branch('Carrefour', 'carrefour');
        $this->price($product, $branch, 100);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/compare-supermarkets')
            ->assertStatus(200)
            ->assertJsonPath('data.branches.0.found_count', 1);

        $this->assertEquals(200.0, $response->json('data.branches.0.total'));
    }

    public function test_missing_prices_are_reported()
    {
        [$user, $group, $list] = $this->context();
        $this->branch('Carrefour', 'carrefour');

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/compare-supermarkets')
            ->assertStatus(200)
            ->assertJsonPath('data.branches.0.missing_count', 1);
    }

    public function test_promotions_are_reported()
    {
        [$user, $group, $list, $item, $product] = $this->context();
        $branch = $this->branch('Carrefour', 'carrefour');
        $promotion = Promotion::create([
            'supermarket_chain_id' => $branch->supermarket_chain_id,
            'supermarket_branch_id' => $branch->id,
            'name' => 'Promo',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addDay(),
            'status' => 'active',
        ]);
        $this->price($product, $branch, 100, 'ARS', $promotion);

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/compare-supermarkets')
            ->assertStatus(200)
            ->assertJsonPath('data.branches.0.promotions.0.name', 'Promo');
    }

    public function test_incompatible_units_are_missing()
    {
        [$user, $group, $list, $item, $product] = $this->context();
        $otherUnit = $this->unit('kg');
        $product->update(['default_unit_id' => $otherUnit->id]);
        $branch = $this->branch('Carrefour', 'carrefour');
        $this->price($product, $branch, 100);

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/compare-supermarkets')
            ->assertStatus(200)
            ->assertJsonPath('data.branches.0.missing_count', 1);
    }

    public function test_coverage_is_returned()
    {
        [$user, $group, $list, $item, $product] = $this->context();
        $branch = $this->branch('Carrefour', 'carrefour');
        $this->price($product, $branch, 100);

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/compare-supermarkets')
            ->assertStatus(200)
            ->assertJsonPath('data.branches.0.coverage', 1);
    }

    public function test_optimize_returns_cheapest_complete_branch()
    {
        [$user, $group, $list, $item, $product] = $this->context();
        $expensive = $this->branch('Carrefour', 'carrefour');
        $cheap = $this->branch('ChangoMas', 'changomas');
        $this->price($product, $expensive, 120);
        $this->price($product, $cheap, 80);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/optimize')
            ->assertStatus(200)
            ->assertJsonPath('data.cheapest_complete.branch.id', $cheap->id);

        $this->assertEquals(160.0, $response->json('data.cheapest_complete.total'));
    }

    public function test_optimize_returns_combined_distribution()
    {
        [$user, $group, $list, $item, $product, $unit] = $this->context();
        $ingredient = $this->ingredient($unit);
        $secondProduct = $this->product($ingredient, $unit);
        ShoppingListItem::create([
            'shopping_list_id' => $list->id,
            'ingredient_id' => $ingredient->id,
            'product_id' => $secondProduct->id,
            'quantity' => 1,
            'unit_id' => $unit->id,
            'status' => 'pending',
        ]);
        $first = $this->branch('Carrefour', 'carrefour');
        $second = $this->branch('La Anonima', 'la-anonima');
        $this->price($product, $first, 80);
        $this->price($secondProduct, $second, 50);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/optimize')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.combined.items');

        $this->assertEquals(210.0, $response->json('data.combined.total'));
    }

    public function test_currency_mismatch_is_reported()
    {
        [$user, $group, $list, $item, $product] = $this->context();
        $branch = $this->branch('Carrefour', 'carrefour');
        $this->price($product, $branch, 100, 'USD');

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id.'/compare-supermarkets')
            ->assertStatus(200)
            ->assertJsonPath('data.branches.0.currency', 'USD');
    }
}
