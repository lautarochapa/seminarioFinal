<?php

namespace App\Scraping\Adapters;

use App\ScrapingJob;
use App\ScrapingSource;
use App\Scraping\Contracts\SupermarketScraperInterface;
use App\Scraping\DTOs\ScrapingResult;
use App\Scraping\Parsers\CarrefourParser;
use App\Repositories\Scraping\ScrapingRepository;
use App\Services\Scraping\ScrapingHttpClient;
use App\Services\Scraping\ScrapingHttpException;
use App\Services\Scraping\ScrapingMetrics;
use App\Services\Scraping\ScrapingRateLimiter;

class CarrefourScraper implements SupermarketScraperInterface
{
    private $parser;
    private $repo;
    private $http;
    private $rateLimiter;

    public function __construct(CarrefourParser $parser, ScrapingRepository $repo, ScrapingHttpClient $http, ScrapingRateLimiter $rateLimiter)
    {
        $this->parser = $parser;
        $this->repo   = $repo;
        $this->http = $http;
        $this->rateLimiter = $rateLimiter;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function scrape(ScrapingSource $source, ScrapingJob $job): ScrapingResult
    {
        $result   = new ScrapingResult();
        $metrics  = new ScrapingMetrics();
        $params   = $job->parameters_json ?? [];
        $requestedMaxPages = (int) ($params['max_pages'] ?? 5);
        $maxPages = min($requestedMaxPages, (int) config('scraping.limits.supermarket_max_pages', 30));
        $maxItems = (int) config('scraping.limits.supermarket_max_items', 3000);
        if (isset($params['max_products']) && (int) $params['max_products'] > 0) {
            $maxItems = min($maxItems, (int) $params['max_products']);
        }
        $delayOverrideMs = isset($params['delay_ms']) && (int) $params['delay_ms'] >= 0
            ? (int) $params['delay_ms']
            : null;
        $perPage  = 50;
        $page     = 0;
        $seenKeys = [];
        $emptyPages = 0;
        $previousFrom = -1;

        while ($page < $maxPages) {
            $job->refresh();
            if (in_array($job->status, ['cancel_requested', 'cancelled'])) {
                $result->finalReason = 'cancelled';
                break;
            }

            if (!$job->source->is_active || $job->source->status !== 'active') {
                $result->finalReason = 'source_inactive';
                break;
            }

            $from = $page * $perPage;
            $to   = $from + $perPage - 1;

            if ($from <= $previousFrom) {
                $result->finalReason = 'offset_not_advancing';
                $this->repo->addLog($job, 'warning', 'Scraping detenido: offset sin avance', [
                    'page' => $page,
                    'offset' => $from,
                ]);
                break;
            }
            $previousFrom = $from;

            try {
                $response = $this->http->getJson($source->code, "{$source->base_url}/api/catalog_system/pub/products/search", [
                    '_from' => $from,
                    '_to'   => $to,
                ], $metrics, ['page' => $page, 'offset' => $from]);

                $data     = $response->json();
                $products = $this->parser->parse(is_array($data) ? $data : []);
                $newProducts = [];
                foreach ($products as $product) {
                    $key = $product->externalProductId ?: ($product->externalSku ?: md5($product->rawName . '|' . $product->rawProductUrl));
                    if (isset($seenKeys[$key])) {
                        $metrics->increment('items_skipped_duplicate');
                        continue;
                    }
                    $seenKeys[$key] = true;
                    $newProducts[] = $product;
                }

                $result->products = array_merge($result->products, $newProducts);
                $result->pagesScraped++;
                $metrics->increment('pages_processed');
                $metrics->increment('items_found', count($newProducts));

                $this->repo->addLog($job, 'info', "Pagina {$page}: " . count($newProducts) . " productos", [
                    'page' => $page,
                    'offset' => $from,
                    'request_number' => $metrics->all()['requests_total'],
                    'status_code' => $response->status(),
                    'items_found' => count($newProducts),
                ]);

                if (count($result->products) >= $maxItems) {
                    $result->products = array_slice($result->products, 0, $maxItems);
                    $result->finalReason = 'max_items_reached';
                    $this->repo->addLog($job, 'warning', 'Scraping detenido por limite maximo de items', [
                        'max_items' => $maxItems,
                    ]);
                    break;
                }

                if (count($data) < $perPage) {
                    break;
                }
                if (count($newProducts) === 0) {
                    $emptyPages++;
                    if ($emptyPages >= 2) {
                        $result->finalReason = 'repeated_empty_pages';
                        break;
                    }
                } else {
                    $emptyPages = 0;
                }
            } catch (ScrapingHttpException $e) {
                $metrics->increment('errors');
                $result->successful = false;
                $result->errorMessage = $e->getMessage();
                $result->finalReason = $e->statusCode() === 429 ? 'rate_limited' : 'http_error';
                $this->repo->addLog($job, 'warning', "Pagina {$page}: " . $e->getMessage(), [
                    'page' => $page,
                    'offset' => $from,
                    'status_code' => $e->statusCode(),
                    'retry_after_seconds' => $e->retryAfterSeconds(),
                    'request_number' => $metrics->all()['requests_total'],
                ]);
                break;
            } catch (\Throwable $e) {
                $metrics->increment('errors');
                $metrics->increment('parse_errors');
                $result->successful = false;
                $result->errorMessage = mb_substr($e->getMessage(), 0, 500);
                $result->finalReason = 'unexpected_error';
                $this->repo->addLog($job, 'error', "Error en pagina {$page}: " . $e->getMessage());
                break;
            }

            $page++;
            if ($page < $maxPages) {
                $this->rateLimiter->pauseForSupermarket($delayOverrideMs);
            }
        }

        $result->totalFound = count($result->products);
        $result->successful = $result->errorMessage === null;
        if ($page >= $maxPages && $result->finalReason === 'completed') {
            $result->finalReason = 'max_pages_reached';
        }
        $result->metrics = $metrics->all();

        return $result;
    }
}
