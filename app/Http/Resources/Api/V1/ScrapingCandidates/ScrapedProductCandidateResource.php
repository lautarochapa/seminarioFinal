<?php

namespace App\Http\Resources\Api\V1\ScrapingCandidates;

use Illuminate\Http\Resources\Json\JsonResource;

class ScrapedProductCandidateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                      => $this->id,
            'scraping_job_id'         => $this->scraping_job_id,
            'source_id'               => $this->source_id,
            'raw_name'                => $this->raw_name,
            'raw_brand'               => $this->raw_brand,
            'raw_price'               => $this->raw_price,
            'raw_unit_price'          => $this->raw_unit_price,
            'raw_image_url'           => $this->raw_image_url,
            'raw_product_url'         => $this->raw_product_url,
            'external_product_id'     => $this->external_product_id,
            'ean'                     => $this->ean,
            'suggested_product_id'    => $this->suggested_product_id,
            'suggested_ingredient_id' => $this->suggested_ingredient_id,
            'match_confidence'        => $this->match_confidence,
            'review_status'           => $this->review_status,
            'reviewed_at'             => $this->reviewed_at,
            'source'                  => $this->whenLoaded('source', function () {
                return [
                    'id'   => $this->source->id,
                    'code' => $this->source->code,
                    'name' => $this->source->name,
                ];
            }),
            'suggested_product' => $this->whenLoaded('suggestedProduct', function () {
                if (!$this->suggestedProduct) {
                    return null;
                }
                return [
                    'id'     => $this->suggestedProduct->id,
                    'name'   => $this->suggestedProduct->name,
                    'status' => $this->suggestedProduct->status,
                ];
            }),
            'suggested_ingredient' => $this->whenLoaded('suggestedIngredient', function () {
                if (!$this->suggestedIngredient) {
                    return null;
                }
                return [
                    'id'   => $this->suggestedIngredient->id,
                    'name' => $this->suggestedIngredient->name,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // raw_payload_json excluido intencionalmente (puede contener datos sensibles)
        ];
    }
}
