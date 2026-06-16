<?php

namespace App\Repositories\Scraping;

use App\ScrapingJob;
use App\ScrapingJobLog;
use App\ScrapingSource;
use App\ScrapedProductCandidate;
use App\SupermarketProduct;
use App\SupermarketProductPrice;
use App\Exceptions\Ingredients\IngredientException;

class ScrapingRepository
{
    public function paginateSources(array $filters)
    {
        $query = ScrapingSource::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('code', 'like', '%' . $filters['search'] . '%');
            });
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        return $query->orderBy('name')->paginate($perPage);
    }

    public function findSourceOrFail(int $id): ScrapingSource
    {
        $source = ScrapingSource::find($id);
        if (!$source) {
            throw new IngredientException('SCRAPING_SOURCE_NOT_FOUND', 'Fuente de scraping no encontrada.', 404);
        }
        return $source;
    }

    public function existsSourceCode(string $code, ?int $exceptId = null): bool
    {
        $query = ScrapingSource::where('code', $code);
        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }
        return $query->exists();
    }

    public function createSource(array $data): ScrapingSource
    {
        return ScrapingSource::create($data);
    }

    public function paginateJobs(array $filters)
    {
        $query = ScrapingJob::with('source');

        if (!empty($filters['source_id'])) {
            $query->where('source_id', (int) $filters['source_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function findJobOrFail(int $id): ScrapingJob
    {
        $job = ScrapingJob::with('source')->find($id);
        if (!$job) {
            throw new IngredientException('SCRAPING_JOB_NOT_FOUND', 'Job de scraping no encontrado.', 404);
        }
        return $job;
    }

    public function hasActiveJobForSource(int $sourceId): bool
    {
        return ScrapingJob::where('source_id', $sourceId)
            ->whereIn('status', ['pending', 'running'])
            ->exists();
    }

    public function createJob(array $data): ScrapingJob
    {
        return ScrapingJob::create($data);
    }

    public function updateJob(ScrapingJob $job, array $data): ScrapingJob
    {
        $job->fill($data);
        $job->save();
        return $job;
    }

    public function addLog(ScrapingJob $job, string $level, string $message, array $context = []): ScrapingJobLog
    {
        return ScrapingJobLog::create([
            'scraping_job_id' => $job->id,
            'level'           => $level,
            'message'         => $message,
            'context_json'    => empty($context) ? null : $context,
        ]);
    }

    public function paginateLogs(int $jobId, array $filters)
    {
        $query = ScrapingJobLog::where('scraping_job_id', $jobId);

        if (!empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 50), 200);
        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function createCandidate(array $data): ScrapedProductCandidate
    {
        return ScrapedProductCandidate::create($data);
    }

    public function findSupermarketProduct(int $chainId, ?int $branchId, ?string $extProductId, ?string $extSku): ?SupermarketProduct
    {
        $base = SupermarketProduct::where('supermarket_chain_id', $chainId);

        if ($branchId) {
            $base->where('supermarket_branch_id', $branchId);
        }

        if ($extSku && $extSku !== '') {
            $found = (clone $base)->where('external_sku', $extSku)->first();
            if ($found) {
                return $found;
            }
        }

        if ($extProductId && $extProductId !== '') {
            return (clone $base)->where('external_product_id', $extProductId)->first();
        }

        return null;
    }

    public function currentActivePrice(int $supermarketProductId): ?SupermarketProductPrice
    {
        return SupermarketProductPrice::where('supermarket_product_id', $supermarketProductId)
            ->whereNull('valid_to')
            ->where('status', 'active')
            ->latest('created_at')
            ->first();
    }

    public function createPrice(int $supermarketProductId, array $data): SupermarketProductPrice
    {
        return SupermarketProductPrice::create(array_merge(
            ['supermarket_product_id' => $supermarketProductId],
            $data
        ));
    }
}
