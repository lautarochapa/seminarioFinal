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
            $eanCandidates = [];
            $link      = $item['link'] ?? $item['url'] ?? null;

            // Formato Vtex (items con sellers)
            if (!empty($item['items'][0])) {
                $vtexItem = $item['items'][0];
                $itemId   = $vtexItem['itemId'] ?? null;
                $imageUrl = $vtexItem['images'][0]['imageUrl'] ?? null;
                $price    = $vtexItem['sellers'][0]['commertialOffer']['Price'] ?? null;

                if (isset($vtexItem['ean'])) {
                    $eanCandidates[] = $vtexItem['ean'];
                }

                // Vtex a veces expone el EAN dentro de referenceId
                if (!empty($vtexItem['referenceId'])) {
                    foreach ($vtexItem['referenceId'] as $ref) {
                        $key = strtolower((string) ($ref['Key'] ?? ''));
                        if (in_array($key, ['ean', 'ean13', 'gtin', 'codigodebarras'], true) && isset($ref['Value'])) {
                            $eanCandidates[] = $ref['Value'];
                        }
                    }
                }
            }

            // Formato simplificado (usado en fixtures de tests)
            if ($price === null && isset($item['price'])) {
                $price    = (float) $item['price'];
                $itemId   = $item['id'] ?? null;
                $imageUrl = $item['image_url'] ?? null;
            }

            if (isset($item['ean'])) {
                $eanCandidates[] = $item['ean'];
            }
            if (isset($item['barcode'])) {
                $eanCandidates[] = $item['barcode'];
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
            $dto->rawEan           = $this->firstValidEan($eanCandidates);

            $products[] = $dto;
        }

        return $products;
    }

    /**
     * Devuelve el primer EAN valido de una lista de candidatos.
     *
     * @param array $candidates
     */
    private function firstValidEan(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeEan($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * Normaliza un EAN/GTIN: solo digitos, longitud EAN-8..GTIN-14, descarta placeholders.
     *
     * @param mixed $value
     */
    private function normalizeEan($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === null || $digits === '' || strlen($digits) < 8 || strlen($digits) > 14) {
            return null;
        }

        if (preg_match('/^0+$/', $digits)) {
            return null;
        }

        return $digits;
    }
}
