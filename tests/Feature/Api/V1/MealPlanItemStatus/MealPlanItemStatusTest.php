<?php

namespace Tests\Feature\Api\V1\MealPlanItemStatus;

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
use App\RecipeCookLog;
use App\RecipeIngredient;
use App\StockItem;
use App\StockLocation;
use App\StockMovement;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealPlanItemStatusTest extends TestCase
{
    use RefreshDatabase;

    private function groupWithMember($role = 'owner')
    {
        $user = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id, 'status' => 'active']);

        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id' => $user->id,
            'role_in_group' => $role,
            'status' => 'active',
        ]);

        return [$user, $group];
    }

    private function unit()
    {
        return UnitMeasure::create([
            'code' => 'mpi_'.uniqid(),
            'name' => 'Gramo',
            'type' => 'mass',
            'symbol' => 'g',
            'status' => 'active',
        ]);
    }

    private function mealType()
    {
        return MealType::create([
            'code' => 'mpi_'.uniqid(),
            'name' => 'Cena',
            'sort_order' => 1,
            'status' => 'active',
        ]);
    }

    private function ingredient(UnitMeasure $unit)
    {
        return Ingredient::create([
            'name' => 'Arroz '.uniqid(),
            'normalized_name' => 'arroz_'.uniqid(),
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

    private function location(FamilyGroup $group)
    {
        return StockLocation::create([
            'family_group_id' => $group->id,
            'name' => 'Alacena '.uniqid(),
            'type' => 'pantry',
            'status' => 'active',
        ]);
    }

    private function recipeWithIngredient(Ingredient $ingredient, UnitMeasure $unit, $quantity = 2)
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

    private function plan(FamilyGroup $group, User $user)
    {
        return MealPlan::create([
            'family_group_id' => $group->id,
            'created_by' => $user->id,
            'period_type' => 'daily',
            'start_date' => '2026-06-16',
            'end_date' => '2026-06-16',
            'status' => 'draft',
        ]);
    }

    private function item(MealPlan $plan, MealType $mealType, array $data = [])
    {
        return MealPlanItem::create(array_merge([
            'meal_plan_id' => $plan->id,
            'date' => '2026-06-16',
            'meal_type_id' => $mealType->id,
            'status' => 'planned',
            'is_eating_out' => false,
        ], $data));
    }

    private function stock(FamilyGroup $group, Product $product, UnitMeasure $unit, $quantity)
    {
        return StockItem::create([
            'family_group_id' => $group->id,
            'product_id' => $product->id,
            'stock_location_id' => $this->location($group)->id,
            'quantity' => $quantity,
            'unit_id' => $unit->id,
            'status' => 'active',
        ]);
    }

    public function test_auth_required()
    {
        $this->postJson('/api/v1/family-groups/1/meal-plans/1/items/1/mark-cooked')->assertStatus(401);
    }

    public function test_access_to_other_group_rejected()
    {
        [$owner, $group] = $this->groupWithMember();
        [$other, $otherGroup] = $this->groupWithMember();
        $plan = $this->plan($otherGroup, $other);
        $item = $this->item($plan, $this->mealType(), ['free_meal_description' => 'Libre']);

        $this->actingAs($owner)
            ->postJson('/api/v1/family-groups/'.$group->id.'/meal-plans/'.$plan->id.'/items/'.$item->id.'/skip')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'MEAL_PLAN_ITEM_PLAN_NOT_FOUND');
    }

    public function test_mark_cooked_records_status_log_and_audit()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $ingredient = $this->ingredient($unit);
        $recipe = $this->recipeWithIngredient($ingredient, $unit, 1);
        $product = $this->product($ingredient, $unit);
        $this->stock($group, $product, $unit, 5);
        $plan = $this->plan($group, $user);
        $item = $this->item($plan, $this->mealType(), ['recipe_id' => $recipe->id, 'servings_total' => 1]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/meal-plans/'.$plan->id.'/items/'.$item->id.'/mark-cooked');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cooked');

        $this->assertTrue(RecipeCookLog::where('meal_plan_item_id', $item->id)->where('stock_discounted', true)->exists());
        $this->assertTrue(AuditLog::where('entity_name', 'meal_plan_items')->where('action', 'meal_plan_item_cooked')->exists());
    }

    public function test_mark_cooked_discounts_stock()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $ingredient = $this->ingredient($unit);
        $recipe = $this->recipeWithIngredient($ingredient, $unit, 2);
        $product = $this->product($ingredient, $unit);
        $stock = $this->stock($group, $product, $unit, 10);
        $plan = $this->plan($group, $user);
        $item = $this->item($plan, $this->mealType(), ['recipe_id' => $recipe->id, 'servings_total' => 2]);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/meal-plans/'.$plan->id.'/items/'.$item->id.'/mark-cooked')
            ->assertStatus(200);

        $this->assertDatabaseHas('stock_items', ['id' => $stock->id, 'quantity' => '6.0000']);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $stock->id,
            'movement_type' => 'recipe_consumption',
            'quantity' => '-4.0000',
        ]);
    }

    public function test_mark_cooked_rejects_insufficient_stock()
    {
        [$user, $group] = $this->groupWithMember();
        $unit = $this->unit();
        $ingredient = $this->ingredient($unit);
        $recipe = $this->recipeWithIngredient($ingredient, $unit, 5);
        $product = $this->product($ingredient, $unit);
        $stock = $this->stock($group, $product, $unit, 2);
        $plan = $this->plan($group, $user);
        $item = $this->item($plan, $this->mealType(), ['recipe_id' => $recipe->id, 'servings_total' => 1]);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/meal-plans/'.$plan->id.'/items/'.$item->id.'/mark-cooked')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MEAL_PLAN_ITEM_INSUFFICIENT_STOCK');

        $this->assertDatabaseHas('meal_plan_items', ['id' => $item->id, 'status' => 'planned']);
        $this->assertDatabaseHas('stock_items', ['id' => $stock->id, 'quantity' => '2.0000']);
    }

    public function test_double_execution_rejected()
    {
        [$user, $group] = $this->groupWithMember();
        $plan = $this->plan($group, $user);
        $item = $this->item($plan, $this->mealType(), ['free_meal_description' => 'Libre', 'status' => 'skipped']);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/meal-plans/'.$plan->id.'/items/'.$item->id.'/skip')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MEAL_PLAN_ITEM_ALREADY_FINALIZED');
    }

    public function test_skip_sets_status_without_stock_discount()
    {
        [$user, $group] = $this->groupWithMember();
        $plan = $this->plan($group, $user);
        $item = $this->item($plan, $this->mealType(), ['free_meal_description' => 'Libre']);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/meal-plans/'.$plan->id.'/items/'.$item->id.'/skip', [
                'notes' => 'No se cocina',
            ])->assertStatus(200)
            ->assertJsonPath('data.status', 'skipped');

        $this->assertDatabaseMissing('recipe_cook_logs', ['meal_plan_item_id' => $item->id]);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_skip_eating_out_sets_flag()
    {
        [$user, $group] = $this->groupWithMember();
        $plan = $this->plan($group, $user);
        $item = $this->item($plan, $this->mealType(), ['free_meal_description' => 'Libre']);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/meal-plans/'.$plan->id.'/items/'.$item->id.'/skip', [
                'eating_out' => true,
            ])->assertStatus(200)
            ->assertJsonPath('data.status', 'eating_out')
            ->assertJsonPath('data.is_eating_out', true);
    }

    public function test_skip_is_audited()
    {
        [$user, $group] = $this->groupWithMember();
        $plan = $this->plan($group, $user);
        $item = $this->item($plan, $this->mealType(), ['free_meal_description' => 'Libre']);

        $this->actingAs($user)
            ->postJson('/api/v1/family-groups/'.$group->id.'/meal-plans/'.$plan->id.'/items/'.$item->id.'/skip')
            ->assertStatus(200);

        $this->assertTrue(AuditLog::where('entity_name', 'meal_plan_items')->where('action', 'meal_plan_item_skipped')->exists());
    }
}
