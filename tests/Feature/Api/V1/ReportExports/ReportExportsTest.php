<?php

namespace Tests\Feature\Api\V1\ReportExports;

use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Jobs\GenerateReportExportJob;
use App\ReportExport;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportExportsTest extends TestCase
{
    use RefreshDatabase;

    private function groupWithMember(): array
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

    private function exportUrl(FamilyGroup $group): string
    {
        return "/api/v1/family-groups/{$group->id}/reports/export";
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $this->postJson('/api/v1/family-groups/1/reports/export', [])
            ->assertStatus(401);

        $this->getJson('/api/v1/report-exports/1')
            ->assertStatus(401);
    }

    public function test_non_member_cannot_request_export()
    {
        [, $group] = $this->groupWithMember();
        $outsider  = factory(User::class)->create();

        $this->actingAs($outsider)
            ->postJson($this->exportUrl($group), ['report_type' => 'stock', 'format' => 'json'])
            ->assertStatus(403);
    }

    public function test_invalid_format_is_rejected()
    {
        [$user, $group] = $this->groupWithMember();
        Bus::fake();

        $this->actingAs($user)
            ->postJson($this->exportUrl($group), ['report_type' => 'stock', 'format' => 'pdf'])
            ->assertStatus(422);
    }

    public function test_invalid_report_type_is_rejected()
    {
        [$user, $group] = $this->groupWithMember();
        Bus::fake();

        $this->actingAs($user)
            ->postJson($this->exportUrl($group), ['report_type' => 'unknown', 'format' => 'json'])
            ->assertStatus(422);
    }

    public function test_valid_export_request_creates_record_and_dispatches_job()
    {
        [$user, $group] = $this->groupWithMember();
        Bus::fake();

        $response = $this->actingAs($user)
            ->postJson($this->exportUrl($group), ['report_type' => 'stock', 'format' => 'json'])
            ->assertStatus(201);

        $this->assertEquals('pending', $response->json('data.status'));
        $this->assertEquals('stock',   $response->json('data.report_type'));
        $this->assertEquals('json',    $response->json('data.format'));
        $this->assertNotNull($response->json('data.id'));
        $this->assertArrayHasKey('trace_id', $response->json());

        Bus::assertDispatched(GenerateReportExportJob::class);
    }

    public function test_duplicate_pending_export_is_rejected()
    {
        [$user, $group] = $this->groupWithMember();
        Bus::fake();

        ReportExport::create([
            'user_id'         => $user->id,
            'family_group_id' => $group->id,
            'report_type'     => 'stock',
            'format'          => 'json',
            'status'          => 'pending',
            'created_at'      => now(),
        ]);

        $this->actingAs($user)
            ->postJson($this->exportUrl($group), ['report_type' => 'stock', 'format' => 'json'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'REPORT_EXPORT_DUPLICATE');
    }

    public function test_get_export_status_returns_state_for_owner()
    {
        [$user, $group] = $this->groupWithMember();

        $export = ReportExport::create([
            'user_id'         => $user->id,
            'family_group_id' => $group->id,
            'report_type'     => 'purchases',
            'format'          => 'csv',
            'status'          => 'processing',
            'created_at'      => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/report-exports/{$export->id}")
            ->assertStatus(200);

        $this->assertEquals('processing', $response->json('data.status'));
        $this->assertEquals('purchases',  $response->json('data.report_type'));
        $this->assertEquals('csv',        $response->json('data.format'));
        $this->assertNull($response->json('data.download_url'));
    }

    public function test_get_export_denies_access_to_non_owner_non_member()
    {
        [$user, $group] = $this->groupWithMember();
        $outsider = factory(User::class)->create();

        $export = ReportExport::create([
            'user_id'         => $user->id,
            'family_group_id' => $group->id,
            'report_type'     => 'stock',
            'format'          => 'json',
            'status'          => 'pending',
            'created_at'      => now(),
        ]);

        $this->actingAs($outsider)
            ->getJson("/api/v1/report-exports/{$export->id}")
            ->assertStatus(404);
    }

    public function test_completed_export_exposes_download_url_and_expired_export_does_not()
    {
        [$user, $group] = $this->groupWithMember();
        Storage::fake('local');

        $completedExport = ReportExport::create([
            'user_id'         => $user->id,
            'family_group_id' => $group->id,
            'report_type'     => 'stock',
            'format'          => 'json',
            'status'          => 'completed',
            'file_url'        => 'exports/1_stock.json',
            'created_at'      => now()->subHours(2),
            'finished_at'     => now()->subHour(),
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/report-exports/{$completedExport->id}")
            ->assertStatus(200);

        $this->assertEquals('completed', $response->json('data.status'));
        $this->assertNotNull($response->json('data.download_url'));

        $expiredExport = ReportExport::create([
            'user_id'         => $user->id,
            'family_group_id' => $group->id,
            'report_type'     => 'stock',
            'format'          => 'json',
            'status'          => 'completed',
            'file_url'        => 'exports/2_stock.json',
            'created_at'      => now()->subDays(2),
            'finished_at'     => now()->subDays(2),
        ]);

        $expiredResponse = $this->actingAs($user)
            ->getJson("/api/v1/report-exports/{$expiredExport->id}")
            ->assertStatus(200);

        $this->assertEquals('expired', $expiredResponse->json('data.status'));
        $this->assertNull($expiredResponse->json('data.download_url'));
    }
}
