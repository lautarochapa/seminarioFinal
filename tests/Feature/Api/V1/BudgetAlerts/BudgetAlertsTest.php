<?php

namespace Tests\Feature\Api\V1\BudgetAlerts;

use App\AuditLog;
use App\Budget;
use App\BudgetAlert;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetAlertsTest extends TestCase
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

    private function alert(Budget $budget, string $type = 'near_limit', string $severity = 'warning', string $status = 'unread')
    {
        return BudgetAlert::create([
            'budget_id'  => $budget->id,
            'alert_type' => $type,
            'message'    => 'Presupuesto cerca del limite',
            'severity'   => $severity,
            'status'     => $status,
            'created_at' => now(),
        ]);
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/v1/family-groups/1/budgets/1/alerts')->assertStatus(401);
        $this->patchJson('/api/v1/family-groups/1/budgets/1/alerts/1/read')->assertStatus(401);
    }

    public function test_access_to_foreign_group_is_denied()
    {
        [$user]         = $this->groupWithMember();
        [, $otherGroup] = $this->groupWithMember();
        $budget         = $this->budget($otherGroup);
        $a              = $this->alert($budget);

        $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$otherGroup->id}/budgets/{$budget->id}/alerts")
            ->assertStatus(403);

        $this->actingAs($user)
            ->patchJson("/api/v1/family-groups/{$otherGroup->id}/budgets/{$budget->id}/alerts/{$a->id}/read")
            ->assertStatus(403);
    }

    public function test_list_returns_paginated_alerts_ordered_by_date()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group);
        $this->alert($budget, 'near_limit',      'warning');
        $this->alert($budget, 'limit_exceeded',  'critical');

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/alerts")
            ->assertStatus(200);

        $this->assertCount(2, $response->json('data'));
        $this->assertArrayHasKey('meta', $response->json());
        $this->assertArrayHasKey('trace_id', $response->json());
    }

    public function test_list_filters_by_alert_type_and_status()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group);
        $this->alert($budget, 'near_limit',     'warning', 'unread');
        $this->alert($budget, 'limit_exceeded', 'critical', 'read');

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/alerts?alert_type=near_limit&status=unread")
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('near_limit', $response->json('data.0.alert_type'));
        $this->assertEquals('unread',     $response->json('data.0.status'));
    }

    public function test_list_filters_by_severity()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group);
        $this->alert($budget, 'near_limit',     'warning');
        $this->alert($budget, 'limit_exceeded', 'critical');

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/alerts?severity=critical")
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('critical', $response->json('data.0.severity'));
    }

    public function test_mark_as_read_updates_status_and_audits()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group);
        $a              = $this->alert($budget, 'limit_exceeded', 'critical', 'unread');

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/alerts/{$a->id}/read")
            ->assertStatus(200);

        $this->assertEquals('read', $response->json('data.status'));
        $this->assertNotNull($response->json('data.read_at'));
        $this->assertDatabaseHas('budget_alerts', ['id' => $a->id, 'status' => 'read']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'budget_alert.read']);
    }

    public function test_mark_as_read_again_returns_conflict()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group);
        $a              = $this->alert($budget, 'near_limit', 'warning', 'read');
        $a->update(['read_at' => now()]);

        $this->actingAs($user)
            ->patchJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/alerts/{$a->id}/read")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'BUDGET_ALERT_ALREADY_READ');
    }

    public function test_alert_not_found_returns_404()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group);

        $this->actingAs($user)
            ->patchJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/alerts/9999/read")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'BUDGET_ALERT_NOT_FOUND');
    }

    public function test_list_rejects_invalid_filter_values()
    {
        [$user, $group] = $this->groupWithMember();
        $budget         = $this->budget($group);

        $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/alerts?alert_type=fake")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BUDGET_ALERT_TYPE_INVALID');

        $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/budgets/{$budget->id}/alerts?severity=extreme")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BUDGET_ALERT_SEVERITY_INVALID');
    }
}
