<?php

namespace Tests\Feature\Api\V1\RecipeNutrition;

use App\AuditLog;
use App\Ingredient;
use App\Recipe;
use App\RecipeIngredient;
use App\RecipeNutrition;
use App\Role;
use App\UnitMeasure;
use App\Nutrient;
use App\UnitConversion;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecipeNutritionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = factory(User::class)->create();
        $role = Role::where('code', 'super_admin')->first();
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'created_at' => now()]);
        return $user;
    }

    private function recipe(array $overrides = []): Recipe
    {
        $name = $overrides['name'] ?? ('Receta ' . uniqid());
        return Recipe::create(array_merge([
            'name'            => $name,
            'nombre'          => $name,
            'normalized_name' => mb_strtolower($name),
            'descripcion'     => '',
            'tiempo'          => '',
            'img'             => '',
            'video'           => '',
            'porcion'         => '',
            'calorias'        => 0,
            'source_type'     => 'user',
            'status'          => 'active',
            'is_public'       => true,
            'is_official'     => false,
            'is_verified'     => false,
            'servings'        => 4,
        ], $overrides));
    }

    private function gramsUnit(): UnitMeasure
    {
        return UnitMeasure::create([
            'code'   => 'g',
            'name'   => 'Gramo',
            'type'   => 'weight',
            'symbol' => 'g',
            'status' => 'active',
        ]);
    }

    private function nutrient(string $code, int $unitId): Nutrient
    {
        return Nutrient::create([
            'code'    => $code,
            'name'    => $code,
            'unit_id' => $unitId,
            'status'  => 'active',
        ]);
    }

    private function ingredient(UnitMeasure $baseUnit): Ingredient
    {
        $name = 'Ingrediente ' . uniqid();
        return Ingredient::create([
            'name'            => $name,
            'normalized_name' => mb_strtolower($name),
            'base_unit_id'    => $baseUnit->id,
            'is_generic'      => true,
            'is_preparation'  => false,
            'is_supplement'   => false,
            'status'          => 'active',
        ]);
    }

    private function attachNutrient(Ingredient $ingredient, Nutrient $nutrient, float $per100g): void
    {
        DB::table('ingredient_nutrients')->insert([
            'ingredient_id'  => $ingredient->id,
            'nutrient_id'    => $nutrient->id,
            'amount_per_100g'=> $per100g,
            'status'         => 'active',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    private function addIngredient(Recipe $recipe, Ingredient $ingredient, UnitMeasure $unit, float $quantity): RecipeIngredient
    {
        return RecipeIngredient::create([
            'recipe_id'     => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'unit_id'       => $unit->id,
            'quantity'      => $quantity,
            'is_optional'   => false,
            'sort_order'    => 0,
        ]);
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $recipe = $this->recipe();
        $this->getJson('/api/v1/recipes/' . $recipe->id . '/nutrition')
            ->assertStatus(401);
    }

    public function test_receta_inexistente_retorna_404()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user)->getJson('/api/v1/recipes/99999/nutrition')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'RECIPE_NOT_FOUND');
    }

    public function test_retorna_null_si_nutricion_no_calculada()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe();

        $response = $this->actingAs($user)->getJson('/api/v1/recipes/' . $recipe->id . '/nutrition');

        $response->assertStatus(200)
            ->assertJsonPath('data.nutrition', null)
            ->assertJsonPath('data.recipe_id', $recipe->id);
    }

    public function test_retorna_nutricion_persistida()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe();

        RecipeNutrition::create([
            'recipe_id'          => $recipe->id,
            'calculation_status' => 'complete',
            'calories_total'     => 200.0,
            'calories_per_serving' => 50.0,
            'protein_total'      => 10.0,
            'protein_per_serving'=> 2.5,
            'carbohydrates_total'=> 30.0,
            'carbohydrates_per_serving' => 7.5,
            'fat_total'          => 5.0,
            'fat_per_serving'    => 1.25,
            'sodium_total'       => null,
            'sodium_per_serving' => null,
            'sugar_total'        => null,
            'sugar_per_serving'  => null,
            'fiber_total'        => null,
            'fiber_per_serving'  => null,
            'calculated_at'      => now(),
        ]);

        $this->actingAs($user)->getJson('/api/v1/recipes/' . $recipe->id . '/nutrition')
            ->assertStatus(200)
            ->assertJsonPath('data.nutrition.calculation_status', 'complete')
            ->assertJson(['data' => ['nutrition' => ['calories_total' => 200]]]);
    }

    public function test_recalculo_requiere_permiso()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe();

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/' . $recipe->id . '/recalculate-nutrition')
            ->assertStatus(403);
    }

    public function test_recalculo_con_ingredientes_calcula_totales()
    {
        $admin  = $this->admin();
        $grams  = $this->gramsUnit();
        $recipe = $this->recipe(['servings' => 2]);

        $caloriesNutrient = $this->nutrient('calories', $grams->id);
        $proteinNutrient  = $this->nutrient('protein', $grams->id);

        $ing = $this->ingredient($grams);
        $this->attachNutrient($ing, $caloriesNutrient, 400.0); // 400 kcal per 100g
        $this->attachNutrient($ing, $proteinNutrient, 20.0);   // 20g protein per 100g

        $this->addIngredient($recipe, $ing, $grams, 200.0); // 200g

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/recipes/' . $recipe->id . '/recalculate-nutrition');

        $response->assertStatus(200)
            ->assertJsonPath('data.calculation_status', 'complete')
            ->assertJson(['data' => [
                'calories_total'      => 800,
                'calories_per_serving'=> 400,
                'protein_total'       => 40,
                'protein_per_serving' => 20,
            ]]);
    }

    public function test_recalculo_con_conversion_de_unidades()
    {
        $admin  = $this->admin();
        $grams  = $this->gramsUnit();
        $kg     = UnitMeasure::create(['code' => 'kg', 'name' => 'Kilogramo', 'type' => 'weight', 'symbol' => 'kg', 'status' => 'active']);

        UnitConversion::create([
            'from_unit_id' => $kg->id,
            'to_unit_id'   => $grams->id,
            'factor'       => 1000,
            'status'       => 'active',
        ]);

        $recipe = $this->recipe(['servings' => 1]);
        $caloriesNutrient = $this->nutrient('calories', $grams->id);
        $ing = $this->ingredient($grams);
        $this->attachNutrient($ing, $caloriesNutrient, 100.0); // 100 kcal per 100g

        $this->addIngredient($recipe, $ing, $kg, 0.5); // 0.5 kg = 500g

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/recipes/' . $recipe->id . '/recalculate-nutrition');

        $response->assertStatus(200)
            ->assertJson(['data' => ['calories_total' => 500]]); // 100/100 * 500g
    }

    public function test_ingrediente_sin_nutrientes_marca_partial()
    {
        $admin  = $this->admin();
        $grams  = $this->gramsUnit();
        $recipe = $this->recipe(['servings' => 1]);

        $caloriesNutrient = $this->nutrient('calories', $grams->id);

        $ingConDatos = $this->ingredient($grams);
        $this->attachNutrient($ingConDatos, $caloriesNutrient, 200.0);
        $this->addIngredient($recipe, $ingConDatos, $grams, 100.0);

        $ingSinDatos = $this->ingredient($grams); // sin nutrientes cargados
        $this->addIngredient($recipe, $ingSinDatos, $grams, 50.0);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/recipes/' . $recipe->id . '/recalculate-nutrition');

        $response->assertStatus(200)
            ->assertJsonPath('data.calculation_status', 'partial');
    }

    public function test_receta_sin_ingredientes_marca_no_ingredients()
    {
        $admin  = $this->admin();
        $recipe = $this->recipe(['servings' => 4]);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/recipes/' . $recipe->id . '/recalculate-nutrition');

        $response->assertStatus(200)
            ->assertJsonPath('data.calculation_status', 'no_ingredients');
    }

    public function test_porciones_nulas_no_dividen_por_cero()
    {
        $admin  = $this->admin();
        $grams  = $this->gramsUnit();
        $recipe = $this->recipe(['servings' => null]);

        $caloriesNutrient = $this->nutrient('calories', $grams->id);
        $ing = $this->ingredient($grams);
        $this->attachNutrient($ing, $caloriesNutrient, 100.0);
        $this->addIngredient($recipe, $ing, $grams, 100.0);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/recipes/' . $recipe->id . '/recalculate-nutrition');

        $response->assertStatus(200)
            ->assertJson(['data' => ['calories_total' => 100]])
            ->assertJsonPath('data.calories_per_serving', null);
    }

    public function test_auditoria_registra_recalculo()
    {
        $admin  = $this->admin();
        $recipe = $this->recipe(['servings' => 1]);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/recipes/' . $recipe->id . '/recalculate-nutrition')
            ->assertStatus(200);

        $this->assertTrue(AuditLog::where('action', 'recipe-nutrition.recalculated')
            ->where('entity_name', 'recipe_nutrition')
            ->exists());
    }
}
