<?php

namespace Tests\Feature\Api\V1\UserHome;

use App\FamilyGroup;
use App\FamilyGroupMember;
use App\ShoppingList;
use App\ShoppingListItem;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserHomeSummaryTest extends TestCase
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

    public function test_home_summary_empty_state()
    {
        [$user, $group] = $this->groupWithMember();

        $this->actingAs($user)
            ->getJson('/api/v1/users/me/home-summary?family_group_id='.$group->id)
            ->assertStatus(200)
            ->assertJsonPath('data.family_group_id', $group->id)
            ->assertJsonPath('data.stock.products', 0)
            ->assertJsonPath('data.recipes.available', 0);
    }

    public function test_home_summary_includes_pending_shopping_work()
    {
        [$user, $group] = $this->groupWithMember();
        $list = ShoppingList::create([
            'family_group_id' => $group->id,
            'created_by' => $user->id,
            'source_type' => 'manual',
            'status' => 'active',
        ]);
        $unit = UnitMeasure::create([
            'code' => 'home_'.uniqid(),
            'name' => 'Unidad',
            'type' => 'unit',
            'symbol' => 'u',
            'status' => 'active',
        ]);
        ShoppingListItem::create([
            'shopping_list_id' => $list->id,
            'free_text_name' => 'papel higienico',
            'quantity' => 1,
            'unit_id' => $unit->id,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/users/me/home-summary?family_group_id='.$group->id)
            ->assertStatus(200)
            ->assertJsonPath('data.shopping.active_lists', 1)
            ->assertJsonPath('data.shopping.pending_items', 1)
            ->assertJsonPath('data.actions.0.type', 'empty_stock');
    }
}
