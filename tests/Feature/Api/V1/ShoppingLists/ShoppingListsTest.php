<?php

namespace Tests\Feature\Api\V1\ShoppingLists;

use App\AuditLog;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Ingredient;
use App\MealPlan;
use App\ShoppingList;
use App\ShoppingListItem;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingListsTest extends TestCase
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

    private function mealPlan(FamilyGroup $group, User $user)
    {
        return MealPlan::create([
            'family_group_id' => $group->id,
            'created_by' => $user->id,
            'period_type' => 'daily',
            'start_date' => '2026-06-16',
            'end_date' => '2026-06-16',
            'status' => 'draft',
        ]);
    }

    private function list(FamilyGroup $group, User $user, array $data = [])
    {
        return ShoppingList::create(array_merge([
            'family_group_id' => $group->id,
            'created_by' => $user->id,
            'source_type' => 'manual',
            'status' => 'draft',
        ], $data));
    }

    public function test_auth_required()
    {
        $this->getJson('/api/v1/family-groups/1/shopping-lists')->assertStatus(401);
    }

    public function test_access_to_other_group_rejected()
    {
        [$user, $group] = $this->groupWithMember();
        [$other, $otherGroup] = $this->groupWithMember();
        $list = $this->list($otherGroup, $other);

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'SHOPPING_LIST_NOT_FOUND');
    }

    public function test_list_returns_group_lists_with_filters()
    {
        [$user, $group] = $this->groupWithMember();
        [$other, $otherGroup] = $this->groupWithMember();
        $this->list($group, $user, ['source_type' => 'manual', 'status' => 'draft']);
        $this->list($group, $user, ['source_type' => 'meal_plan', 'status' => 'completed']);
        $this->list($otherGroup, $other, ['source_type' => 'manual', 'status' => 'draft']);

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/shopping-lists?status=draft&source_type=manual')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.source_type', 'manual');
    }

    public function test_detail_includes_items()
    {
        [$user, $group] = $this->groupWithMember();
        $list = $this->list($group, $user);
        $unit = UnitMeasure::create(['code' => 'sl_'.uniqid(), 'name' => 'Unidad', 'type' => 'unit', 'symbol' => 'u', 'status' => 'active']);
        $ingredient = Ingredient::create([
            'name' => 'Ingrediente',
            'normalized_name' => 'ingrediente',
            'base_unit_id' => $unit->id,
            'is_generic' => true,
            'is_preparation' => false,
            'is_supplement' => false,
            'status' => 'active',
        ]);
        ShoppingListItem::create([
            'shopping_list_id' => $list->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 2,
            'unit_id' => $unit->id,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id)
            ->assertStatus(200)
            ->assertJsonPath('data.items.0.ingredient.id', $ingredient->id);
    }

    public function test_create_manual_list()
    {
        [$user, $group] = $this->groupWithMember();

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists', [
                'source_type' => 'manual',
                'optimization_mode' => 'simple',
            ])->assertStatus(201)
            ->assertJsonPath('data.source_type', 'manual');

        $this->assertDatabaseHas('shopping_lists', [
            'family_group_id' => $group->id,
            'created_by' => $user->id,
            'source_type' => 'manual',
        ]);
    }

    public function test_invalid_source_rejected()
    {
        [$user, $group] = $this->groupWithMember();

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists', [
                'source_type' => 'external',
            ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_update_partial()
    {
        [$user, $group] = $this->groupWithMember();
        $list = $this->list($group, $user, ['status' => 'draft']);

        $this->actingAs($user)
            ->patchJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id, [
                'status' => 'active',
            ])->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_delete_soft_deletes_without_deleting_items()
    {
        [$user, $group] = $this->groupWithMember();
        $list = $this->list($group, $user);
        $unit = UnitMeasure::create(['code' => 'sl_'.uniqid(), 'name' => 'Unidad', 'type' => 'unit', 'symbol' => 'u', 'status' => 'active']);
        ShoppingListItem::create([
            'shopping_list_id' => $list->id,
            'quantity' => 1,
            'unit_id' => $unit->id,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/'.$list->id)
            ->assertStatus(200);

        $this->assertSoftDeleted('shopping_lists', ['id' => $list->id]);
        $this->assertDatabaseHas('shopping_list_items', ['shopping_list_id' => $list->id]);
    }

    public function test_writes_are_audited()
    {
        [$user, $group] = $this->groupWithMember();

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists', [
                'source_type' => 'manual',
            ])->assertStatus(201);

        $this->assertTrue(AuditLog::where('entity_name', 'shopping_lists')->where('action', 'shopping_list.created')->exists());
    }
}
