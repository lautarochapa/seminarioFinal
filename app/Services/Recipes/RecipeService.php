<?php

namespace App\Services\Recipes;

use App\AuditLog;
use App\Exceptions\Recipes\RecipeException;
use App\Recipe;
use App\RecipeCategory;
use App\Repositories\Recipes\RecipeRepository;
use App\User;
use Illuminate\Support\Facades\DB;

class RecipeService
{
    private $repo;

    public function __construct(RecipeRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(array $filters)
    {
        return $this->repo->paginate($filters);
    }

    public function show($id)
    {
        $recipe = $this->repo->findOrFail($id);

        if ($recipe->status !== 'active' && !$recipe->trashed()) {
            throw new RecipeException('RECIPE_NOT_FOUND', 'La receta no existe.', 404);
        }

        return $recipe;
    }

    public function create(User $actor, array $data, $ip, $userAgent)
    {
        $data = $this->prepare($data);

        if (!empty($data['category_id'])) {
            $this->assertCategoryExists($data['category_id']);
        }

        return DB::transaction(function () use ($actor, $data, $ip, $userAgent) {
            $recipe = Recipe::create([
                'name'              => $data['name'],
                'nombre'            => $data['name'],
                'normalized_name'   => $this->normalize($data['name']),
                'description'       => $data['description'] ?? null,
                'descripcion'       => $data['description'] ?? '',
                'tiempo'            => '',
                'img'               => '',
                'video'             => '',
                'porcion'           => '',
                'calorias'          => 0,
                'servings'          => $data['servings'] ?? null,
                'prep_time_minutes' => $data['prep_time_minutes'] ?? null,
                'cook_time_minutes' => $data['cook_time_minutes'] ?? null,
                'difficulty'        => $data['difficulty'] ?? null,
                'category_id'       => $data['category_id'] ?? null,
                'owner_user_id'     => $actor->id,
                'source_type'       => 'user',
                'status'            => $data['status'] ?? 'active',
                'is_public'         => false,
                'is_official'       => false,
                'is_verified'       => false,
            ]);

            $this->audit($actor->id, 'recipe.created', $recipe->id, null, $this->auditPayload($recipe), $ip, $userAgent);

            return $this->repo->findOrFail($recipe->id);
        });
    }

    public function update(User $actor, $id, array $data, $ip, $userAgent)
    {
        $recipe = Recipe::findOrFail($id);

        $this->assertCanEdit($actor, $recipe);

        $data = $this->prepare($data, false);

        if (array_key_exists('category_id', $data) && $data['category_id']) {
            $this->assertCategoryExists($data['category_id']);
        }

        return DB::transaction(function () use ($actor, $recipe, $data, $ip, $userAgent) {
            $old     = $this->auditPayload($recipe);
            $allowed = ['name', 'description', 'servings', 'prep_time_minutes', 'cook_time_minutes', 'difficulty', 'category_id', 'status'];
            $recipe->fill(array_intersect_key($data, array_flip($allowed)));

            if (array_key_exists('name', $data)) {
                $recipe->normalized_name = $this->normalize($data['name']);
            }

            $recipe->save();
            $fresh = $this->repo->findOrFail($recipe->id);
            $new   = $this->auditPayload($fresh);

            if ($old != $new) {
                $this->audit($actor->id, 'recipe.updated', $fresh->id, $old, $new, $ip, $userAgent);
            }

            return $fresh;
        });
    }

    public function delete(User $actor, $id, $ip, $userAgent)
    {
        $recipe = Recipe::findOrFail($id);

        $this->assertCanEdit($actor, $recipe);

        return DB::transaction(function () use ($actor, $recipe, $ip, $userAgent) {
            $old = $this->auditPayload($recipe);
            $recipe->status = 'inactive';
            $recipe->save();
            $recipe->delete();

            $deleted = Recipe::withTrashed()->findOrFail($recipe->id);
            $new     = $this->auditPayload($deleted);

            $this->audit($actor->id, 'recipe.deleted', $deleted->id, $old, $new, $ip, $userAgent);

            return $deleted;
        });
    }

    private function assertCanEdit(User $actor, Recipe $recipe)
    {
        if ($recipe->is_official) {
            if (!$actor->hasPermission('catalog.manage')) {
                throw new RecipeException('RECIPE_OFFICIAL_FORBIDDEN', 'Las recetas oficiales solo pueden ser editadas por administradores.', 403);
            }
            return;
        }

        if ((int) $recipe->owner_user_id !== (int) $actor->id && !$actor->hasPermission('catalog.manage')) {
            throw new RecipeException('RECIPE_EDIT_FORBIDDEN', 'No tiene permiso para modificar esta receta.', 403);
        }
    }

    private function assertCategoryExists($categoryId)
    {
        $cat = RecipeCategory::find($categoryId);

        if (!$cat) {
            throw new RecipeException('RECIPE_CATEGORY_NOT_FOUND', 'La categoria no existe.', 422);
        }

        if ($cat->status !== 'active') {
            throw new RecipeException('RECIPE_CATEGORY_INACTIVE', 'La categoria no esta activa.', 422);
        }
    }

    private function prepare(array $data, $creating = true)
    {
        foreach (['name', 'description'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        return $data;
    }

    private function normalize($name)
    {
        return mb_strtolower(trim($name));
    }

    private function auditPayload(Recipe $recipe)
    {
        return [
            'name'              => $recipe->name,
            'description'       => $recipe->description,
            'servings'          => $recipe->servings,
            'prep_time_minutes' => $recipe->prep_time_minutes,
            'cook_time_minutes' => $recipe->cook_time_minutes,
            'difficulty'        => $recipe->difficulty,
            'category_id'       => $recipe->category_id,
            'source_type'       => $recipe->source_type,
            'status'            => $recipe->status,
            'is_official'       => (bool) $recipe->is_official,
            'deleted_at'        => $recipe->deleted_at ? (string) $recipe->deleted_at : null,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id'     => $actorId,
            'action'      => $action,
            'entity_name' => 'recipes',
            'entity_id'   => (string) $entityId,
            'old_values'  => $old,
            'new_values'  => $new,
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
        ]);
    }
}
