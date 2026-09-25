<?php

namespace Tests\Feature\Api\V1\ShoppingAlternatives;

use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Ingredient;
use App\Product;
use App\ShoppingList;
use App\ShoppingListItem;
use App\ShoppingListItemAlternative;
use App\SupermarketChain;
use App\SupermarketProduct;
use App\SupermarketProductPrice;
use App\UnitConversion;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPackagingAlternativesTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $user = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id]);
        factory(FamilyGroupMember::class)->create(['family_group_id' => $group->id, 'user_id' => $user->id, 'role_in_group' => 'owner', 'status' => 'active']);
        $g = UnitMeasure::firstOrCreate(['code' => 'g'], ['name' => 'Gramo', 'type' => 'weight', 'symbol' => 'g', 'status' => 'active']);
        $kg = UnitMeasure::firstOrCreate(['code' => 'kg'], ['name' => 'Kilogramo', 'type' => 'weight', 'symbol' => 'kg', 'status' => 'active']);
        $package = UnitMeasure::firstOrCreate(['code' => 'package'], ['name' => 'Paquete', 'type' => 'package', 'symbol' => 'paq', 'status' => 'active']);
        UnitConversion::updateOrCreate(['from_unit_id' => $kg->id, 'to_unit_id' => $g->id, 'ingredient_id' => null], ['factor' => 1000, 'status' => 'active']);
        $ingredient = Ingredient::create(['name' => 'Arroz alternativas', 'normalized_name' => 'arroz alternativas', 'base_unit_id' => $g->id, 'is_generic' => true, 'status' => 'active']);
        $original = $this->product($ingredient, $g, $g, 1000);
        $list = ShoppingList::create(['family_group_id' => $group->id, 'created_by' => $user->id, 'source_type' => 'manual', 'status' => 'draft']);
        $item = ShoppingListItem::create(['shopping_list_id' => $list->id, 'product_id' => $original->id, 'quantity' => 1, 'unit_id' => $package->id, 'estimated_price' => 1800, 'actual_price' => 1700, 'status' => 'pending']);
        $this->actingAs($user);
        $base = '/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id;
        return compact('user', 'group', 'g', 'kg', 'package', 'ingredient', 'original', 'list', 'item', 'base');
    }

    private function product(Ingredient $ingredient, UnitMeasure $default, UnitMeasure $content, ?float $size): Product
    {
        $name = 'Presentacion '.uniqid();
        return Product::create(['name' => $name, 'nombre' => $name, 'normalized_name' => strtolower($name), 'brand_id' => 0, 'codigo' => uniqid('QA-ALT-'), 'img' => '', 'habilitado' => 1, 'supply_id' => 0, 'ingredient_id' => $ingredient->id, 'default_unit_id' => $default->id, 'package_unit_id' => $content->id, 'net_quantity' => $size, 'status' => 'active', 'is_active' => true]);
    }

    private function price(Product $product, float $price): SupermarketProduct
    {
        $chain = SupermarketChain::create(['name' => 'Alternativas QA', 'code' => uniqid('alt_'), 'status' => 'active']);
        $offer = SupermarketProduct::create(['product_id' => $product->id, 'supermarket_chain_id' => $chain->id, 'status' => 'active']);
        SupermarketProductPrice::create(['supermarket_product_id' => $offer->id, 'price' => $price, 'currency' => 'ARS', 'status' => 'active', 'scraped_at' => now()]);
        return $offer;
    }

    public function test_package_alternatives_use_content_and_total_cost_instead_of_default_unit(): void
    {
        $c = $this->context();
        $small = $this->product($c['ingredient'], $c['g'], $c['g'], 400);
        $half = $this->product($c['ingredient'], $c['g'], $c['kg'], 0.5);
        $this->price($small, 800);
        $this->price($half, 700);
        $this->price($this->product($c['ingredient'], $c['package'], $c['g'], null), 10);
        $ml = UnitMeasure::firstOrCreate(['code' => 'ml'], ['name' => 'Mililitro', 'type' => 'volume', 'symbol' => 'ml', 'status' => 'active']);
        $this->price($this->product($c['ingredient'], $c['g'], $ml, 400), 20);

        $response = $this->getJson($c['base'].'/alternatives')->assertOk()->assertJsonCount(2, 'data.0.alternatives');
        $alternatives = $response->json('data.0.alternatives');
        $this->assertSame($half->id, $alternatives[0]['product']['id']);
        $this->assertSame(2, $alternatives[0]['purchase_quantity']);
        $this->assertSame($c['package']->id, $alternatives[0]['purchase_unit_id']);
        $this->assertEquals(1400, $alternatives[0]['estimated_subtotal']);
        $this->assertSame('cheaper', $alternatives[0]['reason']);
        $this->assertSame($small->id, $alternatives[1]['product']['id']);
        $this->assertSame(3, $alternatives[1]['purchase_quantity']);
        $this->assertEquals(2400, $alternatives[1]['estimated_subtotal']);
        $this->assertSame('equivalent', $alternatives[1]['reason'], 'A cheaper package can require a more expensive complete purchase.');
    }

    public function test_selecting_persisted_package_alternative_preserves_content_and_is_repeatable(): void
    {
        $c = $this->context();
        $small = $this->product($c['ingredient'], $c['g'], $c['g'], 400);
        $offer = $this->price($small, 800);
        $alternative = ShoppingListItemAlternative::create(['shopping_list_item_id' => $c['item']->id, 'product_id' => $small->id, 'supermarket_product_id' => $offer->id, 'price' => 800, 'reason' => 'equivalent']);
        $url = $c['base'].'/items/'.$c['item']->id.'/select-alternative';
        $this->postJson($url, ['alternative_id' => $alternative->id])->assertOk()
            ->assertJsonPath('data.product.id', $small->id)
            ->assertJsonPath('data.quantity', '3.0000')
            ->assertJsonPath('data.unit.id', $c['package']->id)
            ->assertJsonPath('data.estimated_price', '800.00')
            ->assertJsonPath('data.actual_price', null);
        $this->assertEquals(2400, (float) $c['item']->fresh()->quantity * (float) $c['item']->fresh()->estimated_price);
        $this->postJson($url, ['alternative_id' => $alternative->id])->assertOk()->assertJsonPath('data.quantity', '3.0000');
        $this->assertTrue($alternative->fresh()->is_selected);
    }

    public function test_incompatible_package_selection_is_atomic(): void
    {
        $c = $this->context();
        $invalid = $this->product($c['ingredient'], $c['package'], $c['g'], null);
        $offer = $this->price($invalid, 10);
        $alternative = ShoppingListItemAlternative::create(['shopping_list_item_id' => $c['item']->id, 'product_id' => $invalid->id, 'supermarket_product_id' => $offer->id, 'price' => 10, 'reason' => 'equivalent']);
        $this->postJson($c['base'].'/items/'.$c['item']->id.'/select-alternative', ['alternative_id' => $alternative->id])
            ->assertStatus(422)->assertJsonPath('error.code', 'SHOPPING_ALTERNATIVE_INCOMPATIBLE');
        $this->assertSame($c['original']->id, (int) $c['item']->fresh()->product_id);
        $this->assertEquals(1, (float) $c['item']->fresh()->quantity);
        $this->assertFalse($alternative->fresh()->is_selected);
    }
}