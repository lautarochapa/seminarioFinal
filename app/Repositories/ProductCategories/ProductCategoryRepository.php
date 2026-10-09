<?php

namespace App\Repositories\ProductCategories;

use App\Exceptions\Ingredients\IngredientException;
use App\ProductCategory;
use Illuminate\Support\Facades\DB;

class ProductCategoryRepository
{
    // Shared by every category writer/importer. Use the one-bigint overload;
    // the transaction releases this PostgreSQL lock on commit or rollback.
    public const PRODUCT_CATEGORY_WRITE_LOCK = 1128484931;

    public function lockHierarchyWrites()
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?)', [self::PRODUCT_CATEGORY_WRITE_LOCK]);
        }
    }

    public function paginate(array $filters)
    {
        $query = ProductCategory::with('parent')->withCount(['children', 'products']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', $search)
                    ->orWhere('description', 'ILIKE', $search);
            });
        }

        if (! empty($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (! empty($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        $sortMap = [
            'id' => 'id',
            'name' => 'name',
            'status' => 'status',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
        $sort = $sortMap[$filters['sort'] ?? 'name'] ?? 'name';
        $order = strtolower($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderBy($sort, $order)->orderBy('id')->paginate($perPage);
    }

    public function activeTree()
    {
        return ProductCategory::with('activeChildren')
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function findOrFail($id)
    {
        $category = ProductCategory::with('parent')->find($id);

        if (! $category) {
            throw new IngredientException('PRODUCT_CATEGORY_NOT_FOUND', 'La categoria de producto solicitada no existe.', 404);
        }

        return $category;
    }

    public function findWithTrashedOrFail($id)
    {
        $category = ProductCategory::withTrashed()->with('parent')->find($id);

        if (! $category) {
            throw new IngredientException('PRODUCT_CATEGORY_NOT_FOUND', 'La categoria de producto solicitada no existe.', 404);
        }

        return $category;
    }

    public function activeById($id)
    {
        return ProductCategory::where('id', $id)
            ->where('status', 'active')
            ->first();
    }

    public function existsWithTrashed($id)
    {
        return ProductCategory::withTrashed()->where('id', $id)->exists();
    }

    public function activeNameExists($name, $parentId = null, $exceptId = null)
    {
        $query = ProductCategory::where('status', 'active');

        if ($parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }

        if ($exceptId) {
            $query->where('id', '<>', $exceptId);
        }

        // Compare only siblings. PHP normalization keeps Unicode/whitespace
        // behavior identical in PostgreSQL and SQLite, including older rows.
        $normalize = function ($value) {
            return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value)), 'UTF-8');
        };
        $normalizedName = $normalize($name);

        return $query->pluck('name')->contains(function ($existing) use ($normalize, $normalizedName) {
            return $normalize($existing) === $normalizedName;
        });
    }

    public function create(array $data)
    {
        return ProductCategory::create($data);
    }

    public function update(ProductCategory $category, array $data)
    {
        $category->fill($data);
        $category->save();

        return $category->fresh('parent');
    }
}
