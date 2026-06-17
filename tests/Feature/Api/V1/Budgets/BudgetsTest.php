<?php

namespace Tests\Feature\Api\V1\Budgets;

use App\AuditLog;
use App\Budget;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Purchase;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetsTest extends TestCase
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
            'month'           => 1,
            'total_amount'    => 50000,
            'currency'        => 'ARS',
            'status'          => 'active',
        ], $overrides));
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/v1/family-groups/1/budgets')->assertStatus(401);
    }

    public function test_access_to_foreign_group_budget_is_denied()
    {
        [$user]         = $this->groupWithMember();
        [, $otherGroup] = $this->groupWithMember();

        $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$otherGroup->id}/budgets")
            ->assertStatus(403);
    }

    public function test_create_budget_returns_201_and_logs_audit()
    {
        [$user, $group] = $this->groupWithMember();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/budgets", [
                'year'         => 2026,
                'month'        => 8,
                'total_amount' => 30000,
                'currency'     => 'ARS',
            ])
            ->assertStatus(201);

        $this->assertEquals(30000.0, $response->json('data.total_amount'));
        $this->assertEquals('ARS', $response->json('data.currency'));
        $this->assertArrayHasKey('trace_id', $response->json());
        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'budget.create',
            'entity_name' => 'budgets',
        ]);
    }

    public function test_duplicate_budget_for_same_period_returns_409()
    {
        [$user, $group] = $this->groupWithMember();
        $this->budget($group, ['year' => 2026, 'month' => 3]);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/budgets", [
                'year'         => 2026,
                'month'        => 3,
                'total_amount' => 20000,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'BUDGET_PERIOD_ALREADY_EXISTS');
    }

    public function test_current_returns_budget_for_this_month_with_usage()
    {
        [$user, $group] = $this->groupWithMember();
        $now = now();
        $budget = $this->budget($group, ['year' => (int) $now->format('Y'), 'month' => (int) $now->format('n'), 'total_amount' => 10000]);

        Purchase::create([
            'family_group_id' => $group->id,
            'user_id'         => $user->id,
            'purchase_date'   => $now->toDateString(),
            'status'          => 'confirmed',
            'actual_total'    => 3000,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/current")
            ->assertStatus(200);

        $this->assertEquals(3000.0, $response->json('data.used_amount'));
        $this->assertEquals(7000.0, $response->json('data.available_amount'));
        $this->assertEquals(30.0,   $response->json('data.consumed_percent'));
    }

    public function test_list_returns_paginated_budgets_ordered_by_period()
    {
        [$user, $group] = $this->groupWithMember();
        $this->budget($group, ['year' => 2026, 'month' => 1]);
        $this->budget($group, ['year' => 2026, 'month' => 2]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets")
            ->assertStatus(200);

        $this->assertCount(2, $response->json('data'));
        $this->assertArrayHasKey('meta', $response->json());
        $this->assertEquals(2026, $response->json('data.0.year'));
        $this->assertEquals(2,    $response->json('data.0.month'));
    }

    public function test_partial_update_changes_amount_and_audits()
    {
        [$user, $group] = $this->groupWithMember();
        $budget = $this->budget($group, ['total_amount' => 10000]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}", [
                'total_amount' => 15000,
            ])
            ->assertStatus(200);

        $this->assertEquals(15000.0, $response->json('data.total_amount'));
        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'budget.update',
            'entity_name' => 'budgets',
        ]);
    }

    public function test_delete_soft_deletes_and_marks_inactive()
    {
        [$user, $group] = $this->groupWithMember();
        $budget = $this->budget($group);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}")
            ->assertStatus(200);

        $this->assertEquals('inactive', $response->json('data.status'));
        $this->assertNotNull($response->json('data.deleted_at'));
        $this->assertDatabaseMissing('budgets', ['id' => $budget->id, 'deleted_at' => null]);
    }

    public function test_create_rejects_invalid_currency()
    {
        [$user, $group] = $this->groupWithMember();

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/budgets", [
                'year'         => 2026,
                'month'        => 9,
                'total_amount' => 5000,
                'currency'     => 'XYZ',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

}
