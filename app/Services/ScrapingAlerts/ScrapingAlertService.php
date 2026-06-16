<?php

namespace App\Services\ScrapingAlerts;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\Repositories\ScrapingAlerts\ScrapingAlertRepository;
use App\ScrapingAlert;
use App\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ScrapingAlertService
{
    private $repo;

    public function __construct(ScrapingAlertRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(User $actor, array $filters)
    {
        $this->assertSuperAdmin($actor);

        return $this->repo->paginate($filters);
    }

    public function resolve(User $actor, $id, array $data, $ip, $userAgent)
    {
        $this->assertSuperAdmin($actor);
        $alert = $this->repo->findOrFail((int) $id);

        if ($alert->status !== 'open') {
            throw new IngredientException('ALERT_ALREADY_RESOLVED', 'La alerta ya fue resuelta.', 409);
        }

        return DB::transaction(function () use ($actor, $alert, $ip, $userAgent) {
            $old = $this->snapshot($alert);
            $updated = $this->repo->update($alert, [
                'status' => 'resolved',
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ]);
            $updated = $updated->fresh(['source', 'job', 'resolver']);
            $new = $this->snapshot($updated);

            $this->audit($actor->id, 'alert.resolved', $updated->id, $old, $new, $ip, $userAgent);

            return $updated;
        });
    }

    public function report(User $actor, array $filters)
    {
        $this->assertSuperAdmin($actor);

        return $this->repo->aggregateReport($filters);
    }

    private function assertSuperAdmin(User $actor)
    {
        if (! $actor->hasRole('super_admin')) {
            throw new AuthorizationException('Sin permiso para esta accion.');
        }
    }

    private function snapshot(ScrapingAlert $alert)
    {
        return [
            'scraping_job_id' => $alert->scraping_job_id,
            'source_id' => $alert->source_id,
            'alert_type' => $alert->alert_type,
            'message' => $alert->message,
            'severity' => $alert->severity,
            'status' => $alert->status,
            'resolved_by' => $alert->resolved_by,
            'resolved_at' => $alert->resolved_at ? (string) $alert->resolved_at : null,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => 'scraping_alerts',
            'entity_id' => (string) $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
