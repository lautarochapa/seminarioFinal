<?php

namespace Tests\Feature\Api\V1\BudgetSummary;

use App\Budget;
use App\BudgetMovement;
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

    public function test_adjustments_change_all_budget_balances_and_can_be_compensated()
    {
        Carbon::setTestNow('2026-06-15');
        try {
            [$user, $group] = $this->groupWithMember();
            $budget = $this->budget($group);
            $purchase = $this->confirmedPurchase($group, $user, '2026-06-10', 2100, 'stock_added');
            $this->confirmedPurchase($group, $user, '2026-06-11', 500, 'draft');
            $this->shoppingList($group, $user, 300);
            BudgetMovement::create([
                'budget_id' => $budget->id, 'movement_type' => 'purchase',
                'amount' => 2100, 'related_purchase_id' => $purchase->id,
            ]);
            $base = "/api/v1/family-groups/{$group->id}/budgets";
            $this->actingAs($user);

            foreach ([[-100.25, 2200.25], [40.10, 2160.15], [60.15, 2100]] as [$amount, $spent]) {
                $this->postJson("{$base}/{$budget->id}/adjustments", [
                    'amount' => $amount, 'description' => 'QA balance adjustment',
                ])->assertStatus(201);
                $available = round(10000 - $spent, 2);
                $summary = $this->getJson("{$base}/{$budget->id}/summary")->assertOk();
                $this->assertEquals($spent, $summary->json('data.spent_amount'));
                $this->assertEquals($available, $summary->json('data.available_amount'));
                $this->assertEquals(round($spent / 100, 2), $summary->json('data.consumed_percent'));
                $this->assertEquals(1, $summary->json('data.purchase_count'));
                $projection = $this->getJson("{$base}/{$budget->id}/projection")->assertOk();
                $this->assertEquals($spent, $projection->json('data.spent_amount'));
                $this->assertEquals(round($available - 300, 2), $projection->json('data.available_projected'));
                foreach ([['/current', 'data'], ['', 'data.0']] as [$suffix, $key]) {
                    $response = $this->getJson($base.$suffix)->assertOk();
                    $this->assertEquals($spent, $response->json($key.'.used_amount'));
                    $this->assertEquals($available, $response->json($key.'.available_amount'));
                }
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_adjustments_are_scoped_to_budget_and_ignore_other_ledger_types()
    {
        [$user, $group] = $this->groupWithMember();
        [, $otherGroup] = $this->groupWithMember();
        $budget = $this->budget($group);
        foreach ([$this->budget($group, ['month' => 7]), $this->budget($otherGroup)] as $other) {
            BudgetMovement::create(['budget_id' => $other->id, 'movement_type' => 'adjustment', 'amount' => -900]);
        }
        foreach (['planned_purchase', 'reservation', 'release'] as $type) {
            BudgetMovement::create(['budget_id' => $budget->id, 'movement_type' => $type, 'amount' => 500]);
        }
        // An adjustment belongs to its selected budget, even if entered later.
        BudgetMovement::create([
            'budget_id' => $budget->id, 'movement_type' => 'adjustment',
            'amount' => 125.50, 'created_at' => '2026-07-15 12:00:00',
        ]);
        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/summary")->assertOk();
        $this->assertEquals(-125.50, $response->json('data.spent_amount'));
        $this->assertEquals(10125.50, $response->json('data.available_amount'));
        $this->assertEquals(0, $response->json('data.purchase_count'));
    }

    public function test_expense_adjustment_exceeding_budget_keeps_available_at_zero()
    {
        [$user, $group] = $this->groupWithMember();
        $budget = $this->budget($group);
        BudgetMovement::create(['budget_id' => $budget->id, 'movement_type' => 'adjustment', 'amount' => -11000]);
        $base = "/api/v1/family-groups/{$group->id}/budgets/{$budget->id}";
        $response = $this->actingAs($user)->getJson($base.'/summary')->assertOk();
        $this->assertEquals(11000, $response->json('data.spent_amount'));
        $this->assertEquals(0, $response->json('data.available_amount'));
        $this->assertEquals(110, $response->json('data.consumed_percent'));
        $response = $this->getJson($base.'/projection')->assertOk();
        $this->assertEquals(0, $response->json('data.available_projected'));
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

    public function test_summary_excludes_unconfirmed_and_deleted_purchases()
    {
        [$user, $group] = $this->groupWithMember();
        $budget = $this->budget($group, ['total_amount' => 10000]);
        $this->confirmedPurchase($group, $user, '2026-06-10', 2500, 'confirmed');
        $this->confirmedPurchase($group, $user, '2026-06-11', 1500, 'draft');
        $deleted = $this->confirmedPurchase($group, $user, '2026-06-12', 2000, 'confirmed');
        $deleted->delete();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/summary")
            ->assertStatus(200);

        $this->assertEquals(2500.0, $response->json('data.spent_amount'));
        $this->assertEquals(1,      $response->json('data.purchase_count'));
    }

    public function test_summary_counts_stock_added_purchase_once()
    {
        [$user, $group] = $this->groupWithMember();
        $budget = $this->budget($group, ['total_amount' => 10000]);
        $this->confirmedPurchase($group, $user, '2026-06-10', 2500, 'stock_added');

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/summary")
            ->assertStatus(200);

        $this->assertEquals(2500.0, $response->json('data.spent_amount'));
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
