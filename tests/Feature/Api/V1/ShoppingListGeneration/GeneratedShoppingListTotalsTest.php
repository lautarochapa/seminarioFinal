<?php

namespace Tests\Feature\Api\V1\ShoppingListGeneration;

use App\AuditLog;
use App\Budget;
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
use App\SupermarketChain;
use App\SupermarketProduct;
use App\SupermarketProductPrice;
use App\UnitMeasure;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneratedShoppingListTotalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); Carbon::setTestNow('2026-09-25 12:00:00'); }
    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    private function context(): array
    {
        $user = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id, 'status' => 'active']);
        factory(FamilyGroupMember::class)->create(['family_group_id' => $group->id, 'user_id' => $user->id, 'role_in_group' => 'owner', 'status' => 'active']);
        $g = UnitMeasure::firstOrCreate(['code' => 'g'], ['name' => 'Gramo', 'symbol' => 'g', 'type' => 'weight', 'status' => 'active']);
        $package = UnitMeasure::firstOrCreate(['code' => 'package'], ['name' => 'Paquete', 'symbol' => 'paq', 'type' => 'package', 'status' => 'active']);
        $ingredient = Ingredient::create(['name' => 'Arroz QA', 'normalized_name' => 'arroz qa', 'base_unit_id' => $g->id, 'is_generic' => true, 'status' => 'active']);
        $product = Product::create(['name' => 'Arroz 1 kg', 'nombre' => 'Arroz 1 kg', 'normalized_name' => 'arroz 1 kg',
            'brand_id' => 0, 'codigo' => uniqid('GEN-'), 'img' => '', 'habilitado' => 1, 'supply_id' => 0,
            'ingredient_id' => $ingredient->id, 'default_unit_id' => $g->id, 'net_quantity' => 1000, 'package_unit_id' => $g->id,
            'is_active' => true, 'status' => 'active']);
        $recipe = Recipe::create(['name' => 'Receta QA', 'nombre' => 'Receta QA', 'normalized_name' => 'receta qa',
            'descripcion' => '', 'tiempo' => '', 'img' => '', 'video' => '', 'porcion' => '1', 'calorias' => 100,
            'owner_user_id' => $user->id, 'servings' => 1, 'is_public' => true, 'status' => 'active']);
        RecipeIngredient::create(['recipe_id' => $recipe->id, 'ingredient_id' => $ingredient->id, 'quantity' => 150, 'unit_id' => $g->id, 'is_optional' => false]);
        $type = MealType::create(['code' => uniqid('gen_'), 'name' => 'Cena QA', 'status' => 'active']);
        $plan = MealPlan::create(['family_group_id' => $group->id, 'created_by' => $user->id, 'period_type' => 'daily',
            'start_date' => '2026-09-25', 'end_date' => '2026-09-25', 'status' => 'draft']);
        MealPlanItem::create(['meal_plan_id' => $plan->id, 'date' => '2026-09-25', 'meal_type_id' => $type->id,
            'recipe_id' => $recipe->id, 'servings_total' => 1, 'status' => 'planned', 'is_eating_out' => false]);
        $chain = SupermarketChain::create(['name' => 'Cadena QA', 'code' => uniqid('gen_'), 'status' => 'active']);
        $offer = SupermarketProduct::create(['product_id' => $product->id, 'supermarket_chain_id' => $chain->id, 'status' => 'active']);
        $price = SupermarketProductPrice::create(['supermarket_product_id' => $offer->id, 'price' => 1800.01, 'currency' => 'ARS', 'status' => 'active', 'scraped_at' => now()]);
        $budget = Budget::create(['family_group_id' => $group->id, 'year' => 2026, 'month' => 9, 'total_amount' => 10000, 'currency' => 'ARS', 'status' => 'active']);
        $this->actingAs($user);
        $base = '/api/v1/family-groups/'.$group->id;
        return compact('user', 'group', 'g', 'package', 'ingredient', 'product', 'recipe', 'plan', 'price', 'budget', 'base');
    }

    private function history(array $c): PurchaseItem
    {
        $purchase = Purchase::create(['family_group_id' => $c['group']->id, 'user_id' => $c['user']->id,
            'purchase_date' => '2026-08-10', 'status' => 'confirmed', 'actual_total' => 1200]);
        return PurchaseItem::create(['purchase_id' => $purchase->id, 'product_id' => $c['product']->id,
            'quantity' => 1, 'unit_id' => $c['package']->id, 'unit_price' => 1200, 'total_price' => 1200]);
    }

    private function generate(array $c, string $kind, ?int $listId = null)
    {
        if ($kind === 'preview') return $this->postJson($c['base'].'/meal-plans/'.$c['plan']->id.'/generate-shopping-list');
        if ($kind === 'menu') return $this->postJson($c['base'].'/shopping-lists/generate-from-meal-plan', ['meal_plan_id' => $c['plan']->id]);
        if ($kind === 'history') return $this->postJson($c['base'].'/shopping-lists/generate-from-history', []);
        return $this->postJson($c['base'].'/recipes/'.$c['recipe']->id.'/shopping-list', $listId ? ['shopping_list_id' => $listId] : []);
    }

    private function assertTotal(array $c, int $listId, string $total): void
    {
        $this->getJson($c['base'].'/shopping-lists/'.$listId)->assertOk()->assertJsonPath('data.estimated_total', $total);
        $this->assertSame($total, ShoppingList::findOrFail($listId)->estimated_total);
        $rows = $this->getJson($c['base'].'/shopping-lists')->assertOk()->json('data');
        $this->assertSame($total, collect($rows)->firstWhere('id', $listId)['estimated_total']);
    }

    /** @dataProvider planEndpoints */
    public function test_menu_endpoints_persist_total_and_project_it_for_the_household(string $kind): void
    {
        $c = $this->context();
        $response = $this->generate($c, $kind)->assertStatus(201)->assertJsonPath('data.estimated_total', '1800.01');
        $this->assertTotal($c, $response->json('data.id'), '1800.01');
        $this->getJson($c['base'].'/budgets/'.$c['budget']->id.'/projection')->assertOk()
            ->assertJsonPath('data.planned_amount', 1800.01)->assertJsonPath('data.available_projected', 8199.99)
            ->assertJsonCount(1, 'data.planned_sources');
    }

    public function planEndpoints(): array { return [['menu'], ['preview']]; }

    public function test_menu_regeneration_uses_new_price_and_clears_stale_total_when_stock_covers_all(): void
    {
        $c = $this->context();
        $id = $this->generate($c, 'menu')->assertStatus(201)->json('data.id');
        $this->patchJson($c['base'].'/shopping-lists/'.$id.'/items/'.ShoppingList::find($id)->items()->first()->id, ['estimated_price' => 999])->assertOk();
        $c['price']->update(['price' => 1900]);
        $this->generate($c, 'menu')->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.estimated_total', '1900.00');
        $this->assertTotal($c, $id, '1900.00');
        StockItem::create(['family_group_id' => $c['group']->id, 'product_id' => $c['product']->id,
            'quantity' => 150, 'unit_id' => $c['g']->id, 'status' => 'active']);
        $this->generate($c, 'menu')->assertOk()->assertJsonPath('data.id', $id)
            ->assertJsonCount(0, 'data.items')->assertJsonPath('data.estimated_total', '0.00');
        $this->assertTotal($c, $id, '0.00');
        $this->getJson($c['base'].'/budgets/'.$c['budget']->id.'/projection')->assertOk()->assertJsonPath('data.planned_amount', null);
    }

    public function test_menu_partial_and_unknown_prices_keep_unknown_lines_and_sum_only_known_lines(): void
    {
        $c = $this->context();
        $other = Ingredient::create(['name' => 'Sin producto', 'normalized_name' => 'sin producto', 'base_unit_id' => $c['g']->id, 'is_generic' => true, 'status' => 'active']);
        RecipeIngredient::create(['recipe_id' => $c['recipe']->id, 'ingredient_id' => $other->id, 'quantity' => 100, 'unit_id' => $c['g']->id, 'is_optional' => false]);
        $response = $this->generate($c, 'menu')->assertStatus(201)->assertJsonCount(2, 'data.items')->assertJsonPath('data.estimated_total', '1800.01');
        $id = $response->json('data.id');
        $this->assertSame(1, ShoppingList::find($id)->items()->whereNull('estimated_price')->count());
        $c['price']->delete();
        $this->generate($c, 'menu')->assertOk()->assertJsonPath('data.estimated_total', '0.00');
        $this->assertSame(2, ShoppingList::find($id)->items()->whereNull('estimated_price')->count());
    }

    public function test_history_total_is_zero_and_regeneration_does_not_retain_previous_manual_price(): void
    {
        $c = $this->context(); $this->history($c);
        $response = $this->generate($c, 'history')->assertStatus(201)->assertJsonPath('data.estimated_total', '0.00');
        $id = $response->json('data.id');
        $item = ShoppingList::find($id)->items()->first();
        $this->assertNull($item->estimated_price, 'Historical quantities are not new product price estimates.');
        $this->patchJson($c['base'].'/shopping-lists/'.$id.'/items/'.$item->id, ['estimated_price' => 500])->assertOk();
        $this->assertTotal($c, $id, '500.00');
        $this->generate($c, 'history')->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.estimated_total', '0.00');
        $this->assertTotal($c, $id, '0.00');
        $this->assertNull(ShoppingList::find($id)->items()->first()->estimated_price);
        $this->getJson($c['base'].'/budgets/'.$c['budget']->id.'/projection')->assertOk()
            ->assertJsonPath('data.planned_amount', null)->assertJsonPath('data.spent_amount', 0);
    }

    public function test_recipe_addition_preserves_incremental_response_and_persists_complete_parent_total(): void
    {
        $c = $this->context();
        $list = $this->postJson($c['base'].'/shopping-lists', ['source_type' => 'manual'])->assertStatus(201);
        $id = $list->json('data.id');
        $manualId = $this->postJson($c['base'].'/shopping-lists/'.$id.'/items',
            ['free_text_name' => 'Otro articulo', 'quantity' => 1, 'unit_id' => $c['g']->id, 'estimated_price' => 100])->assertStatus(201)->json('data.id');
        $this->generate($c, 'recipe', $id)->assertOk()->assertJsonPath('data.estimated_total', 1800.01)
            ->assertJsonPath('data.shopping_list.estimated_total', '1900.01')->assertJsonPath('data.items_added', 1);
        $this->assertTotal($c, $id, '1900.01');
        $c['price']->update(['price' => 9999]);
        $this->generate($c, 'recipe', $id)->assertOk()->assertJsonPath('data.estimated_total', 0)
            ->assertJsonPath('data.items_added', 0)->assertJsonPath('data.items_skipped_duplicate', 1)
            ->assertJsonPath('data.shopping_list.estimated_total', '1900.01');
        $this->assertSame('100.00', ShoppingListItem::find($manualId)->estimated_price);
        $this->assertSame(2, ShoppingList::find($id)->items()->count());
        $this->getJson($c['base'].'/budgets/'.$c['budget']->id.'/projection')->assertOk()->assertJsonPath('data.planned_amount', 1900.01);
    }

    public function test_new_recipe_list_has_complete_total_in_both_response_locations(): void
    {
        $c = $this->context();
        $response = $this->generate($c, 'recipe')->assertStatus(201)
            ->assertJsonPath('data.estimated_total', 1800.01)->assertJsonPath('data.shopping_list.estimated_total', '1800.01');
        $this->assertTotal($c, $response->json('data.shopping_list.id'), '1800.01');
    }

    /** @dataProvider protectedStates */
    public function test_open_processed_or_purchased_list_is_not_replaced_or_extended(string $kind, string $state): void
    {
        $c = $this->context();
        if ($kind === 'history') $this->history($c);
        $list = ShoppingList::create(['family_group_id' => $c['group']->id, 'created_by' => $c['user']->id,
            'meal_plan_id' => $kind === 'menu' ? $c['plan']->id : null,
            'source_type' => $kind === 'history' ? 'history' : 'manual', 'status' => 'active', 'estimated_total' => 777]);
        $item = ShoppingListItem::create(['shopping_list_id' => $list->id, 'product_id' => $c['product']->id,
            'quantity' => 1, 'unit_id' => $c['package']->id, 'estimated_price' => 777, 'status' => $state === 'purchased' ? 'purchased' : 'pending',
            'stock_processed_at' => $state === 'processed' ? now() : null]);
        if (in_array($state, ['purchase', 'linked_item'], true)) {
            $purchase = Purchase::create(['family_group_id' => $c['group']->id, 'shopping_list_id' => $list->id,
                'user_id' => $c['user']->id, 'purchase_date' => now(), 'status' => 'confirmed', 'actual_total' => 777]);
            if ($state === 'linked_item') {
                $purchaseItem = PurchaseItem::create(['purchase_id' => $purchase->id, 'product_id' => $c['product']->id,
                    'quantity' => 1, 'unit_id' => $c['package']->id, 'unit_price' => 777, 'total_price' => 777]);
                $item->update(['purchase_item_id' => $purchaseItem->id]);
            }
        }
        $this->generate($c, $kind, $list->id)->assertStatus(409);
        $this->assertSame(1, ShoppingList::where('family_group_id', $c['group']->id)->count());
        $this->assertSame('777.00', $list->fresh()->estimated_total);
        $this->assertSame('777.00', $item->fresh()->estimated_price);
        $this->assertSame(1, $list->items()->count());
        if ($state === 'linked_item') $this->assertSame($purchaseItem->id, (int) $item->fresh()->purchase_item_id);
    }

    public function protectedStates(): array
    {
        $cases = [];
        foreach (['menu', 'history', 'recipe'] as $kind) {
            foreach (['purchased', 'processed', 'purchase', 'linked_item'] as $state) $cases[$kind.' '.$state] = [$kind, $state];
        }
        return $cases;
    }

    /** @dataProvider closedStates */
    public function test_closed_lists_are_preserved_and_implicit_generation_creates_a_new_list(string $kind, string $status): void
    {
        $c = $this->context();
        if ($kind === 'history') $this->history($c);
        $list = ShoppingList::create(['family_group_id' => $c['group']->id, 'created_by' => $c['user']->id,
            'meal_plan_id' => $kind === 'menu' ? $c['plan']->id : null,
            'source_type' => $kind === 'history' ? 'history' : 'manual', 'status' => $status, 'estimated_total' => 777]);
        $item = ShoppingListItem::create(['shopping_list_id' => $list->id, 'product_id' => $c['product']->id,
            'quantity' => 1, 'unit_id' => $c['package']->id, 'estimated_price' => 777, 'status' => 'pending']);
        $response = $this->generate($c, $kind, $list->id);
        if ($kind === 'recipe') $response->assertStatus(409);
        else $this->assertNotEquals($list->id, $response->assertStatus(201)->json('data.id'));
        $this->assertSame($status, $list->fresh()->status);
        $this->assertSame('777.00', $list->fresh()->estimated_total);
        $this->assertNotNull($item->fresh());
    }

    public function closedStates(): array
    {
        $cases = [];
        foreach (['menu', 'history', 'recipe'] as $kind) {
            foreach (['completed', 'cancelled', 'in_progress'] as $status) $cases[$kind.' '.$status] = [$kind, $status];
        }
        return $cases;
    }

    /** @dataProvider generators */
    public function test_audit_failure_rolls_back_replacement_or_append_and_total(string $kind): void
    {
        $c = $this->context();
        if ($kind === 'history') $this->history($c);
        $list = ShoppingList::create(['family_group_id' => $c['group']->id, 'created_by' => $c['user']->id,
            'meal_plan_id' => $kind === 'menu' ? $c['plan']->id : null,
            'source_type' => $kind === 'history' ? 'history' : 'manual', 'status' => 'draft', 'estimated_total' => 123]);
        $item = ShoppingListItem::create(['shopping_list_id' => $list->id, 'free_text_name' => 'Preservar',
            'quantity' => 1, 'unit_id' => $c['g']->id, 'estimated_price' => 123, 'status' => 'pending']);
        $auditCount = AuditLog::count();
        AuditLog::creating(function ($entry) {
            if (strpos($entry->action, 'shopping_list.generated') === 0) throw new \RuntimeException('QA simulated generation audit failure');
        });
        try { $this->generate($c, $kind, $list->id)->assertStatus(500); }
        finally { AuditLog::flushEventListeners(); }
        $this->assertSame('draft', $list->fresh()->status);
        $this->assertSame('123.00', $list->fresh()->estimated_total);
        $this->assertNotNull($item->fresh());
        $this->assertSame(1, $list->items()->count());
        $this->assertSame($auditCount, AuditLog::count());
    }

    public function generators(): array { return [['menu'], ['history'], ['recipe']]; }
}
