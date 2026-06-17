<?php

namespace Tests\Feature\Api\V1\PersonalReports;

use App\BodyMeasurement;
use App\Objective;
use App\User;
use App\UserObjective;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalReportsTest extends TestCase
{
    use RefreshDatabase;

    private function objective(string $code = null): Objective
    {
        return Objective::create([
            'code'     => $code ?? 'OBJ_' . uniqid(),
            'name'     => 'Objetivo test',
            'category' => 'health',
            'status'   => 'active',
        ]);
    }

    private function measurement(User $user, array $overrides = []): BodyMeasurement
    {
        return BodyMeasurement::create(array_merge([
            'user_id'          => $user->id,
            'measurement_date' => now()->toDateString(),
        ], $overrides));
    }

    private function userObjective(User $user, Objective $obj, array $overrides = []): UserObjective
    {
        return UserObjective::create(array_merge([
            'user_id'      => $user->id,
            'objective_id' => $obj->id,
            'is_active'    => true,
        ], $overrides));
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/v1/users/me/reports/body-progress')->assertStatus(401);
        $this->getJson('/api/v1/users/me/reports/objectives-progress')->assertStatus(401);
    }

    public function test_body_progress_returns_empty_when_no_measurements()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/users/me/reports/body-progress')
            ->assertStatus(200);

        $this->assertEquals(0, $response->json('data.total_measurements'));
        $this->assertEmpty($response->json('data.series'));
        $this->assertNull($response->json('data.summary'));
        $this->assertArrayHasKey('trace_id', $response->json());
    }

    public function test_body_progress_returns_series_and_summary()
    {
        $user = factory(User::class)->create();
        $this->measurement($user, ['measurement_date' => '2026-01-01', 'weight_kg' => 80.0, 'waist_cm' => 90.0]);
        $this->measurement($user, ['measurement_date' => '2026-03-01', 'weight_kg' => 75.0, 'waist_cm' => 85.0]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/users/me/reports/body-progress')
            ->assertStatus(200);

        $this->assertEquals(2, $response->json('data.total_measurements'));
        $this->assertCount(2, $response->json('data.series'));
        $this->assertEquals(-5.0, $response->json('data.summary.weight_kg.variation'));
        $this->assertEquals(80.0, $response->json('data.summary.weight_kg.initial'));
        $this->assertEquals(75.0, $response->json('data.summary.weight_kg.current'));
    }

    public function test_body_progress_filters_by_date_range()
    {
        $user = factory(User::class)->create();
        $this->measurement($user, ['measurement_date' => '2025-12-01', 'weight_kg' => 90.0]);
        $this->measurement($user, ['measurement_date' => '2026-06-01', 'weight_kg' => 80.0]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/users/me/reports/body-progress?date_from=2026-01-01')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('data.total_measurements'));
        $this->assertEquals(80.0, $response->json('data.summary.weight_kg.current'));
    }

    public function test_body_progress_does_not_leak_other_user_data()
    {
        $user  = factory(User::class)->create();
        $other = factory(User::class)->create();
        $this->measurement($other, ['measurement_date' => '2026-06-01', 'weight_kg' => 99.0]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/users/me/reports/body-progress')
            ->assertStatus(200);

        $this->assertEquals(0, $response->json('data.total_measurements'));
    }

    public function test_objectives_progress_returns_empty_when_no_objectives()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/users/me/reports/objectives-progress')
            ->assertStatus(200);

        $this->assertEquals(0, $response->json('data.total'));
        $this->assertEmpty($response->json('data.objectives'));
    }

    public function test_objectives_progress_returns_list_with_objective_name()
    {
        $user = factory(User::class)->create();
        $obj  = $this->objective();
        $this->userObjective($user, $obj, ['target_value' => 70.0, 'target_unit' => 'kg']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/users/me/reports/objectives-progress')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('data.total'));
        $first = $response->json('data.objectives.0');
        $this->assertEquals('Objetivo test', $first['objective_name']);
        $this->assertEquals(70.0, $first['target_value']);
        $this->assertEquals('kg', $first['target_unit']);
        $this->assertArrayHasKey('current_value', $first);
        $this->assertArrayHasKey('progress_percent', $first);
    }

    public function test_objectives_progress_computes_progress_from_body_measurements()
    {
        $user = factory(User::class)->create();
        $obj  = $this->objective();
        $uo   = $this->userObjective($user, $obj, [
            'target_value' => 70.0,
            'target_unit'  => 'kg',
        ]);
        $this->measurement($user, [
            'measurement_date' => now()->toDateString(),
            'weight_kg'        => 75.0,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/users/me/reports/objectives-progress')
            ->assertStatus(200);

        $first = $response->json('data.objectives.0');
        $this->assertTrue($first['has_data']);
        $this->assertEquals(75.0, $first['current_value']);
    }

    public function test_objectives_progress_excludes_inactive_objectives()
    {
        $user = factory(User::class)->create();
        $obj  = $this->objective();
        $this->userObjective($user, $obj, ['is_active' => false]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/users/me/reports/objectives-progress')
            ->assertStatus(200);

        $this->assertEquals(0, $response->json('data.total'));
    }
}
