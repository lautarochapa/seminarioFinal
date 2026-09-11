<?php

namespace App\Services\RecipeImportCandidates;

use App\AuditLog;
use App\Exceptions\RecipeImportCandidates\RecipeImportCandidatesException;
use App\ImportedRecipeCandidate;
use App\Recipe;
use App\Repositories\RecipeImportCandidates\RecipeImportCandidatesRepository;
use App\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RecipeImportCandidatesService
{
    private RecipeImportCandidatesRepository $repo;
    private IngredientMatchService $matcher;

    public function __construct(RecipeImportCandidatesRepository $repo, IngredientMatchService $matcher)
    {
        $this->repo    = $repo;
        $this->matcher = $matcher;
    }

    public function ingredientSuggestions(User $user, int $id): array
    {
        $this->assertPermission($user);
        return $this->resolveSuggestions($this->findOrFail($id));
    }

    /**
     * Usa las sugerencias ya persistidas (calculadas al crear el candidate o
     * en la ultima recalculacion) cuando estan disponibles y coinciden en
     * cantidad con los ingredientes crudos actuales; si no (candidatas
     * historicas de antes de esta funcionalidad, o el texto crudo cambio),
     * las calcula al vuelo sin persistir — el GET de revision sigue siendo
     * de solo lectura, la persistencia ocurre solo via recalculateSuggestions.
     */
    private function resolveSuggestions(ImportedRecipeCandidate $candidate): array
    {
        $rawCount  = count($candidate->raw_ingredients_json ?? []);
        $persisted = ($candidate->parsed_recipe_json ?? [])['ingredient_suggestions'] ?? null;

        if (is_array($persisted) && count($persisted) === $rawCount) {
            return $persisted;
        }

        return $this->matcher->suggestForCandidate($candidate);
    }

    public function recalculateSuggestions(User $user, int $id, string $ip, string $ua): ImportedRecipeCandidate
    {
        $this->assertPermission($user);
        $candidate = $this->findOrFail($id);
        $this->assertNotFinalized($candidate);

        $suggestions = $this->matcher->suggestForCandidate($candidate);
        $this->repo->saveSuggestions($candidate, $suggestions);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'import_candidate_suggestions_recalculated',
            'entity_name' => 'imported_recipe_candidates',
            'entity_id'   => $candidate->id,
            'old_values'  => null,
            'new_values'  => ['suggestions_count' => count($suggestions)],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $candidate->fresh();
    }

    /**
     * Recalcula sugerencias para varias candidatas de una sola vez (ej.
     * "Recalcular pendientes visibles"). Nunca toca ingredient_mappings
     * (mapeos manuales); las candidatas finalizadas o inexistentes se
     * omiten sin abortar el resto del lote.
     */
    public function recalculateSuggestionsBulk(User $user, array $ids, string $ip, string $ua): array
    {
        $this->assertPermission($user);

        $recalculated = 0;
        $skipped      = 0;
        $results      = [];

        foreach ($ids as $rawId) {
            $id = (int) $rawId;
            try {
                $candidate = $this->findOrFail($id);
            } catch (RecipeImportCandidatesException $e) {
                $skipped++;
                $results[] = ['id' => $id, 'status' => 'skipped', 'reason' => 'not_found'];
                continue;
            }

            if (in_array($candidate->status, RecipeImportCandidatesRepository::TERMINAL_STATUSES, true)) {
                $skipped++;
                $results[] = ['id' => $id, 'status' => 'skipped', 'reason' => 'finalized'];
                continue;
            }

            $suggestions = $this->matcher->suggestForCandidate($candidate);
            $this->repo->saveSuggestions($candidate, $suggestions);
            $recalculated++;
            $results[] = ['id' => $id, 'status' => 'recalculated', 'suggestions_count' => count($suggestions)];
        }

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'import_candidate_suggestions_recalculated_bulk',
            'entity_name' => 'imported_recipe_candidates',
            'entity_id'   => 0,
            'old_values'  => null,
            'new_values'  => ['requested' => count($ids), 'recalculated' => $recalculated, 'skipped' => $skipped],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return [
            'requested'    => count($ids),
            'recalculated' => $recalculated,
            'skipped'      => $skipped,
            'results'      => $results,
        ];
    }

    /**
     * Aplica de una sola vez todas las sugerencias de confianza (no
     * "unresolved") a ingredient_mappings, reutilizando exactamente el
     * mismo mapIngredient()/setMapping() que usa la accion manual — nunca
     * pisa un indice ya mapeado manualmente, nunca crea Ingredient, y solo
     * completa la unidad cuando parsed_unit_text resuelve a una UnitMeasure
     * real e inequivoca (ver IngredientMatchService::resolveUnitId). Un
     * ingrediente sugerido sin unidad resoluble queda sin aplicar: sigue
     * requiriendo que el admin elija la unidad a mano.
     */
    public function applySuggestedMappings(User $user, int $id, string $ip, string $ua): array
    {
        $this->assertPermission($user);
        $candidate = $this->findOrFail($id);
        $this->assertNotFinalized($candidate);

        $suggestions = $this->resolveSuggestions($candidate);
        // Se persisten aca tambien (no solo en recalculateSuggestions) para
        // que required_unmapped_count/optional_unmapped_count (que dependen
        // de is_optional guardado en ingredient_suggestions) queden
        // correctos inmediatamente despues de aplicar, sin exigir un
        // recalculo previo.
        $this->repo->saveSuggestions($candidate, $suggestions);
        $unmappedSet = array_flip($this->repo->unmappedIngredientIndices($candidate));

        $applied = 0;
        $skippedUnresolved = 0;
        $skippedNoUnit = 0;
        $skippedAlreadyMapped = 0;

        foreach ($suggestions as $suggestion) {
            $index = (int) ($suggestion['index'] ?? -1);

            if (!isset($unmappedSet[$index])) {
                $skippedAlreadyMapped++;
                continue;
            }

            if (empty($suggestion['suggested_ingredient_id'])) {
                $skippedUnresolved++;
                continue;
            }

            $ingredient = $this->repo->findIngredient((int) $suggestion['suggested_ingredient_id']);
            if (!$ingredient) {
                $skippedUnresolved++;
                continue;
            }

            $unitId = $this->matcher->resolveUnitId($suggestion['parsed_unit_text'] ?? null);
            if (!$unitId) {
                $skippedNoUnit++;
                continue;
            }

            $this->repo->setMapping($candidate, $index, [
                'ingredient_id' => $ingredient->id,
                'unit_id'       => $unitId,
                'quantity'      => $suggestion['parsed_quantity'] ?? null,
                'is_optional'   => (bool) ($suggestion['is_optional'] ?? false),
                'notes'         => null,
            ]);
            $applied++;
        }

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'import_candidate_suggestions_applied',
            'entity_name' => 'imported_recipe_candidates',
            'entity_id'   => $candidate->id,
            'old_values'  => null,
            'new_values'  => [
                'applied'                => $applied,
                'skipped_unresolved'     => $skippedUnresolved,
                'skipped_no_unit'        => $skippedNoUnit,
                'skipped_already_mapped' => $skippedAlreadyMapped,
            ],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return [
            'candidate'               => $candidate->fresh(),
            'applied'                 => $applied,
            'skipped_unresolved'      => $skippedUnresolved,
            'skipped_no_unit'         => $skippedNoUnit,
            'skipped_already_mapped'  => $skippedAlreadyMapped,
        ];
    }

    /**
     * Aplica sugerencias sobre varios candidatos de una sola vez (ej.
     * "Aplicar sugerencias seleccionadas"). Reutiliza integramente
     * applySuggestedMappings() por candidato: mismo mapIngredient()/
     * setMapping(), mismos limites (nunca pisa mapeo manual, nunca crea
     * Ingredient/UnitMeasure). Cada candidato se procesa de forma aislada
     * (sin transaccion global): si uno falla, los demas se siguen
     * procesando igual.
     */
    public function applySuggestedMappingsBulk(User $user, array $ids, string $ip, string $ua): array
    {
        $this->assertPermission($user);

        $processed = 0;
        $failed    = 0;
        $results   = [];

        foreach ($ids as $rawId) {
            $id = (int) $rawId;
            try {
                $result = $this->applySuggestedMappings($user, $id, $ip, $ua);
                $processed++;
                $results[] = [
                    'candidate_id'            => $id,
                    'status'                  => 'processed',
                    'applied'                 => $result['applied'],
                    'skipped_unresolved'      => $result['skipped_unresolved'],
                    'skipped_no_unit'         => $result['skipped_no_unit'],
                    'skipped_already_mapped'  => $result['skipped_already_mapped'],
                    'mapping_ready'           => empty($this->repo->requiredUnmappedIngredientIndices($result['candidate'])),
                ];
            } catch (RecipeImportCandidatesException $e) {
                $failed++;
                $results[] = [
                    'candidate_id' => $id,
                    'status'       => 'failed',
                    'reason'       => $e->getErrorCode(),
                ];
            }
        }

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'import_candidate_apply_suggestions_bulk',
            'entity_name' => 'imported_recipe_candidates',
            'entity_id'   => 0,
            'old_values'  => null,
            'new_values'  => ['requested' => count($ids), 'processed' => $processed, 'failed' => $failed],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return [
            'requested' => count($ids),
            'processed' => $processed,
            'failed'    => $failed,
            'results'   => $results,
        ];
    }

    /**
     * Aprueba y crea la receta de varios candidatos "listos" de una sola
     * vez (ej. "Aprobar seleccionadas listas"). Por candidato, aprobar +
     * crear la Recipe es ATOMICO (ver approveAndCreateRecipe()): si la
     * creacion de la Recipe falla (ej. URL de origen duplicada), el
     * candidato vuelve exactamente a como estaba, sin quedar "approved"
     * sin Recipe.
     *
     * mapping_ready se recalcula aca mismo sobre el estado actual en DB
     * (requiredUnmappedIngredientIndices), nunca se confia en un
     * mapping_summary que pudo haber mostrado el frontend antes de este
     * request. Un candidato con ingredientes requeridos sin mapear se
     * omite con reason="mapping_not_ready", nunca se fuerza la aprobacion.
     *
     * Un candidato que YA tiene Recipe (status=recipe_created, o un
     * approved historico con created_recipe_id ya seteado) se omite:
     * nunca se crea una segunda Recipe para el mismo candidato, ni en un
     * rerun del batch. "rejected" tambien se omite (terminal, sin
     * Recipe, no se reabre desde el batch). Un candidato approved
     * historico SIN Recipe (ej. aprobado a mano con el boton individual
     * pero nunca completado con "Crear receta") no se omite: el batch
     * intenta completarlo, ya que ese es justamente el objetivo de esta
     * accion.
     */
    public function approveBulk(User $user, array $ids, string $ip, string $ua): array
    {
        $this->assertPermission($user);

        $approved = 0;
        $skipped  = 0;
        $failed   = 0;
        $results  = [];

        foreach ($ids as $rawId) {
            $id = (int) $rawId;

            try {
                $candidate = $this->findOrFail($id);
            } catch (RecipeImportCandidatesException $e) {
                $failed++;
                $results[] = ['candidate_id' => $id, 'status' => 'failed', 'reason' => $e->getErrorCode()];
                continue;
            }

            if ($candidate->status === 'rejected'
                || $candidate->status === 'recipe_created'
                || $candidate->created_recipe_id !== null
            ) {
                $skipped++;
                $results[] = ['candidate_id' => $id, 'status' => 'skipped', 'reason' => 'already_finalized'];
                continue;
            }

            if (!empty($this->repo->requiredUnmappedIngredientIndices($candidate))) {
                $skipped++;
                $results[] = ['candidate_id' => $id, 'status' => 'skipped', 'reason' => 'mapping_not_ready'];
                continue;
            }

            try {
                $recipe = $this->approveAndCreateRecipe($user, $id, ['is_public' => false], $ip, $ua);
                $approved++;
                $results[] = ['candidate_id' => $id, 'status' => 'approved', 'recipe_id' => $recipe->id];
            } catch (RecipeImportCandidatesException $e) {
                $failed++;
                $results[] = ['candidate_id' => $id, 'status' => 'failed', 'reason' => $e->getErrorCode()];
            }
        }

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'import_candidate_approve_bulk',
            'entity_name' => 'imported_recipe_candidates',
            'entity_id'   => 0,
            'old_values'  => null,
            'new_values'  => ['requested' => count($ids), 'approved' => $approved, 'skipped' => $skipped, 'failed' => $failed],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return [
            'requested' => count($ids),
            'approved'  => $approved,
            'skipped'   => $skipped,
            'failed'    => $failed,
            'results'   => $results,
        ];
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

    /**
     * Validaciones compartidas por approve() (individual) y
     * approveAndCreateRecipe() (batch atomico): titulo, pasos y que no
     * queden ingredientes requeridos sin mapear. Un ingrediente opcional
     * sin mapear (ej. "Opcional laurel y azafran") no bloquea la
     * aprobacion; sigue visible para que el admin lo mapee si quiere, pero
     * no es requisito.
     */
    private function assertApprovable(ImportedRecipeCandidate $candidate): void
    {
        if (empty($candidate->raw_title)) {
            throw RecipeImportCandidatesException::invalidForApproval('La candidata no tiene titulo. Editalo antes de aprobar.');
        }

        $steps = $candidate->raw_steps_json ?? (($candidate->parsed_recipe_json ?? [])['steps'] ?? []);
        if (empty($steps)) {
            throw RecipeImportCandidatesException::invalidForApproval('La candidata no tiene pasos. Agrega al menos un paso antes de aprobar.');
        }

        $requiredUnmapped = $this->repo->requiredUnmappedIngredientIndices($candidate);
        if (!empty($requiredUnmapped)) {
            throw RecipeImportCandidatesException::missingIngredientMappings($requiredUnmapped);
        }
    }

    public function approve(User $user, int $id, string $ip, string $ua): ImportedRecipeCandidate
    {
        $this->assertPermission($user);
        $candidate = $this->findOrFail($id);
        $this->assertNotFinalized($candidate);
        $this->assertApprovable($candidate);

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

    /**
     * Aprueba y crea la Recipe de un candidato en UNA sola transaccion
     * (usado por approveBulk(), donde el objetivo es terminar el flujo
     * completo por candidato de forma atomica). Si algo falla despues de
     * aprobar (ej. duplicateRecipe() por URL de origen repetida), TODO se
     * revierte: el candidato vuelve exactamente a su estado anterior
     * (pending/parsed/approved), sin quedar "approved" huerfano sin
     * Recipe. No reimplementa nada: reutiliza las mismas piezas de mas
     * bajo nivel que approve()/createRecipe() individuales
     * (assertApprovable(), repo->approve(), repo->duplicateRecipeExists(),
     * repo->createRecipe()) envueltas en un unico DB::transaction(); esos
     * dos metodos individuales no se tocan ni se llaman desde aca (cada
     * uno abre su propia transaccion/consulta por separado, lo que
     * impedia el rollback conjunto que esto resuelve).
     */
    public function approveAndCreateRecipe(User $user, int $id, array $input, string $ip, string $ua): Recipe
    {
        $this->assertPermission($user);

        return DB::transaction(function () use ($user, $id, $input, $ip, $ua) {
            $candidate = $this->findOrFail($id);
            $this->assertNotFinalized($candidate);
            $this->assertApprovable($candidate);

            if ($candidate->source_url && $this->repo->duplicateRecipeExists($candidate->source_url)) {
                throw RecipeImportCandidatesException::duplicateRecipe();
            }

            $originalStatus = $candidate->status;
            $this->repo->approve($candidate, $user->id);

            AuditLog::create([
                'user_id'     => $user->id,
                'action'      => 'import_candidate_approved',
                'entity_name' => 'imported_recipe_candidates',
                'entity_id'   => $candidate->id,
                'old_values'  => ['status' => $originalStatus],
                'new_values'  => ['status' => 'approved'],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

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
        });
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
