<?php

namespace App\Scraping\DTOs;

class ScrapedProductDTO
{
    public string $rawName = '';
    public ?string $rawBrand = null;
    public float $rawPrice = 0.0;
    public float $rawUnitPrice = 0.0;
    public string $currency = 'ARS';
    public ?string $rawProductUrl = null;
    public ?string $externalProductId = null;
    public ?string $externalSku = null;
    public ?string $rawImageUrl = null;
}
