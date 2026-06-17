<?php

namespace Tests\Feature\Api\V1\BudgetMovements;

use App\AuditLog;
use App\Budget;
use App\BudgetMovement;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetMovementsTest extends TestCase
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

    private function movement(Budget $budget, string $type, float $amount, string $desc = 'test')
    {
        return BudgetMovement::create([
            'budget_id'     => $budget->id,
            'movement_type' => $type,
            'amount'        => $amount,
            'description'   => $desc,
            'created_at'    => now(),
        ]);
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/v1/family-groups/1/budgets/1/movements')->assertStatus(401);
        $this->postJson('/api/v1/family-groups/1/budgets/1/adjustments')->assertStatus(401);
    }

    public function test_access_to_foreign_group_is_denied()
    {
        [$user]         = $this->groupWithMember();
        [, $otherGroup] = $this->groupWithMember();
        $budget         = $this->budget($otherGroup);

        $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$otherGroup->id}/budgets/{$budget->id}/movements")
            ->assertStatus(403);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$otherGroup->id}/budgets/{$budget->id}/adjustments", [
                'amount'      => 100,
                'description' => 'ajuste',
            ])
            ->assertStatus(403);
    }

    public function test_list_returns_paginated_movements_ordered_by_date()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group);
        $this->movement($budget, 'purchase',    1000, 'compra');
        $this->movement($budget, 'adjustment',  -200, 'ajuste');

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/movements")
            ->assertStatus(200);

        $this->assertCount(2, $response->json('data'));
        $this->assertArrayHasKey('meta', $response->json());
        $this->assertArrayHasKey('trace_id', $response->json());
    }

    public function test_list_filters_by_movement_type()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group);
        $this->movement($budget, 'purchase',   1000, 'compra');
        $this->movement($budget, 'adjustment', -200, 'ajuste');

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/movements?movement_type=adjustment")
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('adjustment', $response->json('data.0.movement_type'));
    }

    public function test_adjustment_positive_creates_movement_and_audits()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/adjustments", [
                'amount'      => 500,
                'description' => 'Ingreso extra',
            ])
            ->assertStatus(201);

        $this->assertEquals(500.0,        $response->json('data.amount'));
        $this->assertEquals('adjustment', $response->json('data.movement_type'));
        $this->assertDatabaseHas('budget_movements', ['budget_id' => $budget->id, 'movement_type' => 'adjustment', 'amount' => 500]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'budget_movement.adjustment']);
        $this->assertArrayHasKey('trace_id', $response->json());
    }

    public function test_adjustment_negative_creates_movement()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/adjustments", [
                'amount'      => -300,
                'description' => 'Descuento por devolucion',
            ])
            ->assertStatus(201);

        $this->assertEquals(-300.0, $response->json('data.amount'));
    }

    public function test_adjustment_rejects_zero_amount()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/adjustments", [
                'amount'      => 0,
                'description' => 'nulo',
            ])
            ->assertStatus(422);
    }

    public function test_adjustment_requires_description()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/adjustments", [
                'amount' => 100,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_list_rejects_invalid_movement_type_filter()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group);

        $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/movements?movement_type=fake_type")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BUDGET_MOVEMENT_TYPE_INVALID');
    }
}
