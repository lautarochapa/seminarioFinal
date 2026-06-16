<?php

namespace Tests\Feature\Api\V1\RecipeAvailability;

use App\FamilyGroup;
use App\Ingredient;
use App\Product;
use App\Recipe;
use App\RecipeIngredient;
use App\StockItem;
use App\UnitConversion;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecipeAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function unit(string $code): UnitMeasure
    {
        return UnitMeasure::firstOrCreate(['code' => $code], [
            'name'   => $code,
            'type'   => 'weight',
            'symbol' => $code,
            'status' => 'active',
        ]);
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

    private function ingredient(UnitMeasure $baseUnit): Ingredient
    {
        $name = 'Ing ' . uniqid();
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

    private function product(Ingredient $ingredient, UnitMeasure $pkgUnit): Product
    {
        $name = 'Prod ' . uniqid();
        return Product::create([
            'name'            => $name,
            'normalized_name' => mb_strtolower($name),
            'ingredient_id'   => $ingredient->id,
            'package_unit_id' => $pkgUnit->id,
            'net_quantity'    => 100,
            'status'          => 'active',
            'is_active'       => true,
            'is_verified'     => false,
            'nombre'          => $name,
            'brand_id'        => 0,
            'codigo'          => uniqid(),
            'img'             => '',
            'habilitado'      => 1,
            'supply_id'       => 0,
        ]);
    }

    private function addIngredient(Recipe $recipe, Ingredient $ingredient, UnitMeasure $unit, float $qty, bool $optional = false): RecipeIngredient
    {
        return RecipeIngredient::create([
            'recipe_id'     => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'unit_id'       => $unit->id,
            'quantity'      => $qty,
            'is_optional'   => $optional,
            'sort_order'    => 0,
        ]);
    }

    private function stockItem(FamilyGroup $group, Product $product, UnitMeasure $unit, float $qty): StockItem
    {
        return StockItem::create([
            'family_group_id' => $group->id,
            'product_id'      => $product->id,
            'unit_id'         => $unit->id,
            'quantity'        => $qty,
            'is_open'         => false,
            'status'          => 'active',
        ]);
    }

    private function familyGroup(User $user): FamilyGroup
    {
        $g = FamilyGroup::create([
            'name'          => 'Grupo ' . uniqid(),
            'owner_user_id' => $user->id,
            'status'        => 'active',
        ]);
        DB::table('family_group_members')->insert([
            'family_group_id' => $g->id,
            'user_id'         => $user->id,
            'role_in_group'   => 'owner',
            'status'          => 'active',
            'joined_at'       => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        return $g;
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $recipe = $this->recipe();
        $this->getJson('/api/v1/recipes/' . $recipe->id . '/availability?family_group_id=1')
            ->assertStatus(401);
    }

    public function test_receta_inexistente_retorna_404()
    {
        $user  = factory(User::class)->create();
        $group = $this->familyGroup($user);
        $this->actingAs($user)->getJson('/api/v1/recipes/99999/availability?family_group_id=' . $group->id)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'RECIPE_NOT_FOUND');
    }

    public function test_grupo_ajeno_retorna_403()
    {
        $owner  = factory(User::class)->create();
        $other  = factory(User::class)->create();
        $group  = $this->familyGroup($owner);
        $recipe = $this->recipe();

        $this->actingAs($other)->getJson('/api/v1/recipes/' . $recipe->id . '/availability?family_group_id=' . $group->id)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FAMILY_GROUP_ACCESS_DENIED');
    }

    public function test_receta_posible_con_stock_suficiente()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 2]);

        $ing  = $this->ingredient($grams);
        $prod = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 200.0); // 200g total (100/serving)
        $this->stockItem($group, $prod, $grams, 300.0);     // 300g available

        $this->actingAs($user)->getJson('/api/v1/recipes/' . $recipe->id . '/availability?family_group_id=' . $group->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'possible')
            ->assertJsonPath('data.max_possible_servings', 3); // floor(300g / 100g_per_serving)
    }

    public function test_receta_casi_posible_por_stock_insuficiente()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 4]);

        $ing  = $this->ingredient($grams);
        $prod = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 400.0); // 400g total (100/serving)
        $this->stockItem($group, $prod, $grams, 200.0);     // 200g = 2 servings

        $this->actingAs($user)->getJson('/api/v1/recipes/' . $recipe->id . '/availability?family_group_id=' . $group->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'almost_possible')
            ->assertJsonPath('data.max_possible_servings', 2)
            ->assertJsonPath('data.suggested_servings', 2);
    }

    public function test_receta_no_posible_sin_stock()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 2]);

        $ing = $this->ingredient($grams);
        $this->addIngredient($recipe, $ing, $grams, 200.0);
        // no stock

        $this->actingAs($user)->getJson('/api/v1/recipes/' . $recipe->id . '/availability?family_group_id=' . $group->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'not_possible')
            ->assertJsonPath('data.max_possible_servings', 0);
    }

    public function test_conversion_de_unidades_en_disponibilidad()
    {
        $user  = factory(User::class)->create();
        $group = $this->familyGroup($user);
        $grams = $this->unit('g');
        $kg    = $this->unit('kg');

        UnitConversion::create([
            'from_unit_id' => $kg->id,
            'to_unit_id'   => $grams->id,
            'factor'       => 1000,
            'status'       => 'active',
        ]);

        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 500.0); // need 500g
        $this->stockItem($group, $prod, $kg, 1.0);           // 1 kg = 1000g

        $this->actingAs($user)->getJson('/api/v1/recipes/' . $recipe->id . '/availability?family_group_id=' . $group->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'possible');
    }

    public function test_acumula_stock_de_multiples_items()
    {
        $user  = factory(User::class)->create();
        $group = $this->familyGroup($user);
        $grams = $this->unit('g');

        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 300.0); // need 300g

        $this->stockItem($group, $prod, $grams, 100.0); // 100g
        $this->stockItem($group, $prod, $grams, 150.0); // 150g
        $this->stockItem($group, $prod, $grams, 80.0);  // 80g -> total 330g

        $this->actingAs($user)->getJson('/api/v1/recipes/' . $recipe->id . '/availability?family_group_id=' . $group->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'possible');
    }

    public function test_missing_ingredients_devuelve_solo_faltantes()
    {
        $user  = factory(User::class)->create();
        $group = $this->familyGroup($user);
        $grams = $this->unit('g');

        $recipe = $this->recipe(['servings' => 1]);

        $ing1  = $this->ingredient($grams);
        $prod1 = $this->product($ing1, $grams);
        $this->addIngredient($recipe, $ing1, $grams, 100.0);
        $this->stockItem($group, $prod1, $grams, 200.0); // covered

        $ing2 = $this->ingredient($grams);
        $this->addIngredient($recipe, $ing2, $grams, 100.0); // no stock

        $response = $this->actingAs($user)->getJson('/api/v1/recipes/' . $recipe->id . '/missing-ingredients?family_group_id=' . $group->id);

        $response->assertStatus(200);
        $missing = $response->json('data.missing');
        $this->assertCount(1, $missing);
        $this->assertEquals($ing2->id, $missing[0]['ingredient_id']);
    }

    public function test_porcion_menor_sugerida_cuando_hay_stock_parcial()
    {
        $user  = factory(User::class)->create();
        $group = $this->familyGroup($user);
        $grams = $this->unit('g');

        $recipe = $this->recipe(['servings' => 4]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 400.0); // 100g/serving
        $this->stockItem($group, $prod, $grams, 150.0);     // 1 serving possible

        $response = $this->actingAs($user)->getJson('/api/v1/recipes/' . $recipe->id . '/availability?family_group_id=' . $group->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'almost_possible')
            ->assertJsonPath('data.suggested_servings', 1);
    }
}
