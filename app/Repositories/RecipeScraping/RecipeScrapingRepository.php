<?php

namespace App\Repositories\RecipeScraping;

use App\Exceptions\Ingredients\IngredientException;
use App\ScrapingJob;
use App\ScrapingJobLog;
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

    /**
     * Bajo QUEUE_CONNECTION=sync no existe un worker de colas que supervise
     * el timeout del job: si el proceso PHP que lo ejecutaba murio antes de
     * llegar a su propio bloque finally (kill externo del servidor web/
     * php-fpm, caida del proceso, OOM no capturable, o una cancelacion
     * solicitada -cancel_requested- cuando el proceso ya no estaba activo
     * para atenderla), el ScrapingJob queda en un estado no terminal para
     * siempre. "pending"/"running" bloquean ademas hasActiveJob() de por
     * vida. Se reconcilia aca, reutilizando el mismo repositorio/flujo que
     * ya usa el job (updateJob + ScrapingJobLog), en vez de un mecanismo
     * aparte.
     *
     * @return int cantidad de jobs reconciliados
     */
    public function reconcileStaleJobs(): int
    {
        $staleSeconds = max(60, (int) config('scraping.recipe_stale_job_seconds', 600));
        $staleBefore  = now()->subSeconds($staleSeconds);

        $staleJobs = ScrapingJob::where('job_type', self::JOB_TYPE)
            ->whereIn('status', ['pending', 'running', 'cancel_requested'])
            ->where(function ($query) use ($staleBefore) {
                $query->where('started_at', '<', $staleBefore)
                    ->orWhere(function ($q) use ($staleBefore) {
                        $q->whereNull('started_at')->where('created_at', '<', $staleBefore);
                    });
            })
            ->get();

        foreach ($staleJobs as $job) {
            $wasCancelling = $job->status === 'cancel_requested';

            $message = $wasCancelling
                ? 'Se solicito cancelar el job pero el proceso ya no estaba activo para completarla. Cerrado como cancelado automaticamente.'
                : 'Job interrumpido: el proceso finalizo sin cerrar el job (timeout del proceso, worker caido u OOM). Reconciliado automaticamente.';

            $this->updateJob($job, [
                'status'        => $wasCancelling ? 'cancelled' : 'failed',
                'finished_at'   => now(),
                'error_message' => $wasCancelling ? null : $message,
            ]);

            ScrapingJobLog::create([
                'scraping_job_id' => $job->id,
                'level'           => 'warning',
                'message'         => $message,
                'context_json'    => ['final_reason' => $wasCancelling ? 'stale_cancel_requested_reconciled' : 'stale_running_reconciled'],
            ]);
        }

        return $staleJobs->count();
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

    /**
     * Usa una excepcion de dominio (no Eloquent::findOrFail) a proposito:
     * ModelNotFoundException extiende RuntimeException, y los controllers de
     * este modulo hacen catch(\RuntimeException) para mapear errores propios
     * (ej. RECIPE_SCRAPING_JOB_NOT_RETRYABLE) a 409/422. Si esta busqueda
     * lanzara ModelNotFoundException, ese catch la interceptaria y la
     * devolveria como 409 con el mensaje crudo de Eloquent en vez de 404.
     */
    public function findJobOrFail(int $id): ScrapingJob
    {
        $job = ScrapingJob::with('source')
            ->where('id', $id)
            ->where('job_type', self::JOB_TYPE)
            ->first();

        if (!$job) {
            throw new IngredientException(
                'RECIPE_SCRAPING_JOB_NOT_FOUND',
                'Job de scraping de recetas no encontrado.',
                404
            );
        }

        return $job;
    }

    public function updateJob(ScrapingJob $job, array $data): void
    {
        $job->fill($data);
        $job->save();
    }
}
