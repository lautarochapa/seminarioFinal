<?php

namespace Tests\Feature\Api\V1\E2E;

use App\Budget;
use App\City;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Ingredient;
use App\MealPlan;
use App\MealPlanItem;
use App\MealType;
use App\Product;
use App\Purchase;
use App\PurchaseItem;
use App\Recipe;
use App\RecipeIngredient;
use App\ShoppingList;
use App\ShoppingListItem;
use App\StockItem;
use App\SupermarketBranch;
use App\SupermarketChain;
use App\SupermarketProduct;
use App\SupermarketProductPrice;
use App\UnitConversion;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPackagingFlowTest extends TestCase
{
    use RefreshDatabase;

    private function context(bool $defaultIsPackage = false): array
    {
        $user = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id]);
        factory(FamilyGroupMember::class)->create(['family_group_id' => $group->id, 'user_id' => $user->id, 'role_in_group' => 'owner', 'status' => 'active']);
        $g = UnitMeasure::firstOrCreate(['code' => 'g'], ['name' => 'Gramo', 'type' => 'weight', 'symbol' => 'g', 'status' => 'active']);
        $kg = UnitMeasure::firstOrCreate(['code' => 'kg'], ['name' => 'Kilogramo', 'type' => 'weight', 'symbol' => 'kg', 'status' => 'active']);
        $package = UnitMeasure::firstOrCreate(['code' => 'package'], ['name' => 'Paquete', 'type' => 'package', 'symbol' => 'paq', 'status' => 'active']);
        UnitConversion::updateOrCreate(['from_unit_id' => $kg->id, 'to_unit_id' => $g->id, 'ingredient_id' => null], ['factor' => 1000, 'status' => 'active']);
        $ingredient = Ingredient::create(['name' => 'Arroz QA', 'normalized_name' => 'arroz qa', 'base_unit_id' => $g->id, 'is_generic' => true, 'status' => 'active']);
        $product = Product::create([
            'name' => 'Arroz QA 1kg', 'nombre' => 'Arroz QA 1kg', 'normalized_name' => 'arroz qa 1kg',
            'brand_id' => 0, 'codigo' => 'QA-PACKAGE', 'img' => '', 'habilitado' => 1, 'supply_id' => 0,
            'ingredient_id' => $ingredient->id, 'default_unit_id' => $defaultIsPackage ? $package->id : $g->id,
            'package_unit_id' => $g->id, 'net_quantity' => 1000, 'status' => 'active', 'is_active' => true,
        ]);
        $stock = StockItem::create(['family_group_id' => $group->id, 'product_id' => $product->id, 'quantity' => 50, 'unit_id' => $g->id, 'status' => 'active']);
        $recipe = Recipe::create([
            'name' => 'Arroz cocido QA', 'nombre' => 'Arroz cocido QA', 'normalized_name' => 'arroz cocido qa',
            'descripcion' => '', 'tiempo' => '', 'img' => '', 'video' => '', 'porcion' => '1', 'calorias' => 0,
            'servings' => 1, 'status' => 'active', 'is_public' => true,
        ]);
        RecipeIngredient::create(['recipe_id' => $recipe->id, 'ingredient_id' => $ingredient->id, 'quantity' => 100, 'unit_id' => $g->id, 'is_optional' => false]);
        $mealType = MealType::create(['code' => 'qa_pack', 'name' => 'Almuerzo QA', 'status' => 'active']);
        $plan = MealPlan::create(['family_group_id' => $group->id, 'created_by' => $user->id, 'period_type' => 'daily', 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(), 'status' => 'approved']);
        MealPlanItem::create(['meal_plan_id' => $plan->id, 'date' => now()->toDateString(), 'meal_type_id' => $mealType->id, 'recipe_id' => $recipe->id, 'servings_total' => 1.5, 'status' => 'planned', 'is_eating_out' => false]);
        $city = City::create(['name' => 'Ciudad QA', 'province' => 'BA', 'country' => 'Argentina', 'status' => 'active']);
        $chain = SupermarketChain::create(['name' => 'Cadena QA', 'code' => 'qa_pack', 'status' => 'active']);
        $branch = SupermarketBranch::create(['supermarket_chain_id' => $chain->id, 'city_id' => $city->id, 'name' => 'Sucursal QA', 'address' => 'Calle QA', 'status' => 'active']);
        $supermarketProduct = SupermarketProduct::create(['product_id' => $product->id, 'supermarket_chain_id' => $chain->id, 'supermarket_branch_id' => $branch->id, 'status' => 'active']);
        $price = SupermarketProductPrice::create(['supermarket_product_id' => $supermarketProduct->id, 'price' => 1800, 'currency' => 'ARS', 'scraped_at' => now(), 'status' => 'active']);
        $budget = Budget::create(['family_group_id' => $group->id, 'year' => now()->year, 'month' => now()->month, 'total_amount' => 10000, 'currency' => 'ARS', 'status' => 'active']);
        $this->actingAs($user);
        return compact('user', 'group', 'g', 'kg', 'package', 'ingredient', 'product', 'stock', 'recipe', 'plan', 'branch', 'price', 'budget');
    }

    private function generate(array $c): ShoppingListItem
    {
        $base = '/api/v1/family-groups/'.$c['group']->id.'/meal-plans/'.$c['plan']->id;
        $preview = $this->getJson($base.'/shopping-list-preview')->assertOk();
        $this->assertEquals(150, $preview->json('data.0.required_quantity'));
        $this->assertEquals(100, $preview->json('data.0.missing_quantity'));
        $generated = $this->postJson($base.'/generate-shopping-list')->assertCreated();
        return ShoppingListItem::where('shopping_list_id', $generated->json('data.id'))->firstOrFail();
    }

    public function purchaseRoutes(): array
    {
        return [['session', false], ['complete', false], ['purchase', false], ['repair', false], ['session', true]];
    }

    /** @dataProvider purchaseRoutes */
    public function test_packages_preserve_price_and_add_content_before_cooking(string $route, bool $defaultIsPackage): void
    {
        $c = $this->context($defaultIsPackage);
        $item = $this->generate($c);
        $this->assertEquals(1, (float) $item->quantity);
        $this->assertSame($c['package']->id, (int) $item->unit_id, 'Package count must never be labeled as grams.');
        $this->assertEquals(1800, (float) $item->estimated_price);
        $base = '/api/v1/family-groups/'.$c['group']->id;
        $comparison = $this->getJson($base.'/shopping-lists/'.$item->shopping_list_id.'/compare-supermarkets')->assertOk();
        $this->assertEquals(1800, $comparison->json('data.branches.0.total'));
        $this->assertEquals(0, $comparison->json('data.branches.0.missing_count'));
        $otherUnitStock = StockItem::create(['family_group_id' => $c['group']->id, 'product_id' => $c['product']->id, 'quantity' => 0.5, 'unit_id' => $c['kg']->id, 'status' => 'active']);
        $purchase = $this->enterStock($c, $item, $route, 1800);
        $this->assertEquals(1800, (float) $purchase->actual_total);
        $purchased = $purchase->items()->firstOrFail();
        $this->assertEquals(1, (float) $purchased->quantity);
        $this->assertSame($c['package']->id, (int) $purchased->unit_id);
        $this->assertEquals(1800, (float) $purchased->unit_price);
        $this->assertEquals(1050, (float) $c['stock']->fresh()->quantity);
        $this->assertEquals(1.8, (float) $c['stock']->fresh()->estimated_purchase_price);
        $this->assertEquals(0.5, (float) $otherUnitStock->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', ['related_purchase_id' => $purchase->id, 'quantity' => 1000, 'unit_id' => $c['g']->id]);
        $summary = $this->getJson($base.'/budgets/'.$c['budget']->id.'/summary')->assertOk();
        $this->assertEquals(1800, $summary->json('data.spent_amount'));
        $cookUrl = $base.'/meal-plans/'.$c['plan']->id.'/items/'.$c['plan']->items()->firstOrFail()->id.'/mark-cooked';
        $this->postJson($cookUrl, ['servings' => 1.5])->assertOk()->assertJsonPath('data.status', 'cooked');
        $this->assertEquals(900, (float) $c['stock']->fresh()->quantity);
        $this->assertEquals(0.5, (float) $otherUnitStock->fresh()->quantity);
        $this->postJson($cookUrl, ['servings' => 1.5])->assertStatus(409);
        $this->assertEquals(900, (float) $c['stock']->fresh()->quantity);
    }

    /** @dataProvider purchaseRoutes */
    public function test_decimal_gram_purchase_does_not_expand_packaging(string $route, bool $defaultIsPackage): void
    {
        $c = $this->context($defaultIsPackage);
        $list = ShoppingList::create(['family_group_id' => $c['group']->id, 'created_by' => $c['user']->id, 'source_type' => 'manual', 'status' => 'active']);
        $item = ShoppingListItem::create(['shopping_list_id' => $list->id, 'product_id' => $c['product']->id, 'quantity' => 100.5, 'unit_id' => $c['g']->id, 'status' => 'pending']);
        $otherUnitStock = StockItem::create(['family_group_id' => $c['group']->id, 'product_id' => $c['product']->id, 'quantity' => 0.5, 'unit_id' => $c['kg']->id, 'status' => 'active']);
        $purchase = $this->enterStock($c, $item, $route, 1.8);
        $this->assertEquals(150.5, (float) $c['stock']->fresh()->quantity);
        $this->assertEquals(0.5, (float) $otherUnitStock->fresh()->quantity);
        $this->assertEquals(180.9, (float) $purchase->actual_total);
        $this->assertDatabaseHas('stock_movements', ['related_purchase_id' => $purchase->id, 'quantity' => 100.5, 'unit_id' => $c['g']->id]);
    }

    public function ambiguousHistorySources(): array
    {
        return [['meal_plan', true], ['recipe', true], ['manual', true], ['meal_plan', false]];
    }

    /** @dataProvider ambiguousHistorySources */
    public function test_legacy_generated_physical_unit_price_is_not_multiplied_as_manual_history(string $source, bool $currentPrice): void
    {
        $c = $this->context();
        if (!$currentPrice) {
            $c['price']->update(['status' => 'inactive']);
        }
        $oldList = ShoppingList::create(['family_group_id' => $c['group']->id, 'created_by' => $c['user']->id, 'source_type' => $source, 'status' => 'completed']);
        $purchase = Purchase::create(['family_group_id' => $c['group']->id, 'shopping_list_id' => $oldList->id, 'user_id' => $c['user']->id, 'purchase_date' => now()->toDateString(), 'status' => 'confirmed']);
        // Before the packaging fix, generated lists could label 1 package / ARS1800 as 1g.
        $oldItem = PurchaseItem::create(['purchase_id' => $purchase->id, 'product_id' => $c['product']->id, 'quantity' => 1, 'unit_id' => $c['g']->id, 'unit_price' => 1800]);
        if ($source === 'manual') {
            // Recipe generation can also append an item to a pre-existing manual list.
            ShoppingListItem::create(['shopping_list_id' => $oldList->id, 'product_id' => $c['product']->id, 'quantity' => 1, 'unit_id' => $c['g']->id, 'source_type' => 'recipe_generation', 'purchase_item_id' => $oldItem->id, 'status' => 'purchased']);
        }
        $generated = $this->postJson('/api/v1/family-groups/'.$c['group']->id.'/recipes/'.$c['recipe']->id.'/shopping-list', ['servings' => 1])->assertCreated();
        $this->assertSame($currentPrice ? 'best_available' : null, $generated->json('data.priced_items.0.price_source'));
        if ($currentPrice) {
            $this->assertEquals(1800, $generated->json('data.priced_items.0.estimated_unit_price'));
        } else {
            $this->assertNull($generated->json('data.priced_items.0.estimated_unit_price'));
        }
        $this->assertDatabaseHas('purchase_items', ['id' => $oldItem->id, 'quantity' => 1, 'unit_id' => $c['g']->id, 'unit_price' => 1800]);
    }

    public function test_missing_package_unit_keeps_unpriced_missing_grams_without_creating_master_data(): void
    {
        $c = $this->context();
        $c['package']->update(['status' => 'inactive']);
        $before = UnitMeasure::count();
        $item = $this->generate($c);
        $this->assertEquals(100, (float) $item->quantity);
        $this->assertSame($c['g']->id, (int) $item->unit_id);
        $this->assertNull($item->estimated_price);
        $this->assertSame($before, UnitMeasure::count());
        $this->assertSame('inactive', $c['package']->fresh()->status);
    }

    public function test_recipe_generation_and_gram_purchase_history_use_package_price(): void
    {
        $c = $this->context();
        $c['price']->update(['status' => 'inactive']);
        $manualList = ShoppingList::create(['family_group_id' => $c['group']->id, 'created_by' => $c['user']->id, 'source_type' => 'manual', 'status' => 'completed']);
        $purchase = Purchase::create(['family_group_id' => $c['group']->id, 'shopping_list_id' => $manualList->id, 'user_id' => $c['user']->id, 'purchase_date' => now()->toDateString(), 'status' => 'confirmed']);
        PurchaseItem::create(['purchase_id' => $purchase->id, 'product_id' => $c['product']->id, 'quantity' => 100, 'unit_id' => $c['g']->id, 'unit_price' => 1.8]);
        $generated = $this->postJson('/api/v1/family-groups/'.$c['group']->id.'/recipes/'.$c['recipe']->id.'/shopping-list', ['servings' => 1])->assertCreated();
        $this->assertEquals(1, $generated->json('data.priced_items.0.purchase_quantity'));
        $this->assertSame($c['package']->id, $generated->json('data.priced_items.0.purchase_unit_id'));
        $this->assertEquals(1800, $generated->json('data.priced_items.0.estimated_unit_price'));
        $this->assertEquals(1800, $generated->json('data.estimated_total'));
    }

    /** @dataProvider purchaseRoutes */
    public function test_subcent_stock_price_preserves_purchase_value(string $route, bool $defaultIsPackage): void
    {
        $c = $this->context($defaultIsPackage);
        $c['stock']->update(['quantity' => 0]);
        $list = ShoppingList::create(['family_group_id' => $c['group']->id, 'created_by' => $c['user']->id, 'source_type' => 'manual', 'status' => 'active']);
        $item = ShoppingListItem::create(['shopping_list_id' => $list->id, 'product_id' => $c['product']->id, 'quantity' => 3, 'unit_id' => $c['package']->id, 'status' => 'pending']);
        $purchase = $this->enterStock($c, $item, $route, 1);
        $stock = $c['stock']->fresh();
        $this->assertEquals(3000, (float) $stock->quantity);
        $this->assertSame('0.001000000000', $stock->estimated_purchase_price);
        $this->assertEquals(3, (float) $purchase->actual_total);
        $this->assertEquals((float) $purchase->actual_total, round((float) $stock->quantity * (float) $stock->estimated_purchase_price, 2));
        $this->getJson('/api/v1/family-groups/'.$c['group']->id.'/stock/value')->assertOk()->assertJsonPath('data.total_value', 3);
        $movementsBefore = \App\StockMovement::where('related_purchase_id', $purchase->id)->count();
        if ($route === 'repair') {
            $this->postJson('/api/v1/family-groups/'.$c['group']->id.'/shopping-lists/'.$list->id.'/process-pending-stock', ['items' => [['shopping_list_item_id' => $item->id, 'add_to_stock' => true]]])->assertOk();
            $this->assertEquals(3000, (float) $stock->fresh()->quantity);
            $this->assertEquals(3, (float) $purchase->fresh()->actual_total);
            $this->assertSame($movementsBefore, \App\StockMovement::where('related_purchase_id', $purchase->id)->count());
        }
    }

    public function test_unit_purchase_is_not_expanded_to_product_package_content(): void
    {
        $c = $this->context();
        $c['g']->update(['code' => 'unit', 'name' => 'Unidad', 'symbol' => 'u', 'type' => 'count']);
        $c['product']->update(['net_quantity' => 10]);
        $list = ShoppingList::create(['family_group_id' => $c['group']->id, 'created_by' => $c['user']->id, 'source_type' => 'manual', 'status' => 'active']);
        $item = ShoppingListItem::create(['shopping_list_id' => $list->id, 'product_id' => $c['product']->id, 'quantity' => 2.5, 'unit_id' => $c['g']->id, 'status' => 'pending']);
        $purchase = $this->enterStock($c, $item, 'purchase', 2);
        $this->assertEquals(52.5, (float) $c['stock']->fresh()->quantity);
        $this->assertEquals(5, (float) $purchase->actual_total);
        $this->assertDatabaseHas('stock_movements', ['related_purchase_id' => $purchase->id, 'quantity' => 2.5, 'unit_id' => $c['g']->id]);
    }

    public function stockPriceEdges(): array
    {
        return [[99999999.9999, 0.01], [0.0001, 1000000000.00]];
    }

    /** @dataProvider stockPriceEdges */
    public function test_stock_price_precision_covers_catalog_quantity_range(float $size, float $price): void
    {
        $c = $this->context();
        $c['stock']->update(['quantity' => 0]);
        $c['product']->update(['net_quantity' => $size]);
        $list = ShoppingList::create(['family_group_id' => $c['group']->id, 'created_by' => $c['user']->id, 'source_type' => 'manual', 'status' => 'active']);
        $item = ShoppingListItem::create(['shopping_list_id' => $list->id, 'product_id' => $c['product']->id, 'quantity' => 1, 'unit_id' => $c['package']->id, 'status' => 'pending']);
        $purchase = $this->enterStock($c, $item, 'purchase', $price);
        $this->assertEquals($size, (float) $c['stock']->fresh()->quantity);
        $this->assertEquals($price, (float) $purchase->actual_total);
        $value = $this->getJson('/api/v1/family-groups/'.$c['group']->id.'/stock/value')->assertOk();
        $this->assertEquals($price, $value->json('data.total_value'));
    }

    public function test_precision_rollback_refuses_to_round_existing_stock_prices(): void
    {
        $c = $this->context();
        $c['stock']->update(['estimated_purchase_price' => 0.001]);
        require_once database_path('migrations/2026_09_25_000001_increase_stock_unit_price_precision.php');
        try {
            (new \IncreaseStockUnitPricePrecision())->down();
            $this->fail('Rollback must reject a lossy conversion.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('precision', $exception->getMessage());
        }
        $this->assertSame('0.001000000000', $c['stock']->fresh()->estimated_purchase_price);
        $c['stock']->update(['estimated_purchase_price' => 1.23]);
        (new \IncreaseStockUnitPricePrecision())->down();
        $this->assertEquals(1.23, (float) $c['stock']->fresh()->estimated_purchase_price);
        (new \IncreaseStockUnitPricePrecision())->up();
        $this->assertSame('1.230000000000', $c['stock']->fresh()->estimated_purchase_price);
    }

    private function enterStock(array $c, ShoppingListItem $item, string $route, float $price): Purchase
    {
        $base = '/api/v1/family-groups/'.$c['group']->id;
        $list = $item->shoppingList;
        $item->update(['status' => 'purchased', 'actual_price' => $price]);
        if ($route === 'session') {
            $session = $this->postJson($base.'/shopping-lists/'.$list->id.'/start-session')->assertCreated();
            $this->postJson($base.'/shopping-sessions/'.$session->json('data.id').'/finish')->assertOk();
        } elseif ($route === 'complete') {
            $list->update(['status' => 'in_progress']);
            $this->postJson($base.'/shopping-lists/'.$list->id.'/complete', ['items' => [['shopping_list_item_id' => $item->id, 'add_to_stock' => true]]])->assertOk();
        } elseif ($route === 'repair') {
            $list->update(['status' => 'completed']);
            $this->postJson($base.'/shopping-lists/'.$list->id.'/process-pending-stock', ['items' => [['shopping_list_item_id' => $item->id, 'add_to_stock' => true]]])->assertOk();
        } else {
            $purchase = Purchase::create(['family_group_id' => $c['group']->id, 'shopping_list_id' => $list->id, 'user_id' => $c['user']->id, 'purchase_date' => now()->toDateString(), 'status' => 'confirmed']);
            $this->postJson($base.'/purchases/'.$purchase->id.'/items', ['product_id' => $c['product']->id, 'quantity' => (float) $item->quantity, 'unit_id' => $item->unit_id, 'unit_price' => $price])->assertCreated();
            $this->postJson($base.'/purchases/'.$purchase->id.'/confirm')->assertOk();
            $this->postJson($base.'/purchases/'.$purchase->id.'/add-to-stock')->assertOk();
        }
        return Purchase::where('shopping_list_id', $list->id)->firstOrFail()->fresh();
    }
}
