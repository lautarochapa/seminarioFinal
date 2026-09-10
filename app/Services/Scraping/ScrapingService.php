<?php

namespace App\Services\Scraping;

use App\Jobs\RunScrapingJob;
use App\Repositories\Scraping\ScrapingRepository;
use App\Exceptions\Ingredients\IngredientException;

class ScrapingService
{
    private $repo;

    public function __construct(ScrapingRepository $repo)
    {
        $this->repo = $repo;
    }

    public function listSources(array $filters)
    {
        return $this->repo->paginateSources($filters);
    }

    public function createSource(int $actorId, array $data): \App\ScrapingSource
    {
        if ($this->repo->existsSourceCode($data['code'])) {
            throw new IngredientException(
                'SCRAPING_SOURCE_DUPLICATE',
                'Ya existe una fuente con ese codigo.',
                409
            );
        }

        return $this->repo->createSource([
            'code'      => $data['code'],
            'name'      => $data['name'],
            'type'      => $data['type'] ?? 'web_scraper',
            'base_url'  => $data['base_url'],
            'city_id'   => $data['city_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'status'    => 'active',
        ]);
    }

    public function listJobs(array $filters)
    {
        return $this->repo->paginateJobs($filters);
    }

    public function showJob(int $id): \App\ScrapingJob
    {
        return $this->repo->findJobOrFail($id);
    }

    public function createJob(int $actorId, array $data): \App\ScrapingJob
    {
        $source = $this->repo->findSourceOrFail((int) $data['source_id']);

        if (!$source->is_active || $source->status !== 'active') {
            throw new IngredientException(
                'SCRAPING_SOURCE_INACTIVE',
                'La fuente de scraping no esta activa.',
                422
            );
        }

        if ($this->repo->hasActiveJobForSource($source->id)) {
            throw new IngredientException(
                'SCRAPING_JOB_ALREADY_RUNNING',
                'Ya existe un job activo para esta fuente.',
                409
            );
        }

        $params = array_filter([
            'supermarket_chain_id'  => isset($data['supermarket_chain_id'])
                ? (int) $data['supermarket_chain_id'] : null,
            'supermarket_branch_id' => isset($data['supermarket_branch_id'])
                ? (int) $data['supermarket_branch_id'] : null,
            'max_pages'             => isset($data['max_pages'])
                ? (int) $data['max_pages'] : 5,
            'max_products'          => isset($data['max_products'])
                ? (int) $data['max_products'] : null,
            'delay_ms'              => isset($data['delay_ms'])
                ? (int) $data['delay_ms'] : null,
            'dry_run'               => array_key_exists('dry_run', $data)
                ? (bool) $data['dry_run'] : null,
        ], function ($v) { return $v !== null; });

        $job = $this->repo->createJob([
            'source_id'       => $source->id,
            'job_type'        => 'product_prices',
            'requested_by'    => $actorId,
            'status'          => 'pending',
            'parameters_json' => $params,
        ]);

        RunScrapingJob::dispatch($job->id);

        return $job->fresh(['source']);
    }

    public function retryJob(int $actorId, int $jobId): \App\ScrapingJob
    {
        $job = $this->repo->findJobOrFail($jobId);

        if (!in_array($job->status, ['failed', 'cancelled'])) {
            throw new IngredientException(
                'SCRAPING_JOB_CANNOT_RETRY',
                'Solo se pueden reintentar jobs fallidos o cancelados.',
                409
            );
        }

        $newJob = $this->repo->createJob([
            'source_id'       => $job->source_id,
            'job_type'        => $job->job_type,
            'requested_by'    => $actorId,
            'status'          => 'pending',
            'parameters_json' => $job->parameters_json,
        ]);

        RunScrapingJob::dispatch($newJob->id);

        return $newJob->fresh(['source']);
    }

    public function cancelJob(int $actorId, int $jobId): \App\ScrapingJob
    {
        $job = $this->repo->findJobOrFail($jobId);

        if (!in_array($job->status, ['pending', 'running'])) {
            throw new IngredientException(
                'SCRAPING_JOB_CANNOT_CANCEL',
                'Solo se pueden cancelar jobs pendientes o en ejecucion.',
                409
            );
        }

        $newStatus = $job->status === 'pending' ? 'cancelled' : 'cancel_requested';
        $extra     = $newStatus === 'cancelled' ? ['finished_at' => now()] : [];

        return $this->repo->updateJob($job, array_merge(['status' => $newStatus], $extra));
    }

    public function jobLogs(int $jobId, array $filters)
    {
        $this->repo->findJobOrFail($jobId);
        return $this->repo->paginateLogs($jobId, $filters);
    }
}
