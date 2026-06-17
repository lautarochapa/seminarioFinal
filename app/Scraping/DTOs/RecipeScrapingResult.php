<?php

namespace App\Scraping\DTOs;

class RecipeScrapingResult
{
    /** @var ScrapedRecipeDTO[] */
    public array   $recipes       = [];
    public int     $totalFound    = 0;
    public bool    $successful    = false;
    public ?string $errorMessage  = null;
}
