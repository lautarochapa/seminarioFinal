<?php

namespace App\Jobs;

use App\ImportedRecipeCandidate;
use App\Repositories\Scraping\ScrapingRepository;
use App\Scraping\Adapters\CookpadRecipeScraper;
use App\Services\Scraping\ScrapingCircuitBreaker;
use App\Services\Scraping\ScrapingExecutionGuard;
use App\Services\Scraping\UrlSecurityValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunRecipeScrapingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    private int $scrapingJobId;

    public function __construct(int $scrapingJobId)
    {
        $this->scrapingJobId = $scrapingJobId;
    }

    public function handle(
        ScrapingRepository $repo,
        CookpadRecipeScraper $scraper,
        ScrapingExecutionGuard $guard,
        ScrapingCircuitBreaker $circuitBreaker,
        UrlSecurityValidator $urlValidator
    ): void
    {
        $job = $repo->findJobOrFail($this->scrapingJobId);
        $lockAcquired = false;

        if (in_array($job->status, ['cancelled', 'cancel_requested'], true)) {
            $repo->updateJob($job, ['status' => 'cancelled', 'finished_at' => now()]);
            return;
        }

        if (!$guard->acquire($job->source->code)) {
            $delay = (int) config('scraping.default_retry_after_seconds', 60);
            $repo->addLog($job, 'warning', 'Ejecucion omitida: lock de fuente ocupado', [
                'source_code' => $job->source->code,
                'retry_after_seconds' => $delay,
                'final_reason' => 'source_lock_busy',
            ]);
            $this->release($delay);
            return;
        }

        $lockAcquired = true;

        try {
            if ($circuitBreaker->isOpen($job->source->code)) {
                $msg = 'Circuit breaker abierto para la fuente. Reintentar luego de ' . $circuitBreaker->secondsUntilClose($job->source->code) . ' segundos.';
                $repo->updateJob($job, ['status' => 'failed', 'finished_at' => now(), 'error_message' => $msg]);
                $repo->addLog($job, 'warning', $msg, [
                    'source_code' => $job->source->code,
                    'circuit_breaker_status' => 'open',
                    'final_reason' => 'circuit_breaker_open',
                ]);
                return;
            }

            $repo->updateJob($job, ['status' => 'running', 'started_at' => now()]);
            $repo->addLog($job, 'info', 'Iniciando scraping de recetas', ['source' => $job->source->code]);

            if (!$scraper->isAvailable()) {
                $msg = 'Scraper de recetas no disponible: ' . $job->source->code;
                $repo->createAlertIfNotDuplicate($job, 'source_unavailable', $msg, 'high');
                $repo->createErrorIfNotDuplicate($job, 'source_unavailable', $msg, null, []);
                $repo->updateJob($job, ['status' => 'failed', 'finished_at' => now(), 'error_message' => $msg]);
                return;
            }

            $result = $scraper->scrape($job->source, $job);

            $totalCreated = 0;
            $totalDuplicates = 0;

            foreach ($result->recipes as $dto) {
                if (empty($dto->title) || empty($dto->sourceUrl)) {
                    continue;
                }

                $sourceUrl = $urlValidator->normalizeHttpUrl($dto->sourceUrl, $job->source->base_url, true);

                $exists = ImportedRecipeCandidate::where('source_url', $sourceUrl)
                    ->whereIn('status', ['pending', 'parsed', 'approved'])
                    ->exists();

                if ($exists) {
                    $totalDuplicates++;
                    continue;
                }

                ImportedRecipeCandidate::create([
                    'source_url'           => $sourceUrl,
                    'source_site'          => $job->source->code,
                    'raw_title'            => $dto->title,
                    'raw_description'      => $dto->description,
                    'raw_ingredients_json' => !empty($dto->ingredients) ? $dto->ingredients : null,
                    'raw_steps_json'       => !empty($dto->steps) ? $dto->steps : null,
                    'raw_image_url'        => $dto->imageUrl,
                    'parsed_recipe_json'   => [
                        'name'              => $dto->title,
                        'description'       => $dto->description,
                        'servings'          => $dto->servings,
                        'prep_time_minutes' => $dto->prepMinutes,
                        'cook_time_minutes' => $dto->cookMinutes,
                        'external_id'       => $dto->externalId,
                        'scraping_job_id'   => $job->id,
                    ],
                    'status' => 'parsed',
                ]);

                $totalCreated++;
            }

            $metrics = $result->metrics;
            $metrics['candidates_created'] = $totalCreated;
            $metrics['items_skipped_duplicate'] = ($metrics['items_skipped_duplicate'] ?? 0) + $totalDuplicates;
            $metrics['final_reason'] = $result->finalReason;
            $finalStatus = $result->successful || $totalCreated > 0 ? 'completed' : 'failed';

            $repo->updateJob($job, [
                'status'        => $finalStatus,
                'finished_at'   => now(),
                'total_found'   => $result->totalFound,
                'total_created' => $totalCreated,
                'error_message' => $result->successful ? null : mb_substr((string) $result->errorMessage, 0, 500),
            ]);

            if ($finalStatus === 'failed') {
                $message = mb_substr((string) ($result->errorMessage ?: 'Scraping de recetas finalizado con error.'), 0, 500);
                $repo->createErrorIfNotDuplicate($job, $result->finalReason, $message, null, [
                    'source' => $job->source->code,
                    'metrics' => $metrics,
                ]);
                $repo->createAlertIfNotDuplicate($job, $result->finalReason, $message, 'high');
            }

            $repo->addLog($job, $finalStatus === 'completed' ? 'info' : 'warning', 'Scraping de recetas finalizado', [
                'found'   => $result->totalFound,
                'created' => $totalCreated,
                'metrics' => $metrics,
            ]);
        } catch (\Throwable $e) {
            $errorMsg = mb_substr($e->getMessage(), 0, 500);

            $repo->updateJob($job, [
                'status'        => 'failed',
                'finished_at'   => now(),
                'error_message' => $errorMsg,
            ]);

            $repo->addLog($job, 'error', 'Job fallido: ' . $errorMsg);
            $repo->createErrorIfNotDuplicate($job, 'recipe_scraping_failed', $errorMsg, null, [
                'exception' => get_class($e),
            ]);
            $repo->createAlertIfNotDuplicate($job, 'recipe_scraping_failed', $errorMsg, 'high');
        } finally {
            if ($lockAcquired) {
                $guard->release();
            }

            $freshJob = \App\ScrapingJob::find($this->scrapingJobId);
            if ($freshJob && $freshJob->status === 'running') {
                $repo->updateJob($freshJob, [
                    'status' => 'failed',
                    'finished_at' => now(),
                    'error_message' => 'El job finalizo sin estado terminal.',
                ]);
            }
        }
    }
}
