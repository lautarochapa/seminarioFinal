<?php

namespace App\Repositories\RecipeImportCandidates;

use App\ImportedRecipeCandidate;
use App\Ingredient;
use App\Recipe;
use App\RecipeIngredient;
use App\RecipeReviewLog;
use App\RecipeStep;
use App\UnitMeasure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RecipeImportCandidatesRepository
{
    const TERMINAL_STATUSES = ['rejected', 'recipe_created'];
    const EDITABLE_STATUSES = ['pending', 'parsed', 'approved'];

    public function findOrFail(int $id): ImportedRecipeCandidate
    {
        $candidate = ImportedRecipeCandidate::find($id);
        if (!$candidate) {
            throw new \RuntimeException('IMPORT_CANDIDATE_NOT_FOUND');
        }
        return $candidate;
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = ImportedRecipeCandidate::query()->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['source_site'])) {
            $query->where('source_site', $filters['source_site']);
        }
        if (!empty($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }
        if (!empty($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        $page    = max((int) ($filters['page'] ?? 1), 1);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function updateFields(ImportedRecipeCandidate $candidate, array $fields): void
    {
        $candidate->fill($fields);
        $candidate->save();
    }

    public function setMapping(ImportedRecipeCandidate $candidate, int $ingredientIndex, array $mapping): void
    {
        $parsed   = $candidate->parsed_recipe_json ?? [];
        $mappings = $parsed['ingredient_mappings'] ?? [];

        $updated = false;
        foreach ($mappings as &$m) {
            if ((int) ($m['ingredient_index'] ?? -1) === $ingredientIndex) {
                $m       = array_merge($m, $mapping, ['ingredient_index' => $ingredientIndex]);
                $updated = true;
                break;
            }
        }
        unset($m);

        if (!$updated) {
            $mappings[] = array_merge($mapping, ['ingredient_index' => $ingredientIndex]);
        }

        $parsed['ingredient_mappings']     = $mappings;
        $candidate->parsed_recipe_json     = $parsed;
        $candidate->save();
    }

    public function approve(ImportedRecipeCandidate $candidate, int $userId): void
    {
        $candidate->status      = 'approved';
        $candidate->reviewed_by = $userId;
        $candidate->reviewed_at = now();
        $candidate->save();
    }

    public function reject(ImportedRecipeCandidate $candidate, int $userId, ?string $reason): void
    {
        $candidate->status      = 'rejected';
        $candidate->reviewed_by = $userId;
        $candidate->reviewed_at = now();
        $candidate->save();

        RecipeReviewLog::create([
            'imported_recipe_candidate_id' => $candidate->id,
            'reviewed_by'                  => $userId,
            'action'                       => 'rejected',
            'comments'                     => $reason,
        ]);
    }

    public function createRecipe(ImportedRecipeCandidate $candidate, array $input, int $userId): Recipe
    {
        return DB::transaction(function () use ($candidate, $input, $userId) {
            $parsed = $candidate->parsed_recipe_json ?? [];

            $recipe = Recipe::create([
                'name'               => $candidate->raw_title,
                'nombre'             => $candidate->raw_title,
                'description'        => $candidate->raw_description,
                'descripcion'        => $candidate->raw_description ?? '',
                'tiempo'             => '',
                'img'                => $candidate->raw_image_url ?? '',
                'video'              => '',
                'porcion'            => isset($parsed['servings']) ? (string) $parsed['servings'] : '',
                'calorias'           => 0,
                'source_url'         => $candidate->source_url,
                'source_site'        => $candidate->source_site,
                'source_type'        => 'imported',
                'owner_user_id'      => $userId,
                'is_public'          => (bool) ($input['is_public'] ?? false),
                'is_official'        => false,
                'is_verified'        => false,
                'status'             => 'active',
                'servings'           => $parsed['servings'] ?? null,
                'prep_time_minutes'  => $parsed['prep_minutes'] ?? null,
                'cook_time_minutes'  => $parsed['cook_minutes'] ?? null,
            ]);

            $mappings = $parsed['ingredient_mappings'] ?? [];
            $sort     = 1;
            foreach ($mappings as $m) {
                if (empty($m['ingredient_id']) || empty($m['unit_id'])) {
                    continue;
                }
                RecipeIngredient::create([
                    'recipe_id'     => $recipe->id,
                    'ingredient_id' => $m['ingredient_id'],
                    'unit_id'       => $m['unit_id'],
                    'quantity'      => $m['quantity'] ?? null,
                    'is_optional'   => (bool) ($m['is_optional'] ?? false),
                    'notes'         => $m['notes'] ?? null,
                    'sort_order'    => $sort++,
                ]);
            }

            $steps = $candidate->raw_steps_json ?? ($parsed['steps'] ?? []);
            foreach ($steps as $step) {
                $desc = is_string($step) ? $step : ($step['description'] ?? ($step['text'] ?? ''));
                if (!$desc) {
                    continue;
                }
                RecipeStep::create([
                    'recipe_id'   => $recipe->id,
                    'step_number' => $step['step_number'] ?? 1,
                    'description' => $desc,
                ]);
            }

            $candidate->status            = 'recipe_created';
            $candidate->created_recipe_id = $recipe->id;
            $candidate->reviewed_by       = $userId;
            $candidate->reviewed_at       = now();
            $candidate->save();

            RecipeReviewLog::create([
                'recipe_id'                    => $recipe->id,
                'imported_recipe_candidate_id' => $candidate->id,
                'reviewed_by'                  => $userId,
                'action'                       => 'recipe_created',
                'comments'                     => $input['notes'] ?? null,
            ]);

            return $recipe;
        });
    }

    public function findIngredient(int $id): ?Ingredient
    {
        return Ingredient::where('id', $id)->where('status', 'active')->whereNull('deleted_at')->first();
    }

    public function findUnit(int $id): ?UnitMeasure
    {
        return UnitMeasure::where('id', $id)->where('status', 'active')->first();
    }

    public function duplicateRecipeExists(?string $sourceUrl): bool
    {
        if (!$sourceUrl) {
            return false;
        }
        return Recipe::where('source_url', $sourceUrl)->whereNull('deleted_at')->exists();
    }

    public function unmappedIngredientIndices(ImportedRecipeCandidate $candidate): array
    {
        $rawIngredients = $candidate->raw_ingredients_json ?? [];
        if (empty($rawIngredients)) {
            return [];
        }

        $parsed   = $candidate->parsed_recipe_json ?? [];
        $mappings = $parsed['ingredient_mappings'] ?? [];

        $mappedIndices = array_map(function ($m) {
            return (int) ($m['ingredient_index'] ?? -1);
        }, $mappings);

        $unmapped = [];
        foreach (array_keys($rawIngredients) as $idx) {
            if (!in_array((int) $idx, $mappedIndices, true)) {
                $unmapped[] = (int) $idx;
            }
        }
        return $unmapped;
    }
}
