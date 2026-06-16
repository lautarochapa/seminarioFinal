<?php

namespace App\Jobs;

use App\Repositories\Scraping\ScrapingRepository;
use App\Scraping\ScraperResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunScrapingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    private $scrapingJobId;

    public function __construct(int $scrapingJobId)
    {
        $this->scrapingJobId = $scrapingJobId;
    }

    public function handle(ScrapingRepository $repo, ScraperResolver $resolver): void
    {
        $job = $repo->findJobOrFail($this->scrapingJobId);

        if (in_array($job->status, ['cancelled', 'cancel_requested'])) {
            $repo->updateJob($job, ['status' => 'cancelled', 'finished_at' => now()]);
            return;
        }

        $repo->updateJob($job, ['status' => 'running', 'started_at' => now()]);
        $repo->addLog($job, 'info', 'Iniciando scraping', ['source' => $job->source->code]);

        try {
            $scraper = $resolver->resolve($job->source->code);

            if (!$scraper->isAvailable()) {
                $unavailableMsg = 'Fuente no disponible con la infraestructura actual: ' . $job->source->code;
                $repo->createAlertIfNotDuplicate($job, 'source_unavailable', $unavailableMsg, 'high');
                $repo->createErrorIfNotDuplicate($job, 'source_unavailable', $unavailableMsg, null, ['source' => $job->source->code]);
                $repo->updateJob($job, [
                    'status'        => 'failed',
                    'finished_at'   => now(),
                    'error_message' => $unavailableMsg,
                ]);
                $repo->addLog($job, 'warning', $unavailableMsg);
                return;
            }

            $result = $scraper->scrape($job->source, $job);

            $params    = $job->parameters_json ?? [];
            $chainId   = isset($params['supermarket_chain_id']) ? (int) $params['supermarket_chain_id'] : 0;
            $branchId  = isset($params['supermarket_branch_id']) ? (int) $params['supermarket_branch_id'] : null;

            $totalUpdated       = 0;
            $totalPendingReview = 0;

            foreach ($result->products as $dto) {
                $repo->createCandidate([
                    'scraping_job_id'     => $job->id,
                    'source_id'           => $job->source_id,
                    'raw_name'            => $dto->rawName,
                    'raw_brand'           => $dto->rawBrand,
                    'raw_price'           => $dto->rawPrice,
                    'raw_unit_price'      => $dto->rawUnitPrice ?: 0,
                    'raw_image_url'       => $dto->rawImageUrl,
                    'raw_product_url'     => $dto->rawProductUrl,
                    'external_product_id' => $dto->externalProductId,
                    'raw_payload_json'    => null,
                    'review_status'       => 'pending',
                ]);

                $matched = false;
                if ($chainId && ($dto->externalSku || $dto->externalProductId)) {
                    $sp = $repo->findSupermarketProduct(
                        $chainId,
                        $branchId,
                        $dto->externalProductId,
                        $dto->externalSku
                    );

                    if ($sp) {
                        $sp->last_scraped_at = now();
                        $sp->last_seen_at    = now();
                        $sp->save();

                        $currentPrice = $repo->currentActivePrice($sp->id);
                        if (!$currentPrice || abs((float) $currentPrice->price - $dto->rawPrice) > 0.001) {
                            if ($currentPrice) {
                                $currentPrice->valid_to = now();
                                $currentPrice->save();
                            }
                            $repo->createPrice($sp->id, [
                                'price'      => $dto->rawPrice,
                                'currency'   => $dto->currency,
                                'source'     => 'scraper',
                                'scraped_at' => now(),
                                'valid_from' => now(),
                                'status'     => 'active',
                            ]);
                        }

                        $totalUpdated++;
                        $matched = true;
                    }
                }

                if (!$matched) {
                    $totalPendingReview++;
                }
            }

            $repo->updateJob($job, [
                'status'               => 'completed',
                'finished_at'          => now(),
                'total_found'          => $result->totalFound,
                'total_created'        => 0,
                'total_updated'        => $totalUpdated,
                'total_pending_review' => $totalPendingReview,
            ]);

            $repo->addLog($job, 'info', 'Scraping completado', [
                'found'   => $result->totalFound,
                'updated' => $totalUpdated,
                'review'  => $totalPendingReview,
            ]);
        } catch (\Throwable $e) {
            $errorMsg = mb_substr($e->getMessage(), 0, 500);

            $repo->updateJob($job, [
                'status'        => 'failed',
                'finished_at'   => now(),
                'error_message' => $errorMsg,
            ]);

            $repo->addLog($job, 'error', 'Job fallido: ' . $errorMsg);
            $repo->createErrorIfNotDuplicate($job, 'scraping_failed', $errorMsg, $e->getTraceAsString(), [
                'exception' => get_class($e),
            ]);
            $repo->createAlertIfNotDuplicate($job, 'scraping_failed', $errorMsg, 'high');
        }
    }
}
