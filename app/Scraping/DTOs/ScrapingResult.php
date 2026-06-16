<?php

namespace App\Scraping\DTOs;

class ScrapingResult
{
    /** @var ScrapedProductDTO[] */
    public array $products = [];
    public int $totalFound = 0;
    public int $pagesScraped = 0;
    public bool $successful = false;
    public ?string $errorMessage = null;
}
