<?php

namespace App\Services\RecipeImportCandidates;

use App\AuditLog;
use App\Exceptions\RecipeImportCandidates\RecipeImportCandidatesException;
use App\ImportedRecipeCandidate;
use App\Recipe;
use App\Repositories\RecipeImportCandidates\RecipeImportCandidatesRepository;
use App\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RecipeImportCandidatesService
{
    private RecipeImportCandidatesRepository $repo;

    public function __construct(RecipeImportCandidatesRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(User $user, array $filters): LengthAwarePaginator
    {
        $this->assertPermission($user);
        return $this->repo->paginate($filters);
    }

    public function show(User $user, int $id): ImportedRecipeCandidate
    {
        $this->assertPermission($user);
        return $this->findOrFail($id);
    }

    public function update(User $user, int $id, array $input, string $ip, string $ua): ImportedRecipeCandidate
    {
        $this->assertPermission($user);
        $candidate = $this->findOrFail($id);
        $this->assertNotFinalized($candidate);

        $allowed = ['raw_title', 'raw_description', 'raw_image_url', 'raw_ingredients_json', 'raw_steps_json'];
        $fields  = array_intersect_key($input, array_flip($allowed));

        $old = array_intersect_key($candidate->toArray(), $fields);
        $this->repo->updateFields($candidate, $fields);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'import_candidate_updated',
            'entity_name' => 'imported_recipe_candidates',
            'entity_id'   => $candidate->id,
            'old_values'  => $old,
            'new_values'  => $fields,
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $candidate->fresh();
    }

    public function mapIngredient(User $user, int $id, array $input, string $ip, string $ua): ImportedRecipeCandidate
    {
        $this->assertPermission($user);
        $candidate = $this->findOrFail($id);
        $this->assertNotFinalized($candidate);

        $ingredient = $this->repo->findIngredient((int) $input['ingredient_id']);
        if (!$ingredient) {
            throw RecipeImportCandidatesException::ingredientNotFound();
        }

        $unit = $this->repo->findUnit((int) $input['unit_id']);
        if (!$unit) {
            throw RecipeImportCandidatesException::unitNotFound();
        }

        $mapping = [
            'ingredient_id' => $ingredient->id,
            'unit_id'       => $unit->id,
            'quantity'      => isset($input['quantity']) ? (float) $input['quantity'] : null,
            'is_optional'   => (bool) ($input['is_optional'] ?? false),
            'notes'         => $input['notes'] ?? null,
        ];

        $this->repo->setMapping($candidate, (int) $input['ingredient_index'], $mapping);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'import_candidate_ingredient_mapped',
            'entity_name' => 'imported_recipe_candidates',
            'entity_id'   => $candidate->id,
            'old_values'  => null,
            'new_values'  => ['ingredient_index' => $input['ingredient_index'], 'ingredient_id' => $ingredient->id, 'unit_id' => $unit->id],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $candidate->fresh();
    }

    public function approve(User $user, int $id, string $ip, string $ua): ImportedRecipeCandidate
    {
        $this->assertPermission($user);
        $candidate = $this->findOrFail($id);
        $this->assertNotFinalized($candidate);

        if (empty($candidate->raw_title)) {
            throw RecipeImportCandidatesException::invalidForApproval('La candidata no tiene titulo. Editalo antes de aprobar.');
        }

        $steps = $candidate->raw_steps_json ?? (($candidate->parsed_recipe_json ?? [])['steps'] ?? []);
        if (empty($steps)) {
            throw RecipeImportCandidatesException::invalidForApproval('La candidata no tiene pasos. Agrega al menos un paso antes de aprobar.');
        }

        $unmapped = $this->repo->unmappedIngredientIndices($candidate);
        if (!empty($unmapped)) {
            throw RecipeImportCandidatesException::missingIngredientMappings($unmapped);
        }

        $this->repo->approve($candidate, $user->id);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'import_candidate_approved',
            'entity_name' => 'imported_recipe_candidates',
            'entity_id'   => $candidate->id,
            'old_values'  => ['status' => $candidate->status],
            'new_values'  => ['status' => 'approved'],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $candidate->fresh();
    }

    public function reject(User $user, int $id, ?string $reason, string $ip, string $ua): ImportedRecipeCandidate
    {
        $this->assertPermission($user);
        $candidate = $this->findOrFail($id);
        $this->assertNotFinalized($candidate);

        $this->repo->reject($candidate, $user->id, $reason);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'import_candidate_rejected',
            'entity_name' => 'imported_recipe_candidates',
            'entity_id'   => $candidate->id,
            'old_values'  => ['status' => $candidate->status],
            'new_values'  => ['status' => 'rejected', 'reason' => $reason],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $candidate->fresh();
    }

    public function createRecipe(User $user, int $id, array $input, string $ip, string $ua): Recipe
    {
        $this->assertPermission($user);
        $candidate = $this->findOrFail($id);

        if ($candidate->status !== 'approved') {
            throw RecipeImportCandidatesException::notApproved();
        }

        if ($candidate->source_url && $this->repo->duplicateRecipeExists($candidate->source_url)) {
            throw RecipeImportCandidatesException::duplicateRecipe();
        }

        $recipe = $this->repo->createRecipe($candidate, $input, $user->id);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'import_candidate_recipe_created',
            'entity_name' => 'imported_recipe_candidates',
            'entity_id'   => $candidate->id,
            'old_values'  => ['status' => 'approved'],
            'new_values'  => ['status' => 'recipe_created', 'recipe_id' => $recipe->id],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $recipe;
    }

    private function findOrFail(int $id): ImportedRecipeCandidate
    {
        try {
            return $this->repo->findOrFail($id);
        } catch (\RuntimeException $e) {
            throw RecipeImportCandidatesException::candidateNotFound();
        }
    }

    private function assertPermission(User $user): void
    {
        if (!$user->hasPermission('recipes.manage') && !$user->hasRole('super_admin') && !$user->hasRole('recipe_admin')) {
            throw RecipeImportCandidatesException::forbidden();
        }
    }

    private function assertNotFinalized(ImportedRecipeCandidate $candidate): void
    {
        if (in_array($candidate->status, RecipeImportCandidatesRepository::TERMINAL_STATUSES, true)) {
            throw RecipeImportCandidatesException::alreadyFinalized();
        }
    }
}
