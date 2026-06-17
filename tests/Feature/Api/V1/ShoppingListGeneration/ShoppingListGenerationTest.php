<?php

namespace Tests\Feature\Api\V1\ShoppingListGeneration;

use App\AuditLog;
use App\Brand;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Ingredient;
use App\MealPlan;
use App\MealPlanItem;
use App\MealType;
use App\Product;
use App\Purchase;
use App\PurchaseItem;
use App\Recipe;
use App\RecipeIngredient;
use App\ShoppingList;
use App\StockItem;
use App\StockLocation;
use App\UnitConversion;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingListGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function groupWithMember()
    {
        $user = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id, 'status' => 'active']);
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id' => $user->id,
            'role_in_group' => 'owner',
            'status' => 'active',
        ]);

        return [$user, $group];
    }

    private function unit($code = null)
    {
        return UnitMeasure::create([
            'code' => $code ?: 'slg_'.uniqid(),
            'name' => 'Unidad '.uniqid(),
            'type' => 'mass',
            'symbol' => $code ?: 'u',
            'status' => 'active',
        ]);
    }

    private function ingredient(UnitMeasure $unit)
    {
        return Ingredient::create([
            'name' => 'Ingrediente '.uniqid(),
            'normalized_name' => 'ingrediente_'.uniqid(),
            'base_unit_id' => $unit->id,
            'is_generic' => true,
            'is_preparation' => false,
            'is_supplement' => false,
            'status' => 'active',
        ]);
    }

    private function product(Ingredient $ingredient, UnitMeasure $unit)
    {
        $name = 'Producto '.uniqid();
        $brandName = 'Marca '.uniqid();
        $brand = Brand::create([
            'nombre' => $brandName,
            'name' => $brandName,
            'normalized_name' => strtolower($brandName),
            'status' => 'active',
            'padre' => 0,
        ]);

        return Product::create([
            'name' => $name,
            'normalized_name' => strtolower($name),
            'nombre' => $name,
            'brand_id' => $brand->id,
            'codigo' => 'BC'.uniqid(),
            'img' => 'product.png',
            'habilitado' => 1,
            'supply_id' => 0,
            'ingredient_id' => $ingredient->id,
            'default_unit_id' => $unit->id,
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    private function recipe(Ingredient $ingredient, UnitMeasure $unit, $quantity = 5)
    {
        $recipe = Recipe::create([
            'nombre' => 'Receta '.uniqid(),
            'descripcion' => 'Descripcion',
            'tiempo' => '30',
            'img' => '',
            'video' => '',
            'porcion' => '1',
            'calorias' => 100,
            'servings' => 1,
            'is_public' => true,
            'status' => 'active',
        ]);

        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => $quantity,
            'unit_id' => $unit->id,
            'is_optional' => false,
        ]);

        return $recipe;
    }

    private function planWithRecipe(FamilyGroup $group, User $user, Recipe $recipe, $status = 'planned')
    {
        $type = MealType::create([
            'code' => 'slg_'.uniqid(),
            'name' => 'Cena',
            'sort_order' => 1,
            'status' => 'active',
        ]);
        $plan = MealPlan::create([
            'family_group_id' => $group->id,
            'created_by' => $user->id,
            'period_type' => 'daily',
            'start_date' => '2026-06-16',
            'end_date' => '2026-06-16',
            'status' => 'draft',
        ]);
        MealPlanItem::create([
            'meal_plan_id' => $plan->id,
            'date' => '2026-06-16',
            'meal_type_id' => $type->id,
            'recipe_id' => $recipe->id,
            'servings_total' => 1,
            'status' => $status,
            'is_eating_out' => $status === 'eating_out',
        ]);

        return $plan;
    }

    private function stock(FamilyGroup $group, Product $product, UnitMeasure $unit, $quantity)
    {
        $location = StockLocation::create([
            'family_group_id' => $group->id,
            'name' => 'Alacena '.uniqid(),
            'type' => 'pantry',
            'status' => 'active',
        ]);

        return StockItem::create([
            'family_group_id' => $group->id,
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => $quantity,
            'unit_id' => $unit->id,
            'status' => 'active',
        ]);
    }

    private function purchase(FamilyGroup $group, User $user, Product $product, UnitMeasure $unit, $quantity = 3)
    {
        $purchase = Purchase::create([
            'family_group_id' => $group->id,
            'user_id' => $user->id,
            'purchase_date' => '2026-06-10',
            'status' => 'confirmed',
        ]);

        return PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_id' => $unit->id,
        ]);
    }

    public function test_auth_required()
    {
        $this->postJson('/api/v1/family-groups/1/shopping-lists/generate-from-meal-plan')->assertStatus(401);
    }

    public function test_access_to_other_group_rejected()
    {
        [$user, $group] = $this->groupWithMember();
        [$other, $otherGroup] = $this->groupWithMember();
        $unit = $this->unit('g');
        $plan = $this->planWithRecipe($otherGroup, $other, $this->recipe($this->ingredient($unit), $unit));

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/generate-from-meal-plan', [
                'meal_plan_id' => $plan->id,
            ])->assertStatus(404)
            ->assertJsonPath('error.code', 'MEAL_PLAN_ITEM_PLAN_NOT_FOUND');
    }

    public function test_generate_from_meal_plan()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit('g');
        $ingredient = $this->ingredient($unit);
        $plan = $this->planWithRecipe($group, $user, $this->recipe($ingredient, $unit, 5));

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/generate-from-meal-plan', [
                'meal_plan_id' => $plan->id,
            ])->assertStatus(201)
            ->assertJsonPath('data.source_type', 'meal_plan')
            ->assertJsonPath('data.items.0.ingredient.id', $ingredient->id);
    }

    public function test_generate_from_meal_plan_subtracts_available_stock()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit('g');
        $ingredient = $this->ingredient($unit);
        $product = $this->product($ingredient, $unit);
        $plan = $this->planWithRecipe($group, $user, $this->recipe($ingredient, $unit, 5));
        $this->stock($group, $product, $unit, 3);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/generate-from-meal-plan', [
                'meal_plan_id' => $plan->id,
            ])->assertStatus(201)
            ->assertJsonPath('data.items.0.quantity', '2.0000');
    }

    public function test_generate_from_meal_plan_uses_conversions()
    {
        [$user, $group] = $this->groupWithMember();
        $gram = $this->unit('g');
        $kilo = $this->unit('kg');
        $ingredient = $this->ingredient($gram);
        $product = $this->product($ingredient, $kilo);
        $plan = $this->planWithRecipe($group, $user, $this->recipe($ingredient, $gram, 1500));
        $this->stock($group, $product, $kilo, 1);
        UnitConversion::create(['from_unit_id' => $kilo->id, 'to_unit_id' => $gram->id, 'factor' => 1000, 'status' => 'active']);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/generate-from-meal-plan', [
                'meal_plan_id' => $plan->id,
            ])->assertStatus(201)
            ->assertJsonPath('data.items.0.quantity', '500.0000');
    }

    public function test_generate_from_meal_plan_excludes_skipped_items()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit('g');
        $plan = $this->planWithRecipe($group, $user, $this->recipe($this->ingredient($unit), $unit, 5), 'skipped');

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/generate-from-meal-plan', [
                'meal_plan_id' => $plan->id,
            ])->assertStatus(201)
            ->assertJsonCount(0, 'data.items');
    }

    public function test_generate_from_history()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit('g');
        $ingredient = $this->ingredient($unit);
        $product = $this->product($ingredient, $unit);
        $this->purchase($group, $user, $product, $unit, 3);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/generate-from-history')
            ->assertStatus(201)
            ->assertJsonPath('data.source_type', 'history')
            ->assertJsonPath('data.items.0.ingredient.id', $ingredient->id);
    }

    public function test_generate_from_history_requires_history()
    {
        [$user, $group] = $this->groupWithMember();

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/generate-from-history')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SHOPPING_LIST_HISTORY_INSUFFICIENT');
    }

    public function test_duplicates_and_audit()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit('g');
        $plan = $this->planWithRecipe($group, $user, $this->recipe($this->ingredient($unit), $unit, 5));

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/generate-from-meal-plan', [
                'meal_plan_id' => $plan->id,
            ])->assertStatus(201);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/shopping-lists/generate-from-meal-plan', [
                'meal_plan_id' => $plan->id,
            ])->assertStatus(200);

        $this->assertSame(1, ShoppingList::where('meal_plan_id', $plan->id)->count());
        $this->assertTrue(AuditLog::where('entity_name', 'shopping_lists')->where('action', 'shopping_list.generated')->exists());
    }
}
