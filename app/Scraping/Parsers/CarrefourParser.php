<?php

namespace App\Scraping\Parsers;

use App\Scraping\DTOs\ScrapedProductDTO;

class CarrefourParser
{
    /**
     * Parsea la respuesta del API de Carrefour (compatible con Vtex y fixture simplificado).
     *
     * @param array $data
     * @return ScrapedProductDTO[]
     */
    public function parse(array $data): array
    {
        $products = [];

        foreach ($data as $item) {
            $name  = $item['productName'] ?? $item['name'] ?? '';
            $brand = $item['brand'] ?? null;

            if ($name === '') {
                continue;
            }

            $price     = null;
            $productId = $item['productId'] ?? $item['id'] ?? null;
            $itemId    = null;
            $imageUrl  = null;
            $link      = $item['link'] ?? $item['url'] ?? null;

            // Formato Vtex (items con sellers)
            if (!empty($item['items'][0])) {
                $vtexItem = $item['items'][0];
                $itemId   = $vtexItem['itemId'] ?? null;
                $imageUrl = $vtexItem['images'][0]['imageUrl'] ?? null;
                $price    = $vtexItem['sellers'][0]['commertialOffer']['Price'] ?? null;
            }

            // Formato simplificado (usado en fixtures de tests)
            if ($price === null && isset($item['price'])) {
                $price    = (float) $item['price'];
                $itemId   = $item['id'] ?? null;
                $imageUrl = $item['image_url'] ?? null;
            }

            if ($price === null || (float) $price <= 0) {
                continue;
            }

            $dto = new ScrapedProductDTO();
            $dto->rawName          = $name;
            $dto->rawBrand         = $brand;
            $dto->rawPrice         = (float) $price;
            $dto->currency         = 'ARS';
            $dto->rawProductUrl    = $link;
            $dto->externalProductId = (string) ($productId ?? '');
            $dto->externalSku      = (string) ($itemId ?? $productId ?? '');
            $dto->rawImageUrl      = $imageUrl;

            $products[] = $dto;
        }

        return $products;
    }
}
