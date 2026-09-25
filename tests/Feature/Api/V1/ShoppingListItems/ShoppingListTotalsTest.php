<?php

namespace Tests\Feature\Api\V1\ShoppingListItems;

use App\AuditLog;
use App\Budget;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Product;
use App\Purchase;
use App\PurchaseItem;
use App\ShoppingList;
use App\ShoppingListItem;
use App\StockItem;
use App\UnitMeasure;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingListTotalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-25 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function context(): array
    {
        $user = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id, 'status' => 'active']);
        factory(FamilyGroupMember::class)->create(['family_group_id' => $group->id, 'user_id' => $user->id, 'role_in_group' => 'owner', 'status' => 'active']);
        $unit = UnitMeasure::create(['name' => 'Unidad QA', 'code' => uniqid('total_'), 'symbol' => 'u', 'type' => 'unit', 'status' => 'active']);
        $budget = Budget::create(['family_group_id' => $group->id, 'year' => 2026, 'month' => 9, 'total_amount' => 10000, 'currency' => 'ARS', 'status' => 'active']);
        $this->actingAs($user);
        $base = '/api/v1/family-groups/'.$group->id;
        $created = $this->postJson($base.'/shopping-lists', ['source_type' => 'manual'])->assertStatus(201);
        $list = ShoppingList::findOrFail($created->json('data.id'));
        $url = $base.'/shopping-lists/'.$list->id;
        return compact('user', 'group', 'unit', 'budget', 'base', 'list', 'url', 'created');
    }

    private function add(array $c, array $data = [])
    {
        return $this->postJson($c['url'].'/items', array_merge([
            'free_text_name' => uniqid('Articulo QA '), 'quantity' => 1, 'unit_id' => $c['unit']->id,
        ], $data))->assertStatus(201);
    }

    private function seedPricedItem(array $c, float $price = 100): ShoppingListItem
    {
        $c['list']->update(['estimated_total' => $price]);
        return ShoppingListItem::create(['shopping_list_id' => $c['list']->id, 'free_text_name' => 'Articulo inicial',
            'quantity' => 1, 'unit_id' => $c['unit']->id, 'estimated_price' => $price, 'status' => 'pending']);
    }

    private function assertTotal(array $c, string $total): void
    {
        $this->getJson($c['url'])->assertOk()->assertJsonPath('data.estimated_total', $total);
        $this->assertSame($total, $c['list']->fresh()->estimated_total);
        $rows = $this->getJson($c['base'].'/shopping-lists')->assertOk()->json('data');
        $row = collect($rows)->firstWhere('id', $c['list']->id);
        $this->assertSame($total, $row['estimated_total']);
    }

    private function product(UnitMeasure $unit): Product
    {
        $name = uniqid('Producto QA ');
        return Product::create(['name' => $name, 'nombre' => $name, 'normalized_name' => strtolower($name),
            'brand_id' => 0, 'codigo' => uniqid('TOTAL-'), 'img' => '', 'habilitado' => 1, 'supply_id' => 0,
            'default_unit_id' => $unit->id, 'is_active' => true, 'status' => 'active']);
    }

    public function test_new_empty_manual_list_has_zero_total(): void
    {
        $c = $this->context();
        $c['created']->assertJsonPath('data.estimated_total', '0.00');
        $this->assertTotal($c, '0.00');
    }

    public function test_endpoint_create_update_delete_recalculate_detail_and_collection(): void
    {
        $c = $this->context();
        $id = $this->add($c, ['estimated_price' => 1800.01])->json('data.id');
        $this->assertTotal($c, '1800.01');
        $this->patchJson($c['url'].'/items/'.$id, ['quantity' => 2])->assertOk()->assertJsonPath('data.estimated_subtotal', 3600.02);
        $this->assertTotal($c, '3600.02');
        $this->patchJson($c['url'].'/items/'.$id, ['estimated_price' => 1000])->assertOk();
        $this->assertTotal($c, '2000.00');
        $this->patchJson($c['url'].'/items/'.$id, ['status' => 'skipped'])->assertOk();
        $this->assertTotal($c, '0.00');
        $this->patchJson($c['url'].'/items/'.$id, ['status' => 'pending'])->assertOk();
        $this->assertTotal($c, '2000.00');
        $this->deleteJson($c['url'].'/items/'.$id)->assertStatus(204);
        $this->assertTotal($c, '0.00');
    }

    /** @dataProvider totalUpdates */
    public function test_each_total_affecting_update_recalculates_existing_total(array $fields, string $expected): void
    {
        $c = $this->context();
        $item = $this->seedPricedItem($c);
        $this->patchJson($c['url'].'/items/'.$item->id, $fields)->assertOk();
        $this->assertTotal($c, $expected);
    }

    public function totalUpdates(): array
    {
        return [
            'quantity' => [['quantity' => 2.5], '250.00'],
            'price' => [['estimated_price' => 12.34], '12.34'],
            'zero price' => [['estimated_price' => 0], '0.00'],
            'unknown price' => [['estimated_price' => null], '0.00'],
            'skipped' => [['status' => 'skipped'], '0.00'],
            'cancelled' => [['status' => 'cancelled'], '0.00'],
            'purchased not processed' => [['status' => 'purchased'], '100.00'],
        ];
    }

    public function test_delete_removes_last_priced_item_from_existing_total(): void
    {
        $c = $this->context();
        $item = $this->seedPricedItem($c);
        $this->deleteJson($c['url'].'/items/'.$item->id)->assertStatus(204);
        $this->assertTotal($c, '0.00');
    }

    public function test_unknown_zero_partial_and_fractional_totals_match_visible_subtotals(): void
    {
        $c = $this->context();
        $unknown = $this->add($c)->assertJsonPath('data.estimated_price', null)->assertJsonPath('data.estimated_subtotal', null);
        $this->assertTotal($c, '0.00');
        $this->add($c, ['estimated_price' => 0])->assertJsonPath('data.estimated_subtotal', 0);
        $this->add($c, ['estimated_price' => 0.01, 'quantity' => 0.5])->assertJsonPath('data.estimated_subtotal', 0.01);
        $this->add($c, ['estimated_price' => 0.01, 'quantity' => 0.5])->assertJsonPath('data.estimated_subtotal', 0.01);
        $this->add($c, ['estimated_price' => 999, 'status' => 'skipped']);
        $this->add($c, ['estimated_price' => 999, 'status' => 'cancelled']);
        $this->assertTotal($c, '0.02');
        $this->assertNull(ShoppingListItem::find($unknown->json('data.id'))->estimated_price);
        $this->getJson($c['base'].'/budgets/'.$c['budget']->id.'/projection')->assertOk()
            ->assertJsonPath('data.planned_amount', 0.02)->assertJsonPath('data.available_projected', 9999.98);
    }

    /** @dataProvider changedReference */
    public function test_product_or_unit_invalidation_clears_the_old_total(string $field): void
    {
        $c = $this->context();
        $item = $this->seedPricedItem($c);
        $item->update(['product_id' => $this->product($c['unit'])->id, 'free_text_name' => null, 'price_source' => 'supermarket']);
        $id = $field === 'product_id' ? $this->product($c['unit'])->id
            : UnitMeasure::create(['name' => 'Otra unidad', 'code' => uniqid('other_'), 'type' => 'unit', 'status' => 'active'])->id;
        $this->patchJson($c['url'].'/items/'.$item->id, [$field => $id])->assertOk()
            ->assertJsonPath('data.estimated_price', null)->assertJsonPath('data.price_source', 'manual');
        $this->assertTotal($c, '0.00');
        $this->patchJson($c['url'].'/items/'.$item->id, ['estimated_price' => 25])->assertOk();
        $this->assertTotal($c, '25.00');
    }

    public function changedReference(): array { return [['product_id'], ['unit_id']]; }

    public function test_projection_uses_correct_household_month_and_never_duplicates_confirmed_purchase(): void
    {
        $c = $this->context();
        $product = $this->product($c['unit']);
        $itemId = $this->add($c, ['free_text_name' => null, 'product_id' => $product->id, 'estimated_price' => 1800.01, 'actual_price' => 1700])->json('data.id');
        $previous = ShoppingList::create(['family_group_id' => $c['group']->id, 'created_by' => $c['user']->id,
            'source_type' => 'manual', 'status' => 'draft', 'estimated_total' => 700]);
        $previous->created_at = '2026-08-15 12:00:00'; $previous->save();
        $otherGroup = factory(FamilyGroup::class)->create(['owner_user_id' => $c['user']->id]);
        ShoppingList::create(['family_group_id' => $otherGroup->id, 'created_by' => $c['user']->id,
            'source_type' => 'manual', 'status' => 'draft', 'estimated_total' => 900]);
        $projection = $this->getJson($c['base'].'/budgets/'.$c['budget']->id.'/projection')->assertOk()
            ->assertJsonPath('data.planned_amount', 1800.01)->assertJsonPath('data.spent_amount', 0)
            ->assertJsonPath('data.available_projected', 8199.99)->assertJsonCount(1, 'data.planned_sources');
        $this->assertSame($c['list']->id, $projection->json('data.planned_sources.0.shopping_list_id'));
        $this->patchJson($c['url'], ['status' => 'active'])->assertOk();
        $this->patchJson($c['url'].'/items/'.$itemId, ['status' => 'purchased'])->assertOk();
        $purchaseResponse = $this->postJson($c['url'].'/complete', ['items' => [
            ['shopping_list_item_id' => $itemId, 'add_to_stock' => true],
        ]])->assertOk();
        $this->assertSame(1, Purchase::where('shopping_list_id', $c['list']->id)->count());
        $this->assertSame(1, StockItem::where('family_group_id', $c['group']->id)->count());
        $this->getJson($c['base'].'/budgets/'.$c['budget']->id.'/projection')->assertOk()
            ->assertJsonPath('data.planned_amount', null)->assertJsonPath('data.spent_amount', 1700)
            ->assertJsonPath('data.available_projected', 8300)->assertJsonCount(0, 'data.planned_sources');
        $this->postJson($c['url'].'/complete', [])->assertStatus(409);
        $this->assertSame(1, Purchase::where('shopping_list_id', $c['list']->id)->count());
        $this->assertTotal($c, '1800.01');
    }

    /** @dataProvider closedLists */
    public function test_closed_list_rejects_crud_and_preserves_snapshot(string $status): void
    {
        $c = $this->context();
        $item = $this->seedPricedItem($c);
        $c['list']->update(['status' => $status]);
        $this->postJson($c['url'].'/items', ['free_text_name' => 'nuevo', 'estimated_price' => 20])->assertStatus(409);
        $this->patchJson($c['url'].'/items/'.$item->id, ['quantity' => 2])->assertStatus(409);
        $this->deleteJson($c['url'].'/items/'.$item->id)->assertStatus(409);
        $this->assertTotal($c, '100.00');
        $this->assertSame('1.0000', $item->fresh()->quantity);
    }

    public function closedLists(): array { return [['completed'], ['cancelled']]; }

    /** @dataProvider processedItems */
    public function test_processed_item_cannot_be_updated_or_deleted_even_when_list_reopened(string $link): void
    {
        $c = $this->context();
        $item = $this->seedPricedItem($c);
        if ($link === 'stock_processed_at') {
            $item->update(['stock_processed_at' => now()]);
        } else {
            $product = $this->product($c['unit']);
            $purchase = Purchase::create(['family_group_id' => $c['group']->id, 'shopping_list_id' => $c['list']->id,
                'user_id' => $c['user']->id, 'purchase_date' => now(), 'status' => 'confirmed', 'actual_total' => 100]);
            $purchaseItem = PurchaseItem::create(['purchase_id' => $purchase->id, 'product_id' => $product->id, 'quantity' => 1,
                'unit_id' => $c['unit']->id, 'unit_price' => 100, 'total_price' => 100]);
            $item->update(['purchase_item_id' => $purchaseItem->id]);
        }
        $c['list']->update(['status' => 'active']);
        $this->patchJson($c['url'].'/items/'.$item->id, ['estimated_price' => 12])->assertStatus(409);
        $this->deleteJson($c['url'].'/items/'.$item->id)->assertStatus(409);
        $this->assertTotal($c, '100.00');
        $this->assertSame('100.00', $item->fresh()->estimated_price);
    }

    public function processedItems(): array { return [['stock_processed_at'], ['purchase_item_id']]; }

    /** @dataProvider mutations */
    public function test_audit_failure_rolls_back_item_and_parent_total(string $mutation): void
    {
        $c = $this->context();
        $item = $this->seedPricedItem($c);
        $auditCount = AuditLog::count();
        AuditLog::creating(function ($entry) {
            if ($entry->entity_name === 'shopping_list_items') throw new \RuntimeException('QA simulated audit failure');
        });
        try {
            if ($mutation === 'create') $this->postJson($c['url'].'/items', ['free_text_name' => 'new rollback', 'quantity' => 1, 'unit_id' => $c['unit']->id, 'estimated_price' => 20])->assertStatus(500);
            if ($mutation === 'update') $this->patchJson($c['url'].'/items/'.$item->id, ['estimated_price' => 25])->assertStatus(500);
            if ($mutation === 'delete') $this->deleteJson($c['url'].'/items/'.$item->id)->assertStatus(500);
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertSame(1, $c['list']->items()->count());
        $this->assertSame('100.00', $item->fresh()->estimated_price);
        $this->assertSame($auditCount, AuditLog::count());
        $this->assertTotal($c, '100.00');
    }

    public function mutations(): array { return [['create'], ['update'], ['delete']]; }

    public function test_validation_and_scope_failure_leave_existing_totals_unchanged(): void
    {
        $c = $this->context();
        $item = $this->seedPricedItem($c);
        $this->patchJson($c['url'].'/items/'.$item->id, ['estimated_price' => -1])->assertStatus(422);
        $otherList = ShoppingList::create(['family_group_id' => $c['group']->id, 'created_by' => $c['user']->id, 'status' => 'draft', 'source_type' => 'manual', 'estimated_total' => 20]);
        $wrong = $c['base'].'/shopping-lists/'.$otherList->id.'/items/'.$item->id;
        $this->patchJson($wrong, ['estimated_price' => 20])->assertStatus(404);
        $this->deleteJson($wrong)->assertStatus(404);
        $this->assertTotal($c, '100.00');
        $this->assertSame('20.00', $otherList->fresh()->estimated_total);
    }

    public function test_alternative_selection_uses_the_same_rounded_line_total_as_manual_crud(): void
    {
        $c = $this->context();
        $ingredient = \App\Ingredient::create(['name' => 'Ingrediente de total', 'normalized_name' => 'ingrediente de total',
            'base_unit_id' => $c['unit']->id, 'is_generic' => true, 'status' => 'active']);
        $original = $this->product($c['unit']); $original->update(['ingredient_id' => $ingredient->id]);
        $alternative = $this->product($c['unit']); $alternative->update(['ingredient_id' => $ingredient->id]);
        $chain = \App\SupermarketChain::create(['name' => 'Cadena total', 'code' => uniqid('total_'), 'status' => 'active']);
        $offer = \App\SupermarketProduct::create(['product_id' => $alternative->id, 'supermarket_chain_id' => $chain->id, 'status' => 'active']);
        \App\SupermarketProductPrice::create(['supermarket_product_id' => $offer->id, 'price' => 0.01, 'currency' => 'ARS', 'status' => 'active', 'scraped_at' => now()]);
        $item = $this->add($c, ['free_text_name' => null, 'product_id' => $original->id, 'quantity' => 0.5, 'estimated_price' => 0.01]);
        $this->add($c, ['quantity' => 0.5, 'estimated_price' => 0.01]);
        $this->assertTotal($c, '0.02');
        $this->postJson($c['url'].'/items/'.$item->json('data.id').'/select-alternative', ['supermarket_product_id' => $offer->id])
            ->assertOk()->assertJsonPath('data.estimated_subtotal', 0.01);
        $this->assertTotal($c, '0.02');
    }

}
