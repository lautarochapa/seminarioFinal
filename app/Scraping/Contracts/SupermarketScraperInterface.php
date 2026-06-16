<?php

namespace App\Scraping\Contracts;

use App\ScrapingJob;
use App\ScrapingSource;
use App\Scraping\DTOs\ScrapingResult;

interface SupermarketScraperInterface
{
    public function scrape(ScrapingSource $source, ScrapingJob $job): ScrapingResult;
    public function isAvailable(): bool;
}
