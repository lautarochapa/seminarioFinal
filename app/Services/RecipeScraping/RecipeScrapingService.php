<?php

namespace App\Services\RecipeScraping;

use App\AuditLog;
use App\Exceptions\RecipeImportUrl\RecipeImportUrlException;
use App\Jobs\RunRecipeScrapingJob;
use App\Repositories\RecipeScraping\RecipeScrapingRepository;
use App\ScrapingJob;
use App\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RecipeScrapingService
{
    const RETRYABLE_STATUSES = ['failed', 'cancelled'];

    private RecipeScrapingRepository $repo;

    public function __construct(RecipeScrapingRepository $repo)
    {
        $this->repo = $repo;
    }

    public function create(User $user, array $input, string $ip, string $userAgent): ScrapingJob
    {
        $this->assertPermission($user);

        $this->repo->reconcileStaleJobs();

        $source = $this->repo->findOrCreateCookpadSource();

        if ($this->repo->hasActiveJob($source->id)) {
            throw new \RuntimeException('RECIPE_SCRAPING_JOB_ALREADY_RUNNING:Ya existe un job activo para esta fuente.');
        }

        $job = $this->repo->createJob([
            'source_id'       => $source->id,
            'job_type'        => RecipeScrapingRepository::JOB_TYPE,
            'requested_by'    => $user->id,
            'status'          => 'pending',
            'parameters_json' => array_filter([
                'max_pages'   => min((int) ($input['max_pages'] ?? 1), 50),
                'max_recipes' => isset($input['max_recipes']) ? min((int) $input['max_recipes'], 200) : null,
                'delay_ms'    => isset($input['delay_ms']) ? (int) $input['delay_ms'] : null,
                'search_term' => isset($input['search_term']) && trim((string) $input['search_term']) !== ''
                    ? trim((string) $input['search_term'])
                    : null,
            ], function ($v) { return $v !== null; }),
        ]);

        RunRecipeScrapingJob::dispatch($job->id);

        AuditLog::create([
            'user_id'    => $user->id,
            'action'     => 'recipe_scraping_job_created',
            'entity_name'=> 'scraping_jobs',
            'entity_id'  => $job->id,
            'old_values' => null,
            'new_values' => ['source' => RecipeScrapingRepository::SOURCE_CODE, 'max_pages' => $input['max_pages'] ?? 1],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return $job->load('source');
    }

    public function list(User $user, array $filters): LengthAwarePaginator
    {
        $this->assertPermission($user);
        $this->repo->reconcileStaleJobs();
        return $this->repo->paginateJobs($filters);
    }

    public function show(User $user, int $id): ScrapingJob
    {
        $this->assertPermission($user);
        return $this->repo->findJobOrFail($id);
    }

    public function retry(User $user, int $id, string $ip, string $userAgent): ScrapingJob
    {
        $this->assertPermission($user);

        $job = $this->repo->findJobOrFail($id);

        if (!in_array($job->status, self::RETRYABLE_STATUSES, true)) {
            throw new \RuntimeException('RECIPE_SCRAPING_JOB_NOT_RETRYABLE:Solo se puede reintentar jobs fallidos o cancelados.');
        }

        $source = $this->repo->findOrCreateCookpadSource();

        $newJob = $this->repo->createJob([
            'source_id'       => $source->id,
            'job_type'        => RecipeScrapingRepository::JOB_TYPE,
            'requested_by'    => $user->id,
            'status'          => 'pending',
            'parameters_json' => $job->parameters_json ?? ['max_pages' => 1],
        ]);

        RunRecipeScrapingJob::dispatch($newJob->id);

        AuditLog::create([
            'user_id'    => $user->id,
            'action'     => 'recipe_scraping_job_retried',
            'entity_name'=> 'scraping_jobs',
            'entity_id'  => $newJob->id,
            'old_values' => ['original_job_id' => $id, 'status' => $job->status],
            'new_values' => ['status' => 'pending'],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return $newJob->load('source');
    }

    private function assertPermission(User $user): void
    {
        if (!$user->hasPermission('recipes.manage') && !$user->hasRole('super_admin') && !$user->hasRole('recipe_admin')) {
            throw RecipeImportUrlException::forbidden();
        }
    }
}
