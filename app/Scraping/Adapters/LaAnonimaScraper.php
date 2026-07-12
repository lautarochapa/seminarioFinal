<?php

namespace App\Scraping\Adapters;

use App\ScrapingJob;
use App\ScrapingSource;
use App\Scraping\Contracts\SupermarketScraperInterface;
use App\Scraping\DTOs\ScrapingResult;
use App\Repositories\Scraping\ScrapingRepository;

/**
 * Scraper para La Anonima.
 * Requiere validacion contra el sitio en vivo: laanonima.com.ar usa renderizado
 * JavaScript. Si la API publica no responde con JSON estatico, devuelve resultado vacio.
 */
class LaAnonimaScraper implements SupermarketScraperInterface
{
    private $repo;

    public function __construct(ScrapingRepository $repo)
    {
        $this->repo = $repo;
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function scrape(ScrapingSource $source, ScrapingJob $job): ScrapingResult
    {
        $result               = new ScrapingResult();
        $result->successful   = false;
        $result->errorMessage = 'La Anonima requiere navegador con JavaScript. Fuente no disponible con la infraestructura actual.';

        $this->repo->addLog($job, 'warning', $result->errorMessage);

        return $result;
    }
}
