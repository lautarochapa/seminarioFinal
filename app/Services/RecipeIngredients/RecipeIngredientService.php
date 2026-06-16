<?php

namespace App\Services\RecipeIngredients;

use App\AuditLog;
use App\Exceptions\RecipeIngredients\RecipeIngredientException;
use App\Recipe;
use App\RecipeIngredient;
use App\Repositories\RecipeIngredients\RecipeIngredientRepository;
use App\User;
use Illuminate\Support\Facades\DB;

class RecipeIngredientService
{
    private $repo;

    public function __construct(RecipeIngredientRepository $repo)
    {
        $this->repo = $repo;
    }

    public function add(User $actor, $recipeId, array $data, $ip, $userAgent)
    {
        $recipe = $this->findRecipeOrFail($recipeId);
        $this->assertCanEdit($actor, $recipe);

        $this->assertActiveIngredient($data['ingredient_id']);
        $this->assertActiveUnit($data['unit_id']);

        if ($this->repo->existsInRecipe($recipeId, $data['ingredient_id'])) {
            throw new RecipeIngredientException('RECIPE_INGREDIENT_DUPLICATE', 'El ingrediente ya existe en la receta.', 409);
        }

        return DB::transaction(function () use ($actor, $recipeId, $data, $ip, $userAgent) {
            $row = RecipeIngredient::create([
                'recipe_id'           => $recipeId,
                'ingredient_id'       => $data['ingredient_id'],
                'unit_id'             => $data['unit_id'],
                'quantity'            => $data['quantity'],
                'specific_product_id' => $data['specific_product_id'] ?? null,
                'notes'               => $data['notes'] ?? null,
                'is_optional'         => $data['is_optional'] ?? false,
                'sort_order'          => $data['sort_order'] ?? 0,
            ]);

            $this->audit($actor->id, 'recipe-ingredient.added', $row->id, null, $this->auditPayload($row), $ip, $userAgent);

            return $row->fresh(['ingredient', 'unit', 'specificProduct']);
        });
    }

    public function update(User $actor, $recipeId, $id, array $data, $ip, $userAgent)
    {
        $recipe = $this->findRecipeOrFail($recipeId);
        $this->assertCanEdit($actor, $recipe);

        $row = $this->repo->findOrFail($recipeId, $id);

        if (array_key_exists('unit_id', $data)) {
            $this->assertActiveUnit($data['unit_id']);
        }

        return DB::transaction(function () use ($actor, $row, $data, $ip, $userAgent) {
            $old     = $this->auditPayload($row);
            $allowed = ['unit_id', 'quantity', 'specific_product_id', 'notes', 'is_optional', 'sort_order'];
            $row->fill(array_intersect_key($data, array_flip($allowed)));
            $row->save();

            $fresh = $row->fresh(['ingredient', 'unit', 'specificProduct']);
            $new   = $this->auditPayload($fresh);

            if ($old != $new) {
                $this->audit($actor->id, 'recipe-ingredient.updated', $row->id, $old, $new, $ip, $userAgent);
            }

            return $fresh;
        });
    }

    public function remove(User $actor, $recipeId, $id, $ip, $userAgent)
    {
        $recipe = $this->findRecipeOrFail($recipeId);
        $this->assertCanEdit($actor, $recipe);

        $row = $this->repo->findOrFail($recipeId, $id);

        return DB::transaction(function () use ($actor, $row, $ip, $userAgent) {
            $old = $this->auditPayload($row);
            $row->delete();

            $this->audit($actor->id, 'recipe-ingredient.removed', $row->id, $old, null, $ip, $userAgent);

            return $row;
        });
    }

    private function findRecipeOrFail($recipeId)
    {
        $recipe = Recipe::find($recipeId);

        if (!$recipe) {
            throw new RecipeIngredientException('RECIPE_NOT_FOUND', 'La receta no existe.', 404);
        }

        return $recipe;
    }

    private function assertCanEdit(User $actor, Recipe $recipe)
    {
        if ($recipe->is_official) {
            if (!$actor->hasPermission('catalog.manage')) {
                throw new RecipeIngredientException('RECIPE_EDIT_FORBIDDEN', 'Sin permiso para modificar esta receta.', 403);
            }
            return;
        }

        if ((int) $recipe->owner_user_id !== (int) $actor->id && !$actor->hasPermission('catalog.manage')) {
            throw new RecipeIngredientException('RECIPE_EDIT_FORBIDDEN', 'Sin permiso para modificar esta receta.', 403);
        }
    }

    private function assertActiveIngredient($id)
    {
        if (!$this->repo->activeIngredient($id)) {
            throw new RecipeIngredientException('INGREDIENT_NOT_FOUND', 'El ingrediente no existe o no esta activo.', 422);
        }
    }

    private function assertActiveUnit($id)
    {
        if (!$this->repo->activeUnit($id)) {
            throw new RecipeIngredientException('UNIT_NOT_FOUND', 'La unidad no existe o no esta activa.', 422);
        }
    }

    private function auditPayload(RecipeIngredient $row)
    {
        return [
            'recipe_id'           => $row->recipe_id,
            'ingredient_id'       => $row->ingredient_id,
            'unit_id'             => $row->unit_id,
            'quantity'            => $row->quantity,
            'specific_product_id' => $row->specific_product_id,
            'notes'               => $row->notes,
            'is_optional'         => (bool) $row->is_optional,
            'sort_order'          => $row->sort_order,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id'     => $actorId,
            'action'      => $action,
            'entity_name' => 'recipe_ingredients',
            'entity_id'   => (string) $entityId,
            'old_values'  => $old,
            'new_values'  => $new,
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
        ]);
    }
}
