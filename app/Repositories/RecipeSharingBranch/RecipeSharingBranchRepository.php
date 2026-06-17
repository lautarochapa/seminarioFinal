<?php

namespace App\Repositories\RecipeSharingBranch;

use App\Recipe;
use App\RecipeIngredient;
use App\RecipeStep;
use Illuminate\Support\Facades\DB;

class RecipeSharingBranchRepository
{
    public function findActive($id): ?Recipe
    {
        return Recipe::where('status', 'active')->whereNull('deleted_at')->find($id);
    }

    public function findVisible($id, int $userId): ?Recipe
    {
        return Recipe::where('status', 'active')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($userId) {
                $q->where('is_public', true)->orWhere('owner_user_id', $userId);
            })
            ->find($id);
    }

    public function setPublic(Recipe $recipe, bool $public): void
    {
        $recipe->is_public   = $public;
        $recipe->is_verified = false;
        $recipe->save();
    }

    public function loadForBranch(int $recipeId): Recipe
    {
        return Recipe::with(['ingredients', 'steps', 'tags'])->findOrFail($recipeId);
    }

    public function createBranch(Recipe $origin, int $userId): Recipe
    {
        return Recipe::create([
            'name'                   => $origin->name,
            'normalized_name'        => $origin->normalized_name,
            'description'            => $origin->description,
            'source_type'            => 'user',
            'owner_user_id'          => $userId,
            'is_public'              => false,
            'is_official'            => false,
            'is_verified'            => false,
            'status'                 => 'active',
            'servings'               => $origin->servings,
            'prep_time_minutes'      => $origin->prep_time_minutes,
            'cook_time_minutes'      => $origin->cook_time_minutes,
            'difficulty'             => $origin->difficulty,
            'category_id'            => $origin->category_id,
            'source_url'             => $origin->source_url,
            'source_site'            => $origin->source_site,
            'source_author'          => $origin->source_author,
            'branched_from_recipe_id'=> $origin->id,
            'nombre'                 => $origin->getRawOriginal('nombre') ?? $origin->name,
            'descripcion'            => $origin->descripcion,
            'tiempo'                 => $origin->tiempo,
            'img'                    => $origin->img,
            'video'                  => $origin->video,
            'porcion'                => $origin->porcion,
            'calorias'               => $origin->calorias,
        ]);
    }

    public function copyIngredients(Recipe $origin, Recipe $branch): void
    {
        foreach ($origin->ingredients as $ri) {
            RecipeIngredient::create([
                'recipe_id'          => $branch->id,
                'ingredient_id'      => $ri->ingredient_id,
                'specific_product_id'=> $ri->specific_product_id,
                'quantity'           => $ri->quantity,
                'unit_id'            => $ri->unit_id,
                'is_optional'        => $ri->is_optional,
                'notes'              => $ri->notes,
                'sort_order'         => $ri->sort_order,
            ]);
        }
    }

    public function copySteps(Recipe $origin, Recipe $branch): void
    {
        foreach ($origin->steps as $step) {
            RecipeStep::create([
                'recipe_id'         => $branch->id,
                'step_number'       => $step->step_number,
                'description'       => $step->description,
                'estimated_minutes' => $step->estimated_minutes,
            ]);
        }
    }

    public function copyTags(Recipe $origin, Recipe $branch): void
    {
        $tagIds = $origin->tags->pluck('id')->toArray();
        if (!empty($tagIds)) {
            $branch->tags()->attach($tagIds);
        }
    }
}
