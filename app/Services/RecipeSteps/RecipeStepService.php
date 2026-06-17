<?php

namespace App\Services\RecipeSteps;

use App\AuditLog;
use App\Exceptions\RecipeSteps\RecipeStepException;
use App\Recipe;
use App\RecipeStep;
use App\Repositories\RecipeSteps\RecipeStepRepository;
use App\User;
use Illuminate\Support\Facades\DB;

class RecipeStepService
{
    private $repo;

    public function __construct(RecipeStepRepository $repo)
    {
        $this->repo = $repo;
    }

    public function add(User $actor, $recipeId, array $data, $ip, $userAgent)
    {
        $recipe = $this->findRecipeOrFail($recipeId);
        $this->assertCanEdit($actor, $recipe);

        $stepNumber = $data['step_number'] ?? $this->repo->nextStepNumber($recipeId);

        if ($this->repo->stepNumberExists($recipeId, $stepNumber)) {
            throw new RecipeStepException('RECIPE_STEP_NUMBER_DUPLICATE', 'Ya existe un paso con ese numero.', 409);
        }

        return DB::transaction(function () use ($actor, $recipeId, $stepNumber, $data, $ip, $userAgent) {
            $step = RecipeStep::create([
                'recipe_id'         => $recipeId,
                'step_number'       => $stepNumber,
                'description'       => trim($data['description']),
                'estimated_minutes' => $data['estimated_minutes'] ?? null,
            ]);

            $this->audit($actor->id, 'recipe-step.added', $step->id, null, $this->auditPayload($step), $ip, $userAgent);

            return $step->fresh();
        });
    }

    public function update(User $actor, $recipeId, $stepId, array $data, $ip, $userAgent)
    {
        $recipe = $this->findRecipeOrFail($recipeId);
        $this->assertCanEdit($actor, $recipe);

        $step = $this->repo->findOrFail($recipeId, $stepId);

        if (array_key_exists('step_number', $data) && (int) $data['step_number'] !== (int) $step->step_number) {
            if ($this->repo->stepNumberExists($recipeId, $data['step_number'], $step->id)) {
                throw new RecipeStepException('RECIPE_STEP_NUMBER_DUPLICATE', 'Ya existe un paso con ese numero.', 409);
            }
        }

        return DB::transaction(function () use ($actor, $step, $data, $ip, $userAgent) {
            $old     = $this->auditPayload($step);
            $allowed = ['step_number', 'description', 'estimated_minutes'];
            $fill    = array_intersect_key($data, array_flip($allowed));

            if (isset($fill['description'])) {
                $fill['description'] = trim($fill['description']);
            }

            $step->fill($fill)->save();
            $fresh = $step->fresh();
            $new   = $this->auditPayload($fresh);

            if ($old != $new) {
                $this->audit($actor->id, 'recipe-step.updated', $step->id, $old, $new, $ip, $userAgent);
            }

            return $fresh;
        });
    }

    public function remove(User $actor, $recipeId, $stepId, $ip, $userAgent)
    {
        $recipe = $this->findRecipeOrFail($recipeId);
        $this->assertCanEdit($actor, $recipe);

        $step = $this->repo->findOrFail($recipeId, $stepId);

        return DB::transaction(function () use ($actor, $step, $ip, $userAgent) {
            $old = $this->auditPayload($step);
            $step->delete();

            $this->audit($actor->id, 'recipe-step.removed', $step->id, $old, null, $ip, $userAgent);

            return $step;
        });
    }

    private function findRecipeOrFail($recipeId)
    {
        $recipe = Recipe::find($recipeId);

        if (!$recipe) {
            throw new RecipeStepException('RECIPE_NOT_FOUND', 'La receta no existe.', 404);
        }

        return $recipe;
    }

    private function assertCanEdit(User $actor, Recipe $recipe)
    {
        if ($recipe->is_official) {
            if (!$actor->hasPermission('catalog.manage')) {
                throw new RecipeStepException('RECIPE_EDIT_FORBIDDEN', 'Sin permiso para modificar esta receta.', 403);
            }
            return;
        }

        if ((int) $recipe->owner_user_id !== (int) $actor->id && !$actor->hasPermission('catalog.manage')) {
            throw new RecipeStepException('RECIPE_EDIT_FORBIDDEN', 'Sin permiso para modificar esta receta.', 403);
        }
    }

    private function auditPayload(RecipeStep $step)
    {
        return [
            'recipe_id'         => $step->recipe_id,
            'step_number'       => $step->step_number,
            'description'       => $step->description,
            'estimated_minutes' => $step->estimated_minutes,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id'     => $actorId,
            'action'      => $action,
            'entity_name' => 'recipe_steps',
            'entity_id'   => (string) $entityId,
            'old_values'  => $old,
            'new_values'  => $new,
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
        ]);
    }
}
