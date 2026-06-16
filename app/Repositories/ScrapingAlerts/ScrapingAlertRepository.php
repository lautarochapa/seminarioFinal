<?php

namespace App\Repositories\ScrapingAlerts;

use App\ScrapingAlert;
use App\ScrapingError;
use App\ScrapingJob;
use App\Exceptions\Ingredients\IngredientException;
use Illuminate\Support\Facades\DB;

class ScrapingAlertRepository
{
    public function paginate(array $filters)
    {
        $query = ScrapingAlert::with(['source', 'job', 'resolver']);
        $alertType = $filters['alert_type'] ?? ($filters['type'] ?? null);
        $sourceId = $filters['source_id'] ?? ($filters['source'] ?? null);
        $jobId = $filters['scraping_job_id'] ?? ($filters['job'] ?? null);
        $createdFrom = $filters['created_from'] ?? ($filters['date_from'] ?? null);
        $createdTo = $filters['created_to'] ?? ($filters['date_to'] ?? null);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (!empty($alertType)) {
            $query->where('alert_type', $alertType);
        }
        if (!empty($sourceId)) {
            $query->where('source_id', (int) $sourceId);
        }
        if (!empty($jobId)) {
            $query->where('scraping_job_id', (int) $jobId);
        }
        if (!empty($createdFrom)) {
            $query->where('created_at', '>=', $createdFrom);
        }
        if (!empty($createdTo)) {
            $query->where('created_at', '<=', $createdTo);
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function findOrFail(int $id): ScrapingAlert
    {
        $alert = ScrapingAlert::with(['source', 'job', 'resolver'])->find($id);
        if (!$alert) {
            throw new IngredientException('SCRAPING_ALERT_NOT_FOUND', 'Alerta no encontrada.', 404);
        }
        return $alert;
    }

    public function update(ScrapingAlert $alert, array $data): ScrapingAlert
    {
        $alert->fill($data);
        $alert->save();
        return $alert;
    }

    public function aggregateReport(array $filters): array
    {
        $alertQuery = ScrapingAlert::query();
        $errorQuery = ScrapingError::query();
        $sourceId = $filters['source_id'] ?? ($filters['source'] ?? null);
        $createdFrom = $filters['created_from'] ?? ($filters['date_from'] ?? null);
        $createdTo = $filters['created_to'] ?? ($filters['date_to'] ?? null);
        $alertType = $filters['alert_type'] ?? ($filters['type'] ?? null);

        if (!empty($sourceId)) {
            $alertQuery->where('source_id', (int) $sourceId);
            $errorQuery->where('source_id', (int) $sourceId);
        }
        if (!empty($createdFrom)) {
            $alertQuery->where('created_at', '>=', $createdFrom);
            $errorQuery->where('created_at', '>=', $createdFrom);
        }
        if (!empty($createdTo)) {
            $alertQuery->where('created_at', '<=', $createdTo);
            $errorQuery->where('created_at', '<=', $createdTo);
        }
        if (!empty($alertType)) {
            $alertQuery->where('alert_type', $alertType);
        }

        $totalAlerts = (clone $alertQuery)->count();

        $byStatus = (clone $alertQuery)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byType = (clone $alertQuery)
            ->selectRaw('alert_type as type, COUNT(*) as count')
            ->groupBy('alert_type')
            ->orderByRaw('COUNT(*) DESC')
            ->get()
            ->map(fn ($r) => ['type' => $r->type, 'count' => (int) $r->count])
            ->values()
            ->toArray();

        $bySeverity = (clone $alertQuery)
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->get()
            ->map(fn ($r) => ['severity' => $r->severity, 'count' => (int) $r->count])
            ->values()
            ->toArray();

        $bySource = (clone $alertQuery)
            ->selectRaw('scraping_alerts.source_id, scraping_sources.name as source_name, COUNT(*) as count')
            ->leftJoin('scraping_sources', 'scraping_sources.id', '=', 'scraping_alerts.source_id')
            ->groupBy('scraping_alerts.source_id', 'scraping_sources.name')
            ->get()
            ->map(fn ($r) => [
                'source_id'   => $r->source_id,
                'source_name' => $r->source_name,
                'count'       => (int) $r->count,
            ])
            ->values()
            ->toArray();

        $evolution = (clone $alertQuery)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'count' => (int) $r->count])
            ->values()
            ->toArray();

        $failedJobsQuery = ScrapingJob::where('status', 'failed');
        if (!empty($filters['source_id'])) {
            $failedJobsQuery->where('source_id', (int) $filters['source_id']);
        }
        if (!empty($filters['created_from'])) {
            $failedJobsQuery->where('created_at', '>=', $filters['created_from']);
        }
        if (!empty($filters['created_to'])) {
            $failedJobsQuery->where('created_at', '<=', $filters['created_to']);
        }

        return [
            'total_alerts'  => $totalAlerts,
            'by_status'     => $byStatus,
            'by_type'       => $byType,
            'by_severity'   => $bySeverity,
            'by_source'     => $bySource,
            'evolution'     => $evolution,
            'by_date'       => $evolution,
            'failed_jobs'   => $failedJobsQuery->count(),
        ];
    }
}
