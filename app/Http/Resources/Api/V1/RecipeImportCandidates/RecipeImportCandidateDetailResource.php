<?php

namespace App\Http\Resources\Api\V1\RecipeImportCandidates;

use Illuminate\Http\Resources\Json\JsonResource;

class RecipeImportCandidateDetailResource extends JsonResource
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
            'reviewed_by'          => $this->reviewed_by,
            'reviewed_at'          => $this->reviewed_at,
            'created_recipe_id'    => $this->created_recipe_id,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
