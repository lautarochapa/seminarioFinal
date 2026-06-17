<?php

namespace App\Repositories\RecipeFavoritesCooked;

use App\FamilyGroup;
use App\Recipe;
use App\RecipeCookLog;
use App\RecipeFavorite;
use App\StockItem;
use App\StockMovement;
use App\UnitConversion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RecipeFavoritesCookedRepository
{
    public function findVisible($id, int $userId): ?Recipe
    {
        return Recipe::where('status', 'active')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($userId) {
                $q->where('is_public', true)->orWhere('owner_user_id', $userId);
            })
            ->find($id);
    }

    public function findFavorite(int $userId, int $recipeId): ?RecipeFavorite
    {
        return RecipeFavorite::where('user_id', $userId)->where('recipe_id', $recipeId)->first();
    }

    public function createFavorite(int $userId, int $recipeId): RecipeFavorite
    {
        return RecipeFavorite::create(['user_id' => $userId, 'recipe_id' => $recipeId]);
    }

    public function deleteFavorite(RecipeFavorite $favorite): void
    {
        $favorite->delete();
    }

    public function paginateFavorites(int $userId, int $page, int $perPage): LengthAwarePaginator
    {
        return RecipeFavorite::with(['recipe' => function ($q) {
            $q->select(['id', 'name', 'difficulty', 'prep_time_minutes', 'cook_time_minutes', 'servings', 'source_type', 'is_official', 'is_public', 'status', 'category_id']);
        }])
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function findFamilyGroup($id): ?FamilyGroup
    {
        return FamilyGroup::where('status', 'active')->find($id);
    }

    public function isFamilyMember(int $groupId, int $userId): bool
    {
        return DB::table('family_group_members')
            ->where('family_group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    public function createCookLog(array $data): RecipeCookLog
    {
        return RecipeCookLog::create($data);
    }

    public function paginateCookLogs(int $userId, int $page, int $perPage): LengthAwarePaginator
    {
        return RecipeCookLog::with(['recipe' => function ($q) {
            $q->select(['id', 'name', 'difficulty', 'servings', 'source_type', 'is_official']);
        }])
            ->where('user_id', $userId)
            ->orderByDesc('cooked_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function loadRecipeWithIngredients(int $recipeId): ?Recipe
    {
        return Recipe::with(['ingredients.ingredient', 'ingredients.unit'])
            ->where('status', 'active')
            ->find($recipeId);
    }

    /**
     * Returns stock items ordered oldest-first for FIFO deduction.
     * Returns rows with: id, quantity, unit_id, product_id, ingredient_id (from product join)
     */
    public function stockItemsForIngredient(int $groupId, int $ingredientId): array
    {
        return DB::table('stock_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('si.family_group_id', $groupId)
            ->where('p.ingredient_id', $ingredientId)
            ->where('si.status', 'active')
            ->whereNull('si.deleted_at')
            ->where('si.quantity', '>', 0)
            ->orderBy('si.expiration_date')
            ->orderBy('si.id')
            ->select('si.id', 'si.quantity', 'si.unit_id', 'si.product_id', 'p.ingredient_id')
            ->get()
            ->toArray();
    }

    public function findConversionFactor(int $fromUnitId, int $toUnitId, ?int $ingredientId): ?float
    {
        if ($fromUnitId === $toUnitId) {
            return 1.0;
        }

        $conv = UnitConversion::where('from_unit_id', $fromUnitId)
            ->where('to_unit_id', $toUnitId)
            ->where(function ($q) use ($ingredientId) {
                if ($ingredientId) {
                    $q->where('ingredient_id', $ingredientId)->orWhereNull('ingredient_id');
                } else {
                    $q->whereNull('ingredient_id');
                }
            })
            ->where('status', 'active')
            ->orderByRaw('CASE WHEN ingredient_id IS NOT NULL THEN 0 ELSE 1 END')
            ->first();

        return $conv ? (float) $conv->factor : null;
    }

    public function deductStockItem(int $stockItemId, float $deductQty): void
    {
        StockItem::where('id', $stockItemId)->decrement('quantity', $deductQty);
    }

    public function createStockMovement(array $data): StockMovement
    {
        return StockMovement::create($data);
    }

    public function markCookLogDiscounted(int $logId): void
    {
        RecipeCookLog::where('id', $logId)->update(['stock_discounted' => true]);
    }
}
