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
            'mapping_summary'      => $this->mappingSummary(),
            'reviewed_by'          => $this->reviewed_by,
            'reviewed_at'          => $this->reviewed_at,
            'created_recipe_id'    => $this->created_recipe_id,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }

    /**
     * Calculo trivial (sin queries) duplicado a proposito para que el
     * listado (item 10: badges "Lista para aprobar"/"Requiere revision") lo
     * tenga disponible sin N+1, ya que raw_ingredients_json/parsed_recipe_json
     * ya vienen cargados con cada fila del paginador. La misma regla de
     * opcionalidad (required_unmapped_count excluye is_optional=true) se usa
     * en RecipeImportCandidatesRepository::requiredUnmappedIngredientIndices(),
     * que es la que efectivamente bloquea approve().
     */
    private function mappingSummary(): array
    {
        $rawIngredients = $this->raw_ingredients_json ?? [];
        $total = count($rawIngredients);

        $parsed   = $this->parsed_recipe_json ?? [];
        $mappings = $parsed['ingredient_mappings'] ?? [];
        $mappedIndices = array_map(function ($m) {
            return (int) ($m['ingredient_index'] ?? -1);
        }, $mappings);

        $suggestions = $parsed['ingredient_suggestions'] ?? [];
        $optionalByIndex = [];
        if (is_array($suggestions) && count($suggestions) === $total) {
            foreach ($suggestions as $s) {
                $optionalByIndex[(int) ($s['index'] ?? -1)] = (bool) ($s['is_optional'] ?? false);
            }
        }

        $unmappedCount = 0;
        $requiredUnmappedCount = 0;
        $optionalUnmappedCount = 0;
        foreach (array_keys($rawIngredients) as $idx) {
            $idx = (int) $idx;
            if (in_array($idx, $mappedIndices, true)) {
                continue;
            }
            $unmappedCount++;
            if (!empty($optionalByIndex[$idx])) {
                $optionalUnmappedCount++;
            } else {
                $requiredUnmappedCount++;
            }
        }

        return [
            'total_count'             => $total,
            'mapped_count'            => $total - $unmappedCount,
            'unmapped_count'          => $unmappedCount,
            'required_unmapped_count' => $requiredUnmappedCount,
            'optional_unmapped_count' => $optionalUnmappedCount,
            'mapping_ready'           => $requiredUnmappedCount === 0,
        ];
    }
}
