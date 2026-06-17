<?php

namespace Tests\Feature\Api\V1\ShoppingListItems;

use App\AuditLog;
use App\Brand;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Ingredient;
use App\Product;
use App\ShoppingList;
use App\ShoppingListItem;
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
