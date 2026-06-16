<?php

namespace App\Scraping\Adapters;

use App\ScrapingJob;
use App\ScrapingSource;
use App\Scraping\Contracts\SupermarketScraperInterface;
use App\Scraping\DTOs\ScrapingResult;
use App\Scraping\Parsers\CarrefourParser;
use App\Repositories\Scraping\ScrapingRepository;
use Illuminate\Support\Facades\Http;

class CarrefourScraper implements SupermarketScraperInterface
{
    private $parser;
    private $repo;

    public function __construct(CarrefourParser $parser, ScrapingRepository $repo)
    {
        $this->parser = $parser;
        $this->repo   = $repo;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function scrape(ScrapingSource $source, ScrapingJob $job): ScrapingResult
    {
        $result   = new ScrapingResult();
        $params   = $job->parameters_json ?? [];
        $maxPages = (int) ($params['max_pages'] ?? 5);
        $perPage  = 50;
        $page     = 0;

        while ($page < $maxPages) {
            $job->refresh();
            if (in_array($job->status, ['cancel_requested', 'cancelled'])) {
                break;
            }

            $from = $page * $perPage;
            $to   = $from + $perPage - 1;

            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'PrecioJusto/1.0 (educational; contact: admin@example.com)',
                    'Accept'     => 'application/json',
                ])->timeout(30)->get("{$source->base_url}/api/catalog_system/pub/products/search", [
                    '_from' => $from,
                    '_to'   => $to,
                ]);

                if (!$response->successful()) {
                    $this->repo->addLog($job, 'warning', "Pagina {$page}: HTTP {$response->status()}");
                    break;
                }

                $data     = $response->json();
                $products = $this->parser->parse(is_array($data) ? $data : []);
                $result->products = array_merge($result->products, $products);
                $result->pagesScraped++;

                $this->repo->addLog($job, 'info', "Pagina {$page}: " . count($products) . " productos");

                if (count($data) < $perPage) {
                    break;
                }
            } catch (\Throwable $e) {
                $this->repo->addLog($job, 'error', "Error en pagina {$page}: " . $e->getMessage());
                break;
            }

            $page++;
        }

        $result->totalFound = count($result->products);
        $result->successful = true;

        return $result;
    }
}
