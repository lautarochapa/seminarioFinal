<?php

namespace Tests\Feature\Api\V1\BudgetSummary;

use App\Budget;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Purchase;
use App\ShoppingList;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function groupWithMember()
    {
        $user  = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id, 'status' => 'active']);
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id'         => $user->id,
            'role_in_group'   => 'owner',
            'status'          => 'active',
        ]);
        return [$user, $group];
    }

    private function budget(FamilyGroup $group, array $overrides = [])
    {
        return Budget::create(array_merge([
            'family_group_id' => $group->id,
            'year'            => 2026,
            'month'           => 6,
            'total_amount'    => 10000,
            'currency'        => 'ARS',
            'status'          => 'active',
        ], $overrides));
    }

    private function confirmedPurchase(FamilyGroup $group, User $user, string $date, float $amount, string $status = 'confirmed', ?int $listId = null)
    {
        return Purchase::create([
            'family_group_id'  => $group->id,
            'user_id'          => $user->id,
            'purchase_date'    => $date,
            'status'           => $status,
            'actual_total'     => $amount,
            'shopping_list_id' => $listId,
        ]);
    }

    private function shoppingList(FamilyGroup $group, User $user, float $estimated, string $status = 'draft')
    {
        return ShoppingList::create([
            'family_group_id' => $group->id,
            'created_by'      => $user->id,
            'source_type'     => 'manual',
            'status'          => $status,
            'estimated_total' => $estimated,
        ]);
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/v1/family-groups/1/budgets/1/summary')->assertStatus(401);
    }

    public function test_access_to_foreign_group_budget_is_denied()
    {
        [$user]         = $this->groupWithMember();
        [, $otherGroup] = $this->groupWithMember();
        $budget         = $this->budget($otherGroup);

        $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$otherGroup->id}/budgets/{$budget->id}/summary")
            ->assertStatus(403);
    }

    public function test_summary_returns_correct_totals_for_period()
    {
        [$user, $group] = $this->groupWithMember();
        $budget = $this->budget($group, ['total_amount' => 10000]);
        $this->confirmedPurchase($group, $user, '2026-06-10', 3000);
        $this->confirmedPurchase($group, $user, '2026-06-20', 2000);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/summary")
            ->assertStatus(200);

        $this->assertEquals(10000.0, $response->json('data.total_amount'));
        $this->assertEquals(5000.0,  $response->json('data.spent_amount'));
        $this->assertEquals(5000.0,  $response->json('data.available_amount'));
        $this->assertEquals(50.0,    $response->json('data.consumed_percent'));
        $this->assertEquals(2,       $response->json('data.purchase_count'));
        $this->assertEquals('ARS',   $response->json('data.currency'));
        $this->assertArrayHasKey('trace_id', $response->json());
    }

    public function test_summary_excludes_purchases_outside_budget_period()
    {
        [$user, $group] = $this->groupWithMember();
        $budget = $this->budget($group, ['month' => 6, 'total_amount' => 10000]);
        $this->confirmedPurchase($group, $user, '2026-06-15', 4000);
        $this->confirmedPurchase($group, $user, '2026-07-01', 3000);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/summary")
            ->assertStatus(200);

        $this->assertEquals(4000.0, $response->json('data.spent_amount'));
        $this->assertEquals(1,      $response->json('data.purchase_count'));
    }

    public function test_summary_excludes_cancelled_purchases()
    {
        [$user, $group] = $this->groupWithMember();
        $budget = $this->budget($group, ['total_amount' => 10000]);
        $this->confirmedPurchase($group, $user, '2026-06-10', 5000);
        $this->confirmedPurchase($group, $user, '2026-06-15', 3000, 'cancelled');

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/summary")
            ->assertStatus(200);

        $this->assertEquals(5000.0, $response->json('data.spent_amount'));
        $this->assertEquals(1,      $response->json('data.purchase_count'));
    }

    public function test_projection_includes_pending_shopping_lists_as_planned()
    {
        Carbon::setTestNow('2026-06-15');
        [$user, $group] = $this->groupWithMember();
        $budget = $this->budget($group, ['total_amount' => 10000]);
        $this->confirmedPurchase($group, $user, '2026-06-10', 2000);
        $this->shoppingList($group, $user, 3000);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/projection")
            ->assertStatus(200);

        $this->assertEquals(2000.0, $response->json('data.spent_amount'));
        $this->assertEquals(3000.0, $response->json('data.planned_amount'));
        $this->assertEquals(5000.0, $response->json('data.available_projected'));
        $this->assertIsArray($response->json('data.planned_sources'));
        Carbon::setTestNow();
    }

    public function test_projection_does_not_double_count_list_already_purchased_and_returns_null_when_no_pending_lists()
    {
        Carbon::setTestNow('2026-06-15');
        [$user, $group] = $this->groupWithMember();
        $budget = $this->budget($group, ['total_amount' => 10000]);
        $list   = $this->shoppingList($group, $user, 3000);
        $this->confirmedPurchase($group, $user, '2026-06-10', 2500, 'confirmed', $list->id);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/projection")
            ->assertStatus(200);

        $this->assertEquals(2500.0, $response->json('data.spent_amount'));
        $this->assertNull($response->json('data.planned_amount'));
        $this->assertEmpty($response->json('data.planned_sources'));
        $this->assertEquals(7500.0, $response->json('data.available_projected'));
        Carbon::setTestNow();
    }

    public function test_summary_and_projection_respect_budget_currency()
    {
        [$user, $group] = $this->groupWithMember();
        $budget = $this->budget($group, ['currency' => 'USD', 'total_amount' => 1000]);
        $this->confirmedPurchase($group, $user, '2026-06-10', 200);

        $summary = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/summary")
            ->assertStatus(200);

        $projection = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/projection")
            ->assertStatus(200);

        $this->assertEquals('USD', $summary->json('data.currency'));
        $this->assertEquals('USD', $projection->json('data.currency'));
    }
}
