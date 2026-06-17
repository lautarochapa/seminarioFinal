<?php

namespace App\Jobs;

use App\ImportedRecipeCandidate;
use App\Repositories\Scraping\ScrapingRepository;
use App\Scraping\Adapters\CookpadRecipeScraper;
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

    public function handle(ScrapingRepository $repo, CookpadRecipeScraper $scraper): void
    {
        $job = $repo->findJobOrFail($this->scrapingJobId);

        if (in_array($job->status, ['cancelled', 'cancel_requested'], true)) {
            $repo->updateJob($job, ['status' => 'cancelled', 'finished_at' => now()]);
            return;
        }

        $repo->updateJob($job, ['status' => 'running', 'started_at' => now()]);
        $repo->addLog($job, 'info', 'Iniciando scraping de recetas', ['source' => $job->source->code]);

        try {
            if (!$scraper->isAvailable()) {
                $msg = 'Scraper de recetas no disponible: ' . $job->source->code;
                $repo->createAlertIfNotDuplicate($job, 'source_unavailable', $msg, 'high');
                $repo->createErrorIfNotDuplicate($job, 'source_unavailable', $msg, null, []);
                $repo->updateJob($job, ['status' => 'failed', 'finished_at' => now(), 'error_message' => $msg]);
                return;
            }

            $result = $scraper->scrape($job->source, $job);

            $totalCreated = 0;

            foreach ($result->recipes as $dto) {
                if (empty($dto->title) || empty($dto->sourceUrl)) {
                    continue;
                }

                $exists = ImportedRecipeCandidate::where('source_url', $dto->sourceUrl)
                    ->whereIn('status', ['pending', 'parsed', 'approved'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                ImportedRecipeCandidate::create([
                    'source_url'           => $dto->sourceUrl,
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

            $repo->updateJob($job, [
                'status'        => 'completed',
                'finished_at'   => now(),
                'total_found'   => $result->totalFound,
                'total_created' => $totalCreated,
            ]);

            $repo->addLog($job, 'info', 'Scraping de recetas completado', [
                'found'   => $result->totalFound,
                'created' => $totalCreated,
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
        }
    }
}
