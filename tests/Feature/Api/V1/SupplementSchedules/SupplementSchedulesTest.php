<?php

namespace Tests\Feature\Api\V1\SupplementSchedules;

use App\AuditLog;
use App\SupplementLog;
use App\SupplementSchedule;
use App\SupplementType;
use App\User;
use App\UserSupplement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplementSchedulesTest extends TestCase
{
    use RefreshDatabase;

    private function supplementType(): SupplementType
    {
        return SupplementType::create([
            'code'   => 'T_' . uniqid(),
            'name'   => 'Vitamina D',
            'status' => 'active',
        ]);
    }

    private function supplement(User $user, array $overrides = []): UserSupplement
    {
        $type = $this->supplementType();
        return UserSupplement::create(array_merge([
            'user_id'            => $user->id,
            'supplement_type_id' => $type->id,
            'frequency'          => 'daily',
            'status'             => 'active',
        ], $overrides));
    }

    private function schedule(UserSupplement $supplement, string $time = '08:00'): SupplementSchedule
    {
        return SupplementSchedule::create([
            'user_supplement_id' => $supplement->id,
            'time_of_day'        => $time,
            'reminder_enabled'   => false,
            'status'             => 'active',
        ]);
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/v1/users/me/supplements/1/schedule')->assertStatus(401);
        $this->postJson('/api/v1/users/me/supplements/1/log')->assertStatus(401);
    }

    public function test_access_to_another_users_supplement_returns_404()
    {
        $userA = factory(User::class)->create();
        $userB = factory(User::class)->create();
        $s     = $this->supplement($userB);

        $this->actingAs($userA)
            ->getJson("/api/v1/users/me/supplements/{$s->id}/schedule")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'USER_SUPPLEMENT_NOT_FOUND');

        $this->actingAs($userA)
            ->postJson("/api/v1/users/me/supplements/{$s->id}/log", [])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'USER_SUPPLEMENT_NOT_FOUND');
    }

    public function test_list_returns_active_schedules_for_own_supplement()
    {
        $user = factory(User::class)->create();
        $s    = $this->supplement($user);
        $this->schedule($s, '08:00');
        $this->schedule($s, '20:00');

        $response = $this->actingAs($user)
            ->getJson("/api/v1/users/me/supplements/{$s->id}/schedule")
            ->assertStatus(200);

        $this->assertCount(2, $response->json('data'));
        $this->assertArrayHasKey('trace_id', $response->json());
    }

    public function test_create_schedule_stores_and_audits()
    {
        $user = factory(User::class)->create();
        $s    = $this->supplement($user);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/users/me/supplements/{$s->id}/schedule", [
                'time_of_day'      => '07:30',
                'days_of_week'     => ['monday', 'wednesday', 'friday'],
                'reminder_enabled' => true,
            ])
            ->assertStatus(201);

        $this->assertEquals('07:30', $response->json('data.time_of_day'));
        $this->assertDatabaseHas('supplement_schedules', ['user_supplement_id' => $s->id, 'time_of_day' => '07:30', 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supplement_schedule.create']);
    }

    public function test_create_schedule_rejects_duplicate_time_and_days()
    {
        $user = factory(User::class)->create();
        $s    = $this->supplement($user);
        $this->schedule($s, '08:00');

        $this->actingAs($user)
            ->postJson("/api/v1/users/me/supplements/{$s->id}/schedule", [
                'time_of_day' => '08:00',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SUPPLEMENT_SCHEDULE_DUPLICATE');
    }

    public function test_update_schedule_applies_partial_changes()
    {
        $user = factory(User::class)->create();
        $s    = $this->supplement($user);
        $sch  = $this->schedule($s, '08:00');

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/users/me/supplements/{$s->id}/schedule/{$sch->id}", [
                'time_of_day'      => '09:00',
                'reminder_enabled' => true,
            ])
            ->assertStatus(200);

        $this->assertStringStartsWith('09:00', $response->json('data.time_of_day'));
        $this->assertTrue($response->json('data.reminder_enabled'));
    }

    public function test_delete_schedule_sets_status_inactive()
    {
        $user = factory(User::class)->create();
        $s    = $this->supplement($user);
        $sch  = $this->schedule($s, '08:00');

        $this->actingAs($user)
            ->deleteJson("/api/v1/users/me/supplements/{$s->id}/schedule/{$sch->id}")
            ->assertStatus(204);

        $this->assertDatabaseHas('supplement_schedules', ['id' => $sch->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supplement_schedule.delete']);
    }

    public function test_log_records_consumption_with_server_timestamp_and_audits()
    {
        $user = factory(User::class)->create();
        $s    = $this->supplement($user);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/users/me/supplements/{$s->id}/log", [
                'notes' => 'con el desayuno',
            ])
            ->assertStatus(201);

        $this->assertNotNull($response->json('data.taken_at'));
        $this->assertEquals($s->id, $response->json('data.user_supplement_id'));
        $this->assertDatabaseHas('supplement_logs', ['user_supplement_id' => $s->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supplement_log.create']);
    }

    public function test_log_rejects_duplicate_taken_at_for_same_supplement()
    {
        $user    = factory(User::class)->create();
        $s       = $this->supplement($user);
        $takenAt = '2026-06-17 08:00:00';

        SupplementLog::create([
            'user_supplement_id' => $s->id,
            'user_id'            => $user->id,
            'taken_at'           => $takenAt,
            'created_at'         => now(),
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/users/me/supplements/{$s->id}/log", [
                'taken_at' => $takenAt,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SUPPLEMENT_LOG_DUPLICATE');
    }
}
