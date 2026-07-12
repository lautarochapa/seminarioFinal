<?php

namespace App\Http\Resources\Api\V1\Recipes;

use Illuminate\Http\Resources\Json\JsonResource;

class RecipeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'description'       => $this->description,
            'source_type'       => $this->source_type,
            'difficulty'        => $this->difficulty,
            'servings'          => $this->servings,
            'prep_time_minutes' => $this->prep_time_minutes,
            'cook_time_minutes' => $this->cook_time_minutes,
            'is_official'       => (bool) $this->is_official,
            'is_verified'       => (bool) $this->is_verified,
            'status'            => $this->status,
            'category_id'       => $this->category_id,
            'category'          => $this->whenLoaded('category', function () {
                return $this->category ? [
                    'id'   => $this->category->id,
                    'name' => $this->category->name,
                ] : null;
            }),
            'owner'             => $this->whenLoaded('owner', function () {
                return $this->owner ? [
                    'id'   => $this->owner->id,
                    'name' => trim(($this->owner->name ?? '') . ' ' . ($this->owner->lastname ?? '')),
                ] : null;
            }),
            'tags'              => $this->whenLoaded('tags', function () {
                return $this->tags->map(function ($tag) {
                    return ['id' => $tag->id, 'code' => $tag->code, 'name' => $tag->name, 'type' => $tag->type];
                });
            }),
            'ingredients'       => $this->whenLoaded('ingredients', function () {
                return RecipeIngredientResource::collection($this->ingredients);
            }),
            'steps'             => $this->whenLoaded('steps', function () {
                return $this->steps->map(function ($step) {
                    return [
                        'step_number'       => $step->step_number,
                        'description'       => $step->description,
                        'estimated_minutes' => $step->estimated_minutes,
                    ];
                });
            }),
            'images'            => $this->whenLoaded('images', function () {
                return $this->images->map(function ($img) {
                    return ['image_url' => $img->image_url, 'is_primary' => $img->is_primary];
                });
            }),
            'sources'           => $this->whenLoaded('sources', function () {
                return $this->sources->map(function ($src) {
                    return [
                        'source_url'    => $src->source_url,
                        'source_site'   => $src->source_site,
                        'source_author' => $src->source_author,
                    ];
                });
            }),
            'tags_count'        => $this->when(isset($this->tags_count), $this->tags_count),
            'ingredients_count' => $this->when(isset($this->ingredients_count), $this->ingredients_count),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'deleted_at'        => $this->deleted_at,
        ];
    }
}
