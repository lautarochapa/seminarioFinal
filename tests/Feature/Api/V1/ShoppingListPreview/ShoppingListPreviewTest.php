<?php

namespace Tests\Feature\Api\V1\ShoppingListPreview;

use App\AuditLog;
use App\Brand;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Ingredient;
use App\MealPlan;
use App\MealPlanItem;
use App\MealType;
use App\Product;
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

class ShoppingListPreviewTest extends TestCase
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
            'code' => $code ?: 'slp_'.uniqid(),
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

    private function planWithRecipe(FamilyGroup $group, User $user, Recipe $recipe, $status = 'planned', $servings = 1)
    {
        $mealType = MealType::create([
            'code' => 'slp_'.uniqid(),
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
            'meal_type_id' => $mealType->id,
            'recipe_id' => $recipe->id,
            'servings_total' => $servings,
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

    public function test_auth_required()
    {
        $this->getJson('/api/v1/family-groups/1/meal-plans/1/shopping-list-preview')->assertStatus(401);
    }

    public function test_access_to_other_group_rejected()
    {
        [$user, $group] = $this->groupWithMember();
        [$other, $otherGroup] = $this->groupWithMember();
        $unit = $this->unit();
        $recipe = $this->recipe($this->ingredient($unit), $unit);
        $plan = $this->planWithRecipe($otherGroup, $other, $recipe);

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/meal-plans/'.$plan->id.'/shopping-list-preview')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'MEAL_PLAN_ITEM_PLAN_NOT_FOUND');
    }

    public function test_preview_returns_missing_ingredients()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit('g');
        $ingredient = $this->ingredient($unit);
        $recipe = $this->recipe($ingredient, $unit, 5);
        $plan = $this->planWithRecipe($group, $user, $recipe, 'planned', 2);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/meal-plans/'.$plan->id.'/shopping-list-preview');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.ingredient.id', $ingredient->id)
            ->assertJsonPath('data.0.unit.id', $unit->id);
        $this->assertEquals(10.0, $response->json('data.0.missing_quantity'));
    }

    public function test_preview_ignores_sufficient_stock()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit('g');
        $ingredient = $this->ingredient($unit);
        $product = $this->product($ingredient, $unit);
        $recipe = $this->recipe($ingredient, $unit, 5);
        $this->stock($group, $product, $unit, 10);
        $plan = $this->planWithRecipe($group, $user, $recipe, 'planned', 1);

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/meal-plans/'.$plan->id.'/shopping-list-preview')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_preview_subtracts_partial_stock()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit('g');
        $ingredient = $this->ingredient($unit);
        $product = $this->product($ingredient, $unit);
        $recipe = $this->recipe($ingredient, $unit, 8);
        $this->stock($group, $product, $unit, 3);
        $plan = $this->planWithRecipe($group, $user, $recipe);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/meal-plans/'.$plan->id.'/shopping-list-preview')
            ->assertStatus(200);
        $this->assertEquals(5.0, $response->json('data.0.missing_quantity'));
    }

    public function test_preview_uses_unit_conversions()
    {
        [$user, $group] = $this->groupWithMember();
        $gram = $this->unit('g');
        $kilo = $this->unit('kg');
        $ingredient = $this->ingredient($gram);
        $product = $this->product($ingredient, $kilo);
        $recipe = $this->recipe($ingredient, $gram, 1500);
        $this->stock($group, $product, $kilo, 1);
        UnitConversion::create([
            'from_unit_id' => $kilo->id,
            'to_unit_id' => $gram->id,
            'factor' => 1000,
            'status' => 'active',
        ]);
        $plan = $this->planWithRecipe($group, $user, $recipe);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/meal-plans/'.$plan->id.'/shopping-list-preview')
            ->assertStatus(200);
        $this->assertEquals(500.0, $response->json('data.0.missing_quantity'));
    }

    public function test_preview_reports_incompatible_units_without_subtracting()
    {
        [$user, $group] = $this->groupWithMember();
        $gram = $this->unit('g');
        $cup = $this->unit('cup');
        $ingredient = $this->ingredient($gram);
        $product = $this->product($ingredient, $cup);
        $recipe = $this->recipe($ingredient, $gram, 5);
        $this->stock($group, $product, $cup, 10);
        $plan = $this->planWithRecipe($group, $user, $recipe);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/meal-plans/'.$plan->id.'/shopping-list-preview')
            ->assertStatus(200)
            ->assertJsonPath('data.0.incomplete', true);
        $this->assertEquals(5.0, $response->json('data.0.missing_quantity'));
    }

    public function test_generate_creates_shopping_list()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit('g');
        $ingredient = $this->ingredient($unit);
        $recipe = $this->recipe($ingredient, $unit, 5);
        $plan = $this->planWithRecipe($group, $user, $recipe);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/meal-plans/'.$plan->id.'/generate-shopping-list');

        $response->assertStatus(201)
            ->assertJsonPath('data.meal_plan_id', $plan->id)
            ->assertJsonCount(1, 'data.items');

        $this->assertDatabaseHas('shopping_list_items', [
            'ingredient_id' => $ingredient->id,
            'quantity' => '5.0000',
        ]);
    }

    public function test_generate_reuses_existing_plan_list()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit('g');
        $ingredient = $this->ingredient($unit);
        $recipe = $this->recipe($ingredient, $unit, 5);
        $plan = $this->planWithRecipe($group, $user, $recipe);
        ShoppingList::create([
            'family_group_id' => $group->id,
            'meal_plan_id' => $plan->id,
            'created_by' => $user->id,
            'source_type' => 'meal_plan',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/meal-plans/'.$plan->id.'/generate-shopping-list')
            ->assertStatus(200);

        $this->assertSame(1, ShoppingList::where('meal_plan_id', $plan->id)->count());
    }

    public function test_generate_is_audited()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit('g');
        $ingredient = $this->ingredient($unit);
        $recipe = $this->recipe($ingredient, $unit, 5);
        $plan = $this->planWithRecipe($group, $user, $recipe);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/meal-plans/'.$plan->id.'/generate-shopping-list')
            ->assertStatus(201);

        $this->assertTrue(AuditLog::where('entity_name', 'shopping_lists')->where('action', 'shopping_list.generated')->exists());
    }
}
