<?php

namespace App\Services\RecipeCategories;

use App\AuditLog;
use App\Exceptions\RecipeCategories\RecipeCategoryException;
use App\RecipeCategory;
use App\Repositories\RecipeCategories\RecipeCategoryRepository;
use Illuminate\Support\Facades\DB;

class RecipeCategoryService
{
    private $repo;

    public function __construct(RecipeCategoryRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(array $filters)
    {
        return $this->repo->paginate($filters);
    }

    public function catalog()
    {
        return $this->repo->activeTree();
    }

    public function show($id)
    {
        return $this->repo->findOrFail($id);
    }

    public function create($actorId, array $data, $ip, $userAgent)
    {
        $data = $this->prepare($data);

        if ($this->repo->nameExists($data['name'])) {
            throw new RecipeCategoryException('RECIPE_CATEGORY_NAME_ALREADY_EXISTS', 'El nombre ya existe.', 409);
        }

        $this->assertValidParent($data['parent_id'] ?? null);

        return DB::transaction(function () use ($actorId, $data, $ip, $userAgent) {
            $category = RecipeCategory::create([
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'parent_id'   => $data['parent_id'] ?? null,
                'status'      => $data['status'] ?? 'active',
            ]);

            $this->audit($actorId, 'recipe-category.created', $category->id, null, $this->auditPayload($category), $ip, $userAgent);

            return $category->fresh('parent');
        });
    }

    public function update($actorId, $id, array $data, $ip, $userAgent)
    {
        $category = $this->repo->findOrFail($id);
        $data = $this->prepare($data, false);

        if (array_key_exists('name', $data) && $this->repo->nameExists($data['name'], $category->id)) {
            throw new RecipeCategoryException('RECIPE_CATEGORY_NAME_ALREADY_EXISTS', 'El nombre ya existe.', 409);
        }

        if (array_key_exists('parent_id', $data)) {
            if ((int) $data['parent_id'] === (int) $category->id) {
                throw new RecipeCategoryException('RECIPE_CATEGORY_SELF_PARENT_FORBIDDEN', 'Una categoria no puede ser su propio padre.', 422);
            }
            $this->assertValidParent($data['parent_id']);
            $this->assertNoCycle($category->id, $data['parent_id']);
        }

        return DB::transaction(function () use ($actorId, $category, $data, $ip, $userAgent) {
            $old = $this->auditPayload($category);
            $allowed = ['name', 'description', 'parent_id', 'status'];
            $category->fill(array_intersect_key($data, array_flip($allowed)));
            $category->save();
            $fresh = $category->fresh('parent');
            $new = $this->auditPayload($fresh);

            if ($old != $new) {
                $this->audit($actorId, 'recipe-category.updated', $fresh->id, $old, $new, $ip, $userAgent);
            }

            return $fresh;
        });
    }

    public function delete($actorId, $id, $ip, $userAgent)
    {
        $category = $this->repo->findOrFail($id);

        if ($this->repo->hasChildren($category->id)) {
            throw new RecipeCategoryException('RECIPE_CATEGORY_HAS_CHILDREN', 'La categoria tiene subcategorias asociadas.', 409);
        }

        if ($category->recipes()->exists()) {
            throw new RecipeCategoryException('RECIPE_CATEGORY_HAS_RECIPES', 'La categoria tiene recetas asociadas.', 409);
        }

        return DB::transaction(function () use ($actorId, $category, $ip, $userAgent) {
            $old = $this->auditPayload($category);
            $category->status = 'inactive';
            $category->save();
            $category->delete();
            $deleted = $this->repo->findWithTrashedOrFail($category->id);
            $new = $this->auditPayload($deleted);

            $this->audit($actorId, 'recipe-category.deleted', $deleted->id, $old, $new, $ip, $userAgent);

            return $deleted;
        });
    }

    public function restore($actorId, $id, $ip, $userAgent)
    {
        $category = $this->repo->findWithTrashedOrFail($id);

        return DB::transaction(function () use ($actorId, $category, $ip, $userAgent) {
            $old = $this->auditPayload($category);
            $category->restore();
            $category->status = 'active';
            $category->save();
            $restored = $category->fresh('parent');
            $new = $this->auditPayload($restored);

            $this->audit($actorId, 'recipe-category.restored', $restored->id, $old, $new, $ip, $userAgent);

            return $restored;
        });
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

    private function assertValidParent($parentId)
    {
        if (!$parentId) {
            return;
        }

        if (!$this->repo->existsWithTrashed($parentId)) {
            throw new RecipeCategoryException('RECIPE_CATEGORY_PARENT_NOT_FOUND', 'La categoria padre no existe.', 422);
        }

        if (!$this->repo->activeById($parentId)) {
            throw new RecipeCategoryException('RECIPE_CATEGORY_PARENT_INACTIVE', 'La categoria padre no esta activa.', 422);
        }
    }

    private function assertNoCycle($categoryId, $parentId)
    {
        $current = $parentId ? RecipeCategory::find($parentId) : null;
        while ($current) {
            if ((int) $current->id === (int) $categoryId) {
                throw new RecipeCategoryException('RECIPE_CATEGORY_CYCLE_FORBIDDEN', 'La jerarquia genera un ciclo.', 422);
            }
            $current = $current->parent_id ? RecipeCategory::find($current->parent_id) : null;
        }
    }

    private function auditPayload(RecipeCategory $category)
    {
        return [
            'name'        => $category->name,
            'description' => $category->description,
            'parent_id'   => $category->parent_id,
            'status'      => $category->status,
            'deleted_at'  => $category->deleted_at ? (string) $category->deleted_at : null,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id'     => $actorId,
            'action'      => $action,
            'entity_name' => 'recipe_categories',
            'entity_id'   => (string) $entityId,
            'old_values'  => $old,
            'new_values'  => $new,
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
        ]);
    }
}
