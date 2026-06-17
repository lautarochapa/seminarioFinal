<?php

namespace App\Http\Resources\Api\V1\RecipeImportUrl;

use Illuminate\Http\Resources\Json\JsonResource;

class ImportedRecipeCandidateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'status'               => $this->status,
            'source_url'           => $this->source_url,
            'source_site'          => $this->source_site,
            'raw_title'            => $this->raw_title,
            'raw_description'      => $this->raw_description,
            'raw_image_url'        => $this->raw_image_url,
            'raw_ingredients_json' => $this->raw_ingredients_json,
            'raw_steps_json'       => $this->raw_steps_json,
            'parsed_recipe_json'   => $this->parsed_recipe_json,
            'created_at'           => $this->created_at,
        ];
    }
}
