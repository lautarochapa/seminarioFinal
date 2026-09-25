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

    public function test_get_is_read_only_and_its_offer_can_be_selected_and_repeated_with_parent_total(): void
    {
        $c = $this->context();
        $small = $this->product($c['ingredient'], $c['g'], $c['g'], 400);
        $offer = $this->price($small, 800);
        $response = $this->getJson($c['base'].'/alternatives')->assertOk()
            ->assertJsonPath('data.0.can_select', true)
            ->assertJsonPath('data.0.alternatives.0.purchase_unit.code', 'package')
            ->assertJsonPath('data.0.alternatives.0.product.package_unit.code', 'g');
        $this->assertSame(0, ShoppingListItemAlternative::count(), 'GET must not create selection snapshots.');
        $this->assertSame($c['original']->id, (int) $c['item']->fresh()->product_id);
        $offerId = $response->json('data.0.alternatives.0.supermarket_product_id');
        $this->assertSame($offer->id, $offerId);
        $url = $c['base'].'/items/'.$c['item']->id.'/select-alternative';
        foreach ([1, 2] as $attempt) {
            $this->postJson($url, ['supermarket_product_id' => $offerId])->assertOk()
                ->assertJsonPath('data.product.id', $small->id)
                ->assertJsonPath('data.quantity', '3.0000')
                ->assertJsonPath('data.unit.id', $c['package']->id)
                ->assertJsonPath('data.estimated_price', '800.00')
                ->assertJsonPath('data.estimated_subtotal', 2400);
            $this->getJson($c['base'])->assertOk()
                ->assertJsonPath('data.estimated_total', '2400.00')
                ->assertJsonPath('data.items.0.quantity', '3.0000');
        }
        $this->assertSame(1, ShoppingListItemAlternative::where('shopping_list_item_id', $c['item']->id)->count());
        $this->assertDatabaseHas('shopping_list_item_alternatives', ['shopping_list_item_id' => $c['item']->id, 'supermarket_product_id' => $offer->id, 'is_selected' => true]);
    }

    public function test_selection_uses_latest_price_for_that_offer_including_legacy_reference(): void
    {
        $c = $this->context();
        $small = $this->product($c['ingredient'], $c['g'], $c['g'], 400);
        $offer = $this->price($small, 800);
        $this->getJson($c['base'].'/alternatives')->assertOk()->assertJsonPath('data.0.alternatives.0.price', '800.00');
        $alternative = ShoppingListItemAlternative::create(['shopping_list_item_id' => $c['item']->id, 'product_id' => $small->id, 'supermarket_product_id' => $offer->id, 'price' => 100, 'reason' => 'equivalent']);
        // Equal scrape time: use the newer row, and never a different offer's cheaper price.
        SupermarketProductPrice::create(['supermarket_product_id' => $offer->id, 'price' => 900, 'currency' => 'ARS', 'status' => 'active', 'scraped_at' => $offer->prices()->first()->scraped_at]);
        $this->price($small, 1);
        $url = $c['base'].'/items/'.$c['item']->id.'/select-alternative';
        $this->postJson($url, ['alternative_id' => $alternative->id])->assertOk()->assertJsonPath('data.estimated_price', '900.00');
        $this->postJson($url, ['supermarket_product_id' => $offer->id])->assertOk()->assertJsonPath('data.estimated_price', '900.00');
        $this->assertSame('900.00', $alternative->fresh()->price);
        $this->assertSame('2700.00', $c['list']->fresh()->estimated_total);
        $this->assertEquals($offer->supermarket_chain_id, $c['item']->fresh()->supermarket_chain_id);
    }

    /** @dataProvider unavailableOffers */
    public function test_stale_offer_is_revalidated_before_any_mutation(string $changed): void
    {
        $c = $this->context();
        $small = $this->product($c['ingredient'], $c['g'], $c['g'], 400);
        $offer = $this->price($small, 800);
        $alternative = ShoppingListItemAlternative::create(['shopping_list_item_id' => $c['item']->id, 'product_id' => $small->id, 'supermarket_product_id' => $offer->id, 'price' => 800, 'reason' => 'equivalent']);
        $this->getJson($c['base'].'/alternatives')->assertOk()->assertJsonCount(1, 'data.0.alternatives');
        if ($changed === 'offer_inactive') $offer->update(['status' => 'inactive']);
        if ($changed === 'product_inactive') $small->update(['is_active' => false]);
        if ($changed === 'price_inactive') $offer->prices()->update(['status' => 'inactive']);
        if ($changed === 'price_expired') $offer->prices()->update(['valid_to' => now()->subDay()]);
        if ($changed === 'price_future') $offer->prices()->update(['valid_from' => now()->addDay()]);
        if ($changed === 'ingredient_changed') {
            $other = Ingredient::create(['name' => 'Tomate otro', 'normalized_name' => 'tomate otro', 'base_unit_id' => $c['g']->id, 'is_generic' => true, 'status' => 'active']);
            $small->update(['ingredient_id' => $other->id]);
        }
        $this->getJson($c['base'].'/alternatives')->assertOk()->assertJsonCount(0, 'data.0.alternatives');
        foreach ([['supermarket_product_id' => $offer->id], ['alternative_id' => $alternative->id]] as $input) {
            $this->postJson($c['base'].'/items/'.$c['item']->id.'/select-alternative', $input)
                ->assertStatus(404)->assertJsonPath('error.code', 'SHOPPING_ALTERNATIVE_NOT_FOUND');
        }
        $this->assertSame($c['original']->id, (int) $c['item']->fresh()->product_id);
        $this->assertSame('1.0000', $c['item']->fresh()->quantity);
        $this->assertFalse($alternative->fresh()->is_selected);
        $this->assertNull($c['list']->fresh()->estimated_total);
    }

    public function unavailableOffers(): array
    {
        return array_map(function ($case) { return [$case]; }, ['offer_inactive', 'product_inactive', 'price_inactive', 'price_expired', 'price_future', 'ingredient_changed']);
    }

    /** @dataProvider closedStates */
    public function test_historical_or_non_pending_items_cannot_change(string $listStatus, string $itemStatus, bool $processed): void
    {
        $c = $this->context();
        $offer = $this->price($this->product($c['ingredient'], $c['g'], $c['g'], 400), 800);
        $c['list']->update(['status' => $listStatus]);
        $c['item']->update(['status' => $itemStatus, 'stock_processed_at' => $processed ? now() : null]);
        $this->getJson($c['base'].'/alternatives')->assertOk()->assertJsonPath('data.0.can_select', false);
        $this->postJson($c['base'].'/items/'.$c['item']->id.'/select-alternative', ['supermarket_product_id' => $offer->id])
            ->assertStatus(409)->assertJsonPath('error.code', 'SHOPPING_ALTERNATIVE_NOT_EDITABLE');
        $this->assertSame($c['original']->id, (int) $c['item']->fresh()->product_id);
        $this->assertSame(0, ShoppingListItemAlternative::count());
    }

    public function closedStates(): array
    {
        return [
            'completed list' => ['completed', 'pending', false],
            'cancelled list' => ['cancelled', 'pending', false],
            'purchased item' => ['active', 'purchased', false],
            'skipped item' => ['active', 'skipped', false],
            'cancelled item' => ['active', 'cancelled', false],
            'reopened processed item' => ['active', 'pending', true],
        ];
    }

    public function test_reopened_item_linked_to_purchase_cannot_change(): void
    {
        $c = $this->context();
        $offer = $this->price($this->product($c['ingredient'], $c['g'], $c['g'], 400), 800);
        $purchase = \App\Purchase::create(['family_group_id' => $c['group']->id, 'shopping_list_id' => $c['list']->id, 'user_id' => $c['user']->id, 'purchase_date' => now(), 'status' => 'confirmed', 'actual_total' => 1800]);
        $purchaseItem = \App\PurchaseItem::create(['purchase_id' => $purchase->id, 'product_id' => $c['original']->id, 'quantity' => 1, 'unit_id' => $c['package']->id, 'unit_price' => 1800, 'total_price' => 1800]);
        $c['item']->update(['purchase_item_id' => $purchaseItem->id]);
        $this->getJson($c['base'].'/alternatives')->assertOk()->assertJsonPath('data.0.can_select', false);
        $this->postJson($c['base'].'/items/'.$c['item']->id.'/select-alternative', ['supermarket_product_id' => $offer->id])->assertStatus(409);
        $this->assertSame($c['original']->id, (int) $c['item']->fresh()->product_id);
        $this->assertSame('1800.00', $purchaseItem->fresh()->total_price);
    }

    public function test_request_rejects_product_ids_missing_or_ambiguous_references(): void
    {
        $c = $this->context();
        $url = $c['base'].'/items/'.$c['item']->id.'/select-alternative';
        foreach ([[], ['product_id' => $c['original']->id], ['alternative_id' => 1, 'supermarket_product_id' => 1], ['supermarket_product_id' => 1.5], ['supermarket_product_id' => 0]] as $input) {
            $this->postJson($url, $input)->assertStatus(422);
        }
        $this->assertSame(0, ShoppingListItemAlternative::count());
    }

    public function test_selection_rejects_mixed_group_list_item_and_legacy_alternative(): void
    {
        $c = $this->context();
        $offer = $this->price($this->product($c['ingredient'], $c['g'], $c['g'], 400), 800);
        $otherList = ShoppingList::create(['family_group_id' => $c['group']->id, 'created_by' => $c['user']->id, 'source_type' => 'manual', 'status' => 'draft']);
        $otherItem = ShoppingListItem::create(['shopping_list_id' => $otherList->id, 'product_id' => $c['original']->id, 'quantity' => 1, 'unit_id' => $c['package']->id, 'status' => 'pending']);
        $otherAlternative = ShoppingListItemAlternative::create(['shopping_list_item_id' => $otherItem->id, 'product_id' => $offer->product_id, 'supermarket_product_id' => $offer->id, 'price' => 800, 'reason' => 'equivalent']);
        $this->postJson($c['base'].'/items/'.$otherItem->id.'/select-alternative', ['supermarket_product_id' => $offer->id])->assertStatus(404);
        $this->postJson($c['base'].'/items/'.$c['item']->id.'/select-alternative', ['alternative_id' => $otherAlternative->id])->assertStatus(404);
        $outsider = factory(User::class)->create();
        $otherGroup = factory(FamilyGroup::class)->create(['owner_user_id' => $outsider->id]);
        $this->postJson('/api/v1/family-groups/'.$otherGroup->id.'/shopping-lists/'.$c['list']->id.'/items/'.$c['item']->id.'/select-alternative', ['supermarket_product_id' => $offer->id])->assertStatus(403);
        $this->assertSame($c['original']->id, (int) $c['item']->fresh()->product_id);
        $this->assertFalse($otherAlternative->fresh()->is_selected);
    }

    public function test_legacy_snapshot_must_reference_the_same_product_as_its_offer(): void
    {
        $c = $this->context();
        $offer = $this->price($this->product($c['ingredient'], $c['g'], $c['g'], 400), 800);
        $alternative = ShoppingListItemAlternative::create(['shopping_list_item_id' => $c['item']->id, 'product_id' => $c['original']->id, 'supermarket_product_id' => $offer->id, 'price' => 800, 'reason' => 'equivalent']);
        $this->postJson($c['base'].'/items/'.$c['item']->id.'/select-alternative', ['alternative_id' => $alternative->id])->assertStatus(404);
        $this->assertSame($c['original']->id, (int) $c['item']->fresh()->product_id);
        $this->assertFalse($alternative->fresh()->is_selected);
    }

    public function test_physical_requirement_buys_whole_packages_instead_of_multiplying_package_price_by_grams(): void
    {
        $c = $this->context();
        $c['item']->update(['quantity' => 100, 'unit_id' => $c['g']->id, 'estimated_price' => 1.80]);
        $small = $this->product($c['ingredient'], $c['g'], $c['kg'], 0.4);
        $this->price($small, 800);
        $response = $this->getJson($c['base'].'/alternatives')->assertOk()
            ->assertJsonPath('data.0.alternatives.0.purchase_quantity', 1)
            ->assertJsonPath('data.0.alternatives.0.purchase_unit.id', $c['package']->id)
            ->assertJsonPath('data.0.alternatives.0.estimated_subtotal', 800);
        $this->postJson($c['base'].'/items/'.$c['item']->id.'/select-alternative', ['supermarket_product_id' => $response->json('data.0.alternatives.0.supermarket_product_id')])
            ->assertOk()->assertJsonPath('data.quantity', '1.0000')
            ->assertJsonPath('data.unit.id', $c['package']->id)
            ->assertJsonPath('data.estimated_price', '800.00')
            ->assertJsonPath('data.actual_price', null);
        $this->assertSame('800.00', $c['list']->fresh()->estimated_total);
    }

    public function test_physical_requirement_rejects_incompatible_package_content(): void
    {
        $c = $this->context();
        $c['item']->update(['quantity' => 100, 'unit_id' => $c['g']->id]);
        $ml = UnitMeasure::firstOrCreate(['code' => 'ml'], ['name' => 'Mililitro', 'type' => 'volume', 'symbol' => 'ml', 'status' => 'active']);
        $offer = $this->price($this->product($c['ingredient'], $c['g'], $ml, 400), 800);
        $this->getJson($c['base'].'/alternatives')->assertOk()->assertJsonCount(0, 'data.0.alternatives');
        $this->postJson($c['base'].'/items/'.$c['item']->id.'/select-alternative', ['supermarket_product_id' => $offer->id])
            ->assertStatus(422)->assertJsonPath('error.code', 'SHOPPING_ALTERNATIVE_INCOMPATIBLE');
        $this->assertSame('100.0000', $c['item']->fresh()->quantity);
        $this->assertSame($c['g']->id, (int) $c['item']->fresh()->unit_id);
    }

    public function test_kg_requirement_finds_package_with_gram_default_and_content_unit(): void
    {
        $c = $this->context();
        $c['item']->update(['quantity' => 0.5, 'unit_id' => $c['kg']->id]);
        $small = $this->product($c['ingredient'], $c['g'], $c['g'], 400);
        $offer = $this->price($small, 800);
        $response = $this->getJson($c['base'].'/alternatives')->assertOk()
            ->assertJsonPath('data.0.alternatives.0.supermarket_product_id', $offer->id)
            ->assertJsonPath('data.0.alternatives.0.purchase_quantity', 2)
            ->assertJsonPath('data.0.alternatives.0.estimated_subtotal', 1600);
        $this->postJson($c['base'].'/items/'.$c['item']->id.'/select-alternative', ['supermarket_product_id' => $response->json('data.0.alternatives.0.supermarket_product_id')])
            ->assertOk()->assertJsonPath('data.quantity', '2.0000')
            ->assertJsonPath('data.unit.id', $c['package']->id);
        $this->assertSame('1600.00', $c['list']->fresh()->estimated_total);
    }

    public function test_same_product_with_new_purchase_unit_discards_physical_actual_price(): void
    {
        $c = $this->context();
        $c['item']->update(['quantity' => 100, 'unit_id' => $c['g']->id, 'actual_price' => 1.80]);
        $offer = $this->price($c['original'], 1800);
        $this->postJson($c['base'].'/items/'.$c['item']->id.'/select-alternative', ['supermarket_product_id' => $offer->id])
            ->assertOk()->assertJsonPath('data.product.id', $c['original']->id)
            ->assertJsonPath('data.quantity', '1.0000')
            ->assertJsonPath('data.unit.id', $c['package']->id)
            ->assertJsonPath('data.actual_price', null);
    }

}
