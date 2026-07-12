<?php

namespace App\Repositories\RecipeScraping;

use App\ScrapingJob;
use App\ScrapingSource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RecipeScrapingRepository
{
    const SOURCE_CODE = 'cookpad';
    const JOB_TYPE    = 'recipe_scraping';

    public function findOrCreateCookpadSource(): ScrapingSource
    {
        return ScrapingSource::firstOrCreate(
            ['code' => self::SOURCE_CODE],
            [
                'name'      => 'Cookpad Argentina',
                'type'      => 'web_scraper',
                'base_url'  => 'https://cookpad.com/ar',
                'is_active' => true,
                'status'    => 'active',
            ]
        );
    }

    public function findSource(): ?ScrapingSource
    {
        return ScrapingSource::where('code', self::SOURCE_CODE)->where('status', 'active')->first();
    }

    public function hasActiveJob(int $sourceId): bool
    {
        return ScrapingJob::where('source_id', $sourceId)
            ->whereIn('status', ['pending', 'running'])
            ->where('job_type', self::JOB_TYPE)
            ->exists();
    }

    public function createJob(array $data): ScrapingJob
    {
        return ScrapingJob::create($data);
    }

    public function paginateJobs(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = ScrapingJob::with('source')
            ->where('job_type', self::JOB_TYPE)
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        $page    = (int) ($filters['page'] ?? 1);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function findJobOrFail(int $id): ScrapingJob
    {
        return ScrapingJob::with('source')
            ->where('job_type', self::JOB_TYPE)
            ->findOrFail($id);
    }

    public function updateJob(ScrapingJob $job, array $data): void
    {
        $job->fill($data);
        $job->save();
    }
}
