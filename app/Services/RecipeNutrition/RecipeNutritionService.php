<?php

namespace App\Services\RecipeNutrition;

use App\AuditLog;
use App\Exceptions\RecipeNutrition\RecipeNutritionException;
use App\Recipe;
use App\RecipeNutrition;
use App\Repositories\RecipeNutrition\RecipeNutritionRepository;
use App\User;
use Illuminate\Support\Facades\DB;

class RecipeNutritionService
{
    private RecipeNutritionRepository $repo;

    private const NUTRIENT_MAP = [
        'calories'      => ['calories_total',       'calories_per_serving'],
        'protein'       => ['protein_total',         'protein_per_serving'],
        'carbohydrates' => ['carbohydrates_total',   'carbohydrates_per_serving'],
        'fat'           => ['fat_total',             'fat_per_serving'],
        'sodium'        => ['sodium_total',          'sodium_per_serving'],
        'sugar'         => ['sugar_total',           'sugar_per_serving'],
        'fiber'         => ['fiber_total',           'fiber_per_serving'],
    ];

    public function __construct(RecipeNutritionRepository $repo)
    {
        $this->repo = $repo;
    }

    public function show(User $user, $recipeId): array
    {
        try {
            $recipe = $this->repo->findActiveOrFail($recipeId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw RecipeNutritionException::recipeNotFound();
        }

        if (!$recipe->is_public
            && $recipe->owner_user_id !== $user->id
            && !$user->hasPermission('recipes.manage')
        ) {
            throw RecipeNutritionException::recipeNotVisible();
        }

        $nutrition = $this->repo->findNutrition($recipe->id);

        return ['recipe' => $recipe, 'nutrition' => $nutrition];
    }

    public function recalculate(User $user, $recipeId, string $ip, string $userAgent): RecipeNutrition
    {
        if (!$user->hasPermission('recipes.manage')) {
            throw RecipeNutritionException::forbidden();
        }

        $recipe = $this->repo->loadForCalculation($recipeId);

        return DB::transaction(function () use ($user, $recipe, $ip, $userAgent) {
            $data = $this->calculate($recipe);

            $nutrition = $this->repo->upsertNutrition($recipe->id, $data);

            AuditLog::create([
                'user_id'     => $user->id,
                'action'      => 'recipe-nutrition.recalculated',
                'entity_name' => 'recipe_nutrition',
                'entity_id'   => $nutrition->id,
                'old_values'  => null,
                'new_values'  => json_encode(['calculation_status' => $data['calculation_status']]),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $nutrition;
        });
    }

    private function calculate(Recipe $recipe): array
    {
        $ingredients = $recipe->ingredients;

        if ($ingredients->isEmpty()) {
            return array_merge($this->emptyTotals(), ['calculation_status' => 'no_ingredients', 'calculated_at' => now()]);
        }

        $gramsUnit   = $this->repo->gramsUnit();
        $codes       = array_keys(self::NUTRIENT_MAP);
        $totals      = array_fill_keys($codes, 0.0);
        $incomplete  = 0;
        $total       = 0;

        foreach ($ingredients as $ri) {
            $total++;
            $ingredient = $ri->ingredient;

            if (!$ingredient) {
                $incomplete++;
                continue;
            }

            $grams = $this->toGrams((float) $ri->quantity, (int) $ri->unit_id, $ingredient, $gramsUnit);

            if ($grams === null) {
                $incomplete++;
                continue;
            }

            $nutrients = $ingredient->nutrients->where('pivot.status', 'active');

            if ($nutrients->isEmpty()) {
                $incomplete++;
                continue;
            }

            $hasAny = false;
            foreach ($codes as $code) {
                $nutrient = $nutrients->firstWhere('code', $code);
                if ($nutrient) {
                    $totals[$code] += ((float) $nutrient->pivot->amount_per_100g / 100.0) * $grams;
                    $hasAny = true;
                }
            }

            if (!$hasAny) {
                $incomplete++;
            }
        }

        $status   = $incomplete === 0 ? 'complete' : 'partial';
        $servings = ($recipe->servings !== null && $recipe->servings > 0) ? (int) $recipe->servings : null;

        $data = ['calculation_status' => $status, 'calculated_at' => now()];

        foreach (self::NUTRIENT_MAP as $code => [$totalField, $servingField]) {
            $data[$totalField]   = round($totals[$code], 2);
            $data[$servingField] = $servings !== null ? round($totals[$code] / $servings, 2) : null;
        }

        return $data;
    }

    private function toGrams(float $quantity, int $unitId, $ingredient, $gramsUnit): ?float
    {
        if (!$gramsUnit) {
            return null;
        }

        if ($unitId === $gramsUnit->id) {
            return $quantity;
        }

        $conversion = $this->repo->findConversionToGrams($unitId, $gramsUnit->id, $ingredient->id);

        if ($conversion) {
            return $quantity * (float) $conversion->factor;
        }

        return null;
    }

    private function emptyTotals(): array
    {
        $data = [];
        foreach (self::NUTRIENT_MAP as [$totalField, $servingField]) {
            $data[$totalField]   = null;
            $data[$servingField] = null;
        }
        return $data;
    }
}
