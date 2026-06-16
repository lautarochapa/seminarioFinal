<?php

namespace App\Services\IngredientCategories;

use App\AuditLog;
use App\Exceptions\IngredientCategories\IngredientCategoryException;
use App\IngredientCategory;
use App\Repositories\IngredientCategories\IngredientCategoryRepository;
use Illuminate\Support\Facades\DB;

class IngredientCategoryService
{
    private $repo;

    public function __construct(IngredientCategoryRepository $repo)
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

        if ($this->repo->codeExists($data['code'])) {
            throw new IngredientCategoryException('INGREDIENT_CATEGORY_CODE_ALREADY_EXISTS', 'El código ya existe.', 409);
        }

        $this->assertValidParent($data['parent_id'] ?? null);

        return DB::transaction(function () use ($actorId, $data, $ip, $userAgent) {
            $category = IngredientCategory::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => ($data['status'] ?? 'active') === 'active',
                'status' => $data['status'] ?? 'active',
            ]);

            $this->audit($actorId, 'ingredient-category.created', $category->id, null, $this->auditPayload($category), $ip, $userAgent);

            return $category->fresh('parent');
        });
    }

    public function update($actorId, $id, array $data, $ip, $userAgent)
    {
        $category = $this->repo->findOrFail($id);
        $data = $this->prepare($data, false);

        if (array_key_exists('code', $data) && $this->repo->codeExists($data['code'], $category->id)) {
            throw new IngredientCategoryException('INGREDIENT_CATEGORY_CODE_ALREADY_EXISTS', 'El código ya existe.', 409);
        }

        if (array_key_exists('parent_id', $data)) {
            if ((int) $data['parent_id'] === (int) $category->id) {
                throw new IngredientCategoryException('INGREDIENT_CATEGORY_SELF_PARENT_FORBIDDEN', 'Una categoría no puede ser su propio padre.', 422);
            }
            $this->assertValidParent($data['parent_id']);
            $this->assertNoCycle($category->id, $data['parent_id']);
        }

        return DB::transaction(function () use ($actorId, $category, $data, $ip, $userAgent) {
            $old = $this->auditPayload($category);
            $allowed = ['code', 'name', 'description', 'parent_id', 'sort_order', 'status'];
            $category->fill(array_intersect_key($data, array_flip($allowed)));
            if (array_key_exists('status', $data)) {
                $category->is_active = $data['status'] === 'active';
            }
            $category->save();
            $fresh = $category->fresh('parent');
            $new = $this->auditPayload($fresh);

            if ($old != $new) {
                $this->audit($actorId, 'ingredient-category.updated', $fresh->id, $old, $new, $ip, $userAgent);
            }

            return $fresh;
        });
    }

    public function delete($actorId, $id, $ip, $userAgent)
    {
        $category = $this->repo->findOrFail($id);

        if ($this->repo->hasChildren($category->id)) {
            throw new IngredientCategoryException('INGREDIENT_CATEGORY_HAS_CHILDREN', 'La categoría tiene subcategorías asociadas.', 409);
        }

        if ($category->ingredients()->exists()) {
            throw new IngredientCategoryException('INGREDIENT_CATEGORY_HAS_INGREDIENTS', 'La categoría tiene ingredientes asociados.', 409);
        }

        return DB::transaction(function () use ($actorId, $category, $ip, $userAgent) {
            $old = $this->auditPayload($category);
            $category->status = 'inactive';
            $category->is_active = false;
            $category->save();
            $category->delete();
            $deleted = $this->repo->findWithTrashedOrFail($category->id);
            $new = $this->auditPayload($deleted);

            $this->audit($actorId, 'ingredient-category.deleted', $deleted->id, $old, $new, $ip, $userAgent);

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
            $category->is_active = true;
            $category->save();
            $restored = $category->fresh('parent');
            $new = $this->auditPayload($restored);

            $this->audit($actorId, 'ingredient-category.restored', $restored->id, $old, $new, $ip, $userAgent);

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

        if (array_key_exists('code', $data)) {
            $data['code'] = $this->normalizeCode($data['code']);
        }

        if ($creating && empty($data['code'])) {
            $data['code'] = $this->normalizeCode($data['name']);
        }

        return $data;
    }

    private function normalizeCode($code)
    {
        $code = strtolower(trim($code));
        $code = preg_replace('/[^a-z0-9]+/', '_', $code);

        return trim($code, '_');
    }

    private function assertValidParent($parentId)
    {
        if (!$parentId) {
            return;
        }

        if (!$this->repo->existsWithTrashed($parentId)) {
            throw new IngredientCategoryException('INGREDIENT_CATEGORY_PARENT_NOT_FOUND', 'La categoría padre no existe.', 422);
        }

        if (!$this->repo->activeById($parentId)) {
            throw new IngredientCategoryException('INGREDIENT_CATEGORY_PARENT_INACTIVE', 'La categoría padre no está activa.', 422);
        }
    }

    private function assertNoCycle($categoryId, $parentId)
    {
        $current = $parentId ? IngredientCategory::find($parentId) : null;
        while ($current) {
            if ((int) $current->id === (int) $categoryId) {
                throw new IngredientCategoryException('INGREDIENT_CATEGORY_CYCLE_FORBIDDEN', 'La jerarquía genera un ciclo.', 422);
            }
            $current = $current->parent_id ? IngredientCategory::find($current->parent_id) : null;
        }
    }

    private function auditPayload(IngredientCategory $category)
    {
        return [
            'code' => $category->code,
            'name' => $category->name,
            'description' => $category->description,
            'parent_id' => $category->parent_id,
            'sort_order' => $category->sort_order,
            'is_active' => (bool) $category->is_active,
            'status' => $category->status,
            'deleted_at' => $category->deleted_at ? (string) $category->deleted_at : null,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => 'ingredient_categories',
            'entity_id' => (string) $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
