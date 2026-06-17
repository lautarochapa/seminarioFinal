<?php

namespace App\Scraping\DTOs;

class ScrapedRecipeDTO
{
    public string  $sourceUrl       = '';
    public string  $title           = '';
    public ?string $description     = null;
    public ?int    $servings        = null;
    public ?int    $prepMinutes     = null;
    public ?int    $cookMinutes     = null;
    public array   $ingredients     = [];
    public array   $steps           = [];
    public ?string $imageUrl        = null;
    public ?string $externalId      = null;
}
