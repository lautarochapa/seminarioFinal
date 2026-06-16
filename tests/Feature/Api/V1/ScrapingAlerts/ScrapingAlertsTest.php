<?php

namespace Tests\Feature\Api\V1\ScrapingAlerts;

use App\AuditLog;
use App\Role;
use App\ScrapingAlert;
use App\ScrapingError;
use App\ScrapingJob;
use App\ScrapingSource;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScrapingAlertsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = factory(User::class)->create();
        $role = Role::where('code', 'super_admin')->first();
        DB::table('user_roles')->insert([
            'user_id'    => $user->id,
            'role_id'    => $role->id,
            'created_at' => now(),
        ]);
        return $user;
    }

    private function regularUser(): User
    {
        return factory(User::class)->create();
    }

    private function source(): ScrapingSource
    {
        return ScrapingSource::create([
            'code'      => 'src_' . Str::random(6),
            'name'      => 'Fuente Test',
            'type'      => 'web_scraper',
            'base_url'  => 'https://example.com',
            'is_active' => true,
            'status'    => 'active',
        ]);
    }

    private function job(ScrapingSource $source, string $status = 'completed'): ScrapingJob
    {
        return ScrapingJob::create([
            'source_id' => $source->id,
            'job_type'  => 'product_prices',
            'status'    => $status,
        ]);
    }

    private function alert(ScrapingSource $source, ScrapingJob $job, array $data = []): ScrapingAlert
    {
        return ScrapingAlert::create(array_merge([
            'scraping_job_id' => $job->id,
            'source_id'       => $source->id,
            'alert_type'      => 'scraping_failed',
            'message'         => 'Error de prueba',
            'severity'        => 'high',
            'status'          => 'open',
        ], $data));
    }

    private function scrapingError(ScrapingSource $source, ScrapingJob $job, array $data = []): ScrapingError
    {
        return ScrapingError::create(array_merge([
            'scraping_job_id' => $job->id,
            'source_id'       => $source->id,
            'error_type'      => 'parser_error',
            'message'         => 'Fallo el parser',
            'stack_trace'     => 'Exception in file.php:99\nTrace line 1\nTrace line 2',
            'context_json'    => ['page' => 1],
        ], $data));
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $this->getJson('/api/v1/admin/scraping/alerts')->assertStatus(401);
        $this->patchJson('/api/v1/admin/scraping/alerts/1/resolve')->assertStatus(401);
        $this->getJson('/api/v1/admin/reports/scraping-errors')->assertStatus(401);
    }

    public function test_sin_permiso_retorna_403()
    {
        $user = $this->regularUser();

        $this->actingAs($user)->getJson('/api/v1/admin/scraping/alerts')->assertStatus(403);
        $this->actingAs($user)->patchJson('/api/v1/admin/scraping/alerts/1/resolve')->assertStatus(403);
        $this->actingAs($user)->getJson('/api/v1/admin/reports/scraping-errors')->assertStatus(403);
    }

    public function test_listado_alertas_paginado_con_source_y_job()
    {
        $admin  = $this->admin();
        $source = $this->source();
        $job    = $this->job($source);
        $this->alert($source, $job);
        $this->alert($source, $job, ['severity' => 'medium', 'alert_type' => 'source_unavailable']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/scraping/alerts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [[
                    'id', 'alert_type', 'message', 'severity', 'status',
                    'source', 'created_at',
                ]],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'trace_id',
            ]);

        $this->assertGreaterThanOrEqual(2, $response->json('meta.total'));
    }

    public function test_filtros_por_estado_severidad_y_tipo()
    {
        $admin  = $this->admin();
        $source = $this->source();
        $job    = $this->job($source);
        $this->alert($source, $job, ['status' => 'open', 'severity' => 'high', 'alert_type' => 'scraping_failed']);
        $this->alert($source, $job, ['status' => 'resolved', 'severity' => 'medium', 'alert_type' => 'parser_error']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/scraping/alerts?status=resolved&severity=medium');

        $response->assertStatus(200);
        foreach ($response->json('data') as $item) {
            $this->assertEquals('resolved', $item['status']);
        }

        $byType = $this->actingAs($admin)->getJson('/api/v1/admin/scraping/alerts?alert_type=parser_error');
        $byType->assertStatus(200);
        foreach ($byType->json('data') as $item) {
            $this->assertEquals('parser_error', $item['alert_type']);
        }
    }

    public function test_reporte_no_expone_stack_trace_individual()
    {
        $admin  = $this->admin();
        $source = $this->source();
        $job    = $this->job($source, 'failed');
        $this->scrapingError($source, $job);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/reports/scraping-errors');

        $response->assertStatus(200);
        $body = json_encode($response->json());
        $this->assertStringNotContainsString('stack_trace', $body);
        $this->assertStringNotContainsString('Exception in file.php', $body);
    }

    public function test_resolucion_exitosa_con_auditoria()
    {
        $admin  = $this->admin();
        $source = $this->source();
        $job    = $this->job($source);
        $alert  = $this->alert($source, $job);

        $response = $this->actingAs($admin)->patchJson(
            "/api/v1/admin/scraping/alerts/{$alert->id}/resolve",
            ['resolution_notes' => 'Solucionado manualmente']
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'resolved');

        $this->assertDatabaseHas('scraping_alerts', [
            'id'     => $alert->id,
            'status' => 'resolved',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => 'alert.resolved',
            'entity_name' => 'scraping_alerts',
            'entity_id'   => (string) $alert->id,
            'user_id'     => $admin->id,
        ]);
    }

    public function test_resolucion_registra_actor_y_fecha_del_servidor()
    {
        $admin  = $this->admin();
        $source = $this->source();
        $job    = $this->job($source);
        $alert  = $this->alert($source, $job);

        $this->actingAs($admin)->patchJson(
            "/api/v1/admin/scraping/alerts/{$alert->id}/resolve",
            ['resolution_notes' => 'Validado']
        )->assertStatus(200);

        $updated = ScrapingAlert::find($alert->id);
        $this->assertEquals($admin->id, $updated->resolved_by);
        $this->assertNotNull($updated->resolved_at);
    }

    public function test_doble_resolucion_retorna_409()
    {
        $admin  = $this->admin();
        $source = $this->source();
        $job    = $this->job($source);
        $alert  = $this->alert($source, $job, ['status' => 'resolved', 'resolved_by' => $admin->id]);

        $this->actingAs($admin)->patchJson(
            "/api/v1/admin/scraping/alerts/{$alert->id}/resolve",
            ['resolution_notes' => 'Intento repetido']
        )->assertStatus(409)
         ->assertJsonPath('error.code', 'ALERT_ALREADY_RESOLVED');
    }

    public function test_reporte_errores_devuelve_resumen_agregado()
    {
        $admin  = $this->admin();
        $source = $this->source();
        $job    = $this->job($source, 'failed');
        $this->alert($source, $job, ['alert_type' => 'scraping_failed', 'severity' => 'high']);
        $this->alert($source, $job, ['alert_type' => 'parser_error', 'severity' => 'medium', 'status' => 'resolved']);
        $this->scrapingError($source, $job);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/reports/scraping-errors');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_alerts',
                    'by_status',
                    'by_type',
                    'by_severity',
                    'by_source',
                    'evolution',
                    'failed_jobs',
                ],
                'trace_id',
            ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, $data['total_alerts']);
        $this->assertGreaterThanOrEqual(1, $data['failed_jobs']);
        $this->assertIsArray($data['by_type']);
        $this->assertIsArray($data['by_severity']);
    }

    public function test_no_duplica_alerta_para_mismo_job_y_tipo()
    {
        $admin  = $this->admin();
        $source = $this->source();
        $job    = $this->job($source);

        $repo = app(\App\Repositories\Scraping\ScrapingRepository::class);
        $repo->createAlertIfNotDuplicate($job, 'scraping_failed', 'Error A');
        $repo->createAlertIfNotDuplicate($job, 'scraping_failed', 'Error B');

        $count = ScrapingAlert::where('scraping_job_id', $job->id)
            ->where('alert_type', 'scraping_failed')
            ->count();

        $this->assertEquals(1, $count);
    }
}
