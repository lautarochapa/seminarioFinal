<?php

namespace App\Repositories\Scraping;

use App\ScrapingJob;
use App\ScrapingJobLog;
use App\ScrapingSource;
use App\ScrapedProductCandidate;
use App\SupermarketProduct;
use App\SupermarketProductPrice;
use App\Exceptions\Ingredients\IngredientException;
use App\ScrapingError;

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

    /**
     * $jobType acota el listado a un job_type especifico (ej. 'product_prices').
     * La tabla scraping_jobs es compartida con recipe_scraping; sin este
     * filtro, el listado de un modulo mostraria tambien jobs del otro.
     */
    public function paginateJobs(array $filters, ?string $jobType = null)
    {
        $query = ScrapingJob::with('source');

        if ($jobType !== null) {
            $query->where('job_type', $jobType);
        }
        if (!empty($filters['source_id'])) {
            $query->where('source_id', (int) $filters['source_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * $expectedJobType acota la busqueda a un job_type especifico. La tabla
     * scraping_jobs es compartida entre product scraping (product_prices) y
     * recipe scraping (recipe_scraping): sin este filtro, una accion de un
     * modulo podria encontrar y modificar/exponer un job del otro modulo con
     * el mismo id (ver bug de cancelacion cruzada detectado en job #15). Los
     * llamadores internos que ya conocen el id de su propio job (los Jobs de
     * cola) siguen usando la forma sin filtro.
     */
    public function findJobOrFail(int $id, ?string $expectedJobType = null): ScrapingJob
    {
        $query = ScrapingJob::with('source')->where('id', $id);
        if ($expectedJobType !== null) {
            $query->where('job_type', $expectedJobType);
        }

        $job = $query->first();
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

    public function createAlertIfNotDuplicate(
        \App\ScrapingJob $job,
        string $alertType,
        string $message,
        string $severity = 'high'
    ): void {
        $exists = \App\ScrapingAlert::where('scraping_job_id', $job->id)
            ->where('alert_type', $alertType)
            ->exists();

        if (!$exists) {
            \App\ScrapingAlert::create([
                'scraping_job_id' => $job->id,
                'source_id'       => $job->source_id,
                'alert_type'      => $alertType,
                'message'         => mb_substr($message, 0, 500),
                'severity'        => $severity,
                'status'          => 'open',
            ]);
        }
    }

    public function createErrorIfNotDuplicate(\App\ScrapingJob $job, string $errorType, string $message, ?string $stackTrace = null, array $context = []): void
    {
        $message = mb_substr($message, 0, 500);
        $exists = ScrapingError::where('scraping_job_id', $job->id)
            ->where('error_type', $errorType)
            ->where('message', $message)
            ->exists();

        if (!$exists) {
            ScrapingError::create([
                'scraping_job_id' => $job->id,
                'source_id' => $job->source_id,
                'error_type' => $errorType,
                'message' => $message,
                'stack_trace' => $stackTrace,
                'context_json' => empty($context) ? null : $context,
            ]);
        }
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
        $query = ScrapedProductCandidate::where('scraping_job_id', $data['scraping_job_id'])
            ->where('source_id', $data['source_id']);

        if (!empty($data['external_product_id'])) {
            $existing = (clone $query)->where('external_product_id', $data['external_product_id'])->first();
            if ($existing) {
                return $existing;
            }
        }

        if (!empty($data['raw_product_url'])) {
            $existing = (clone $query)->where('raw_product_url', $data['raw_product_url'])->first();
            if ($existing) {
                return $existing;
            }
        }

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
