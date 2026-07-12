<?php

namespace App\Scraping;

use App\Scraping\Contracts\SupermarketScraperInterface;
use App\Scraping\Adapters\CarrefourScraper;
use App\Scraping\Adapters\ChangomasScraper;
use App\Scraping\Adapters\LaAnonimaScraper;
use App\Exceptions\Ingredients\IngredientException;

class ScraperResolver
{
    private array $scrapers;

    public function __construct(
        CarrefourScraper $carrefour,
        ChangomasScraper $changomas,
        LaAnonimaScraper $laAnonima
    ) {
        $this->scrapers = [
            'carrefour'  => $carrefour,
            'changomas'  => $changomas,
            'la_anonima' => $laAnonima,
        ];
    }

    public function resolve(string $code): SupermarketScraperInterface
    {
        if (!isset($this->scrapers[$code])) {
            throw new IngredientException(
                'SCRAPING_SOURCE_UNAVAILABLE',
                "No existe adaptador para la fuente: {$code}",
                422
            );
        }

        return $this->scrapers[$code];
    }

    public function supports(string $code): bool
    {
        return isset($this->scrapers[$code]);
    }
}
