<?php

namespace App\Services\ProductCategories;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\ProductCategory;
use App\Repositories\ProductCategories\ProductCategoryRepository;
use Illuminate\Support\Facades\DB;

class ProductCategoryService
{
    private $categories;

    public function __construct(ProductCategoryRepository $categories)
    {
        $this->categories = $categories;
    }

    public function list(array $filters)
    {
        return $this->categories->paginate($filters);
    }

    public function catalog()
    {
        return $this->categories->activeTree();
    }

    public function show($id)
    {
        return $this->categories->findOrFail($id);
    }

    public function create($actorId, array $data, $ip, $userAgent)
    {
        return DB::transaction(function () use ($actorId, $data, $ip, $userAgent) {
            $this->categories->lockHierarchyWrites();
            $data = $this->prepare($data, true);
            $this->assertFinalState($data['name'], $data['parent_id'] ?? null, $data['status']);
            $category = $this->categories->create($data);
            $this->audit($actorId, 'product-category.created', $category->id, null, $this->auditPayload($category), $ip, $userAgent);

            return $category->fresh('parent');
        });
    }

    public function update($actorId, $id, array $data, $ip, $userAgent)
    {
        return DB::transaction(function () use ($actorId, $id, $data, $ip, $userAgent) {
            $this->categories->lockHierarchyWrites();
            $category = $this->categories->findOrFail($id);
            $data = $this->prepare($data, false);
            $final = array_merge([
                'name' => $category->name,
                'parent_id' => $category->parent_id,
                'status' => $category->status,
            ], $data);
            $this->assertFinalState($final['name'], $final['parent_id'], $final['status'], $category->id);
            $old = $this->auditPayload($category);
            $updated = $this->categories->update($category, $data);
            $new = $this->auditPayload($updated);

            if ($old != $new) {
                $this->audit($actorId, 'product-category.updated', $updated->id, $old, $new, $ip, $userAgent);
            }

            return $updated;
        });
    }

    public function delete($actorId, $id, $ip, $userAgent)
    {
        return DB::transaction(function () use ($actorId, $id, $ip, $userAgent) {
            $this->categories->lockHierarchyWrites();
            $category = $this->categories->findOrFail($id);
            $old = $this->auditPayload($category);
            $category->status = 'inactive';
            $category->save();
            $category->delete();
            $deleted = $this->categories->findWithTrashedOrFail($category->id);
            $new = $this->auditPayload($deleted);

            $this->audit($actorId, 'product-category.deleted', $deleted->id, $old, $new, $ip, $userAgent);

            return $deleted;
        });
    }

    public function restore($actorId, $id, $ip, $userAgent)
    {
        return DB::transaction(function () use ($actorId, $id, $ip, $userAgent) {
            $this->categories->lockHierarchyWrites();
            $category = $this->categories->findWithTrashedOrFail($id);
            if (! $category->trashed()) {
                throw new IngredientException('RESOURCE_NOT_DELETED', 'El recurso no esta eliminado.', 409);
            }
            $this->assertFinalState($category->name, $category->parent_id, 'active', $category->id);
            $old = $this->auditPayload($category);
            $category->restore();
            $category->status = 'active';
            $category->save();
            $restored = $category->fresh('parent');
            $new = $this->auditPayload($restored);

            $this->audit($actorId, 'product-category.restored', $restored->id, $old, $new, $ip, $userAgent);

            return $restored;
        });
    }

    private function prepare(array $data, $creating)
    {
        foreach (['name', 'description', 'status'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if (array_key_exists('name', $data)) {
            $data['name'] = trim(preg_replace('/\s+/u', ' ', $data['name']));
        }

        if ($creating && ! array_key_exists('status', $data)) {
            $data['status'] = 'active';
        }

        return array_intersect_key($data, array_flip(['name', 'description', 'parent_id', 'status']));
    }

    private function assertFinalState($name, $parentId, $status, $categoryId = null)
    {
        if ($categoryId !== null && (int) $parentId === (int) $categoryId) {
            throw new IngredientException('PRODUCT_CATEGORY_SELF_PARENT_FORBIDDEN', 'Una categoria no puede ser su propio padre.', 422);
        }

        $this->assertValidParent($parentId);
        $this->assertNoCycle($categoryId, $parentId);

        if ($status === 'active' && $this->categories->activeNameExists($name, $parentId, $categoryId)) {
            throw new IngredientException('PRODUCT_CATEGORY_NAME_ALREADY_EXISTS', 'Ya existe una categoria activa con ese nombre.', 409);
        }
    }

    private function assertValidParent($parentId)
    {
        if (! $parentId) {
            return;
        }

        if (! $this->categories->existsWithTrashed($parentId)) {
            throw new IngredientException('PRODUCT_CATEGORY_PARENT_NOT_FOUND', 'La categoria padre no existe.', 422);
        }

        if (! $this->categories->activeById($parentId)) {
            throw new IngredientException('PRODUCT_CATEGORY_PARENT_INACTIVE', 'La categoria padre no esta activa.', 422);
        }
    }

    private function assertNoCycle($categoryId, $parentId)
    {
        $current = $parentId ? ProductCategory::withTrashed()->find($parentId) : null;
        $visited = [];

        while ($current) {
            if (($categoryId !== null && (int) $current->id === (int) $categoryId) || isset($visited[$current->id])) {
                throw new IngredientException('PRODUCT_CATEGORY_CYCLE_FORBIDDEN', 'La jerarquia genera un ciclo.', 422);
            }

            $visited[$current->id] = true;
            $current = $current->parent_id ? ProductCategory::withTrashed()->find($current->parent_id) : null;
        }
    }

    private function auditPayload(ProductCategory $category)
    {
        return [
            'name' => $category->name,
            'description' => $category->description,
            'parent_id' => $category->parent_id,
            'status' => $category->status,
            'deleted_at' => $category->deleted_at ? (string) $category->deleted_at : null,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => 'product_categories',
            'entity_id' => (string) $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
