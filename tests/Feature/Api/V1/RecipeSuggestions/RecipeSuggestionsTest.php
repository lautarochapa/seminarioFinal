<?php

namespace Tests\Feature\Api\V1\RecipeSuggestions;

use App\Budget;
use App\FamilyGroup;
use App\Ingredient;
use App\Product;
use App\Recipe;
use App\RecipeCostSnapshot;
use App\RecipeIngredient;
use App\StockItem;
use App\UnitConversion;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecipeSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    private function unit(string $code = 'g'): UnitMeasure
    {
        return UnitMeasure::firstOrCreate(['code' => $code], [
            'name' => $code, 'type' => 'weight', 'symbol' => $code, 'status' => 'active',
        ]);
    }

    private function recipe(array $overrides = []): Recipe
    {
        $name = $overrides['name'] ?? ('Receta ' . uniqid());
        return Recipe::create(array_merge([
            'name'            => $name,
            'nombre'          => $name,
            'normalized_name' => mb_strtolower($name),
            'descripcion'     => '', 'tiempo' => '', 'img' => '', 'video' => '', 'porcion' => '',
            'calorias'        => 0,
            'source_type'     => 'user',
            'status'          => 'active',
            'is_public'       => true,
            'is_official'     => false,
            'is_verified'     => false,
            'servings'        => 2,
        ], $overrides));
    }

    private function ingredient(UnitMeasure $unit): Ingredient
    {
        $name = 'Ing ' . uniqid();
        return Ingredient::create([
            'name'            => $name,
            'normalized_name' => mb_strtolower($name),
            'base_unit_id'    => $unit->id,
            'is_generic'      => true,
            'is_preparation'  => false,
            'is_supplement'   => false,
            'status'          => 'active',
        ]);
    }

    private function product(Ingredient $ing, UnitMeasure $unit): Product
    {
        $name = 'Prod ' . uniqid();
        return Product::create([
            'name'            => $name,
            'normalized_name' => mb_strtolower($name),
            'ingredient_id'   => $ing->id,
            'package_unit_id' => $unit->id,
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

    private function addIngredient(Recipe $recipe, Ingredient $ing, UnitMeasure $unit, float $qty): void
    {
        RecipeIngredient::create([
            'recipe_id'     => $recipe->id,
            'ingredient_id' => $ing->id,
            'unit_id'       => $unit->id,
            'quantity'      => $qty,
            'is_optional'   => false,
            'sort_order'    => 0,
        ]);
    }

    private function stockItem(FamilyGroup $group, Product $prod, UnitMeasure $unit, float $qty, ?string $expiresAt = null): StockItem
    {
        return StockItem::create([
            'family_group_id'  => $group->id,
            'product_id'       => $prod->id,
            'unit_id'          => $unit->id,
            'quantity'         => $qty,
            'is_open'          => false,
            'status'           => 'active',
            'expiration_date'  => $expiresAt,
        ]);
    }

    private function familyGroup(User $user): FamilyGroup
    {
        $g = FamilyGroup::create([
            'name' => 'Grupo ' . uniqid(), 'owner_user_id' => $user->id, 'status' => 'active',
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

    public function test_suggestions_sin_autenticacion_retorna_401()
    {
        $this->getJson('/api/v1/recipes/suggestions')->assertStatus(401);
    }

    public function test_grupo_ajeno_retorna_403()
    {
        $owner = factory(User::class)->create();
        $other = factory(User::class)->create();
        $group = $this->familyGroup($owner);

        $this->actingAs($other)->getJson('/api/v1/family-groups/' . $group->id . '/recipes/available')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FAMILY_GROUP_ACCESS_DENIED');
    }

    public function test_grupo_inexistente_retorna_404()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user)->getJson('/api/v1/family-groups/99999/recipes/available')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'FAMILY_GROUP_NOT_FOUND');
    }

    public function test_available_retorna_recetas_con_stock_completo()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 100.0);
        $this->stockItem($group, $prod, $grams, 200.0);

        $response = $this->actingAs($user)->getJson('/api/v1/family-groups/' . $group->id . '/recipes/available');
        $response->assertStatus(200);
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($recipe->id, $ids);
    }

    public function test_available_excluye_recetas_sin_stock()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $this->addIngredient($recipe, $ing, $grams, 100.0);
        // no stock

        $response = $this->actingAs($user)->getJson('/api/v1/family-groups/' . $group->id . '/recipes/available');
        $response->assertStatus(200);
        $ids = array_column($response->json('data'), 'id');
        $this->assertNotContains($recipe->id, $ids);
    }

    public function test_almost_available_retorna_con_stock_insuficiente()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 4]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 400.0); // 100/serving
        $this->stockItem($group, $prod, $grams, 200.0);     // solo 2 porciones

        $response = $this->actingAs($user)->getJson('/api/v1/family-groups/' . $group->id . '/recipes/almost-available');
        $response->assertStatus(200);
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($recipe->id, $ids);
    }

    public function test_by_expiring_stock_incluye_receta_con_ingrediente_proximo()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 50.0);
        $this->stockItem($group, $prod, $grams, 100.0, now()->addDays(3)->toDateString());

        $response = $this->actingAs($user)->getJson('/api/v1/family-groups/' . $group->id . '/recipes/by-expiring-stock?days=7');
        $response->assertStatus(200);
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($recipe->id, $ids);
    }

    public function test_by_budget_filtra_por_costo_maximo()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $cheap  = $this->recipe(['name' => 'Barata']);
        $costly = $this->recipe(['name' => 'Cara']);

        RecipeCostSnapshot::create(['recipe_id' => $cheap->id,  'estimated_total_cost' => 50,  'calculated_at' => now()]);
        RecipeCostSnapshot::create(['recipe_id' => $costly->id, 'estimated_total_cost' => 500, 'calculated_at' => now()]);

        $response = $this->actingAs($user)->getJson('/api/v1/family-groups/' . $group->id . '/recipes/by-budget?max_cost=100');
        $response->assertStatus(200);
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($cheap->id, $ids);
        $this->assertNotContains($costly->id, $ids);
    }

    public function test_by_objectives_retorna_200()
    {
        $user  = factory(User::class)->create();
        $group = $this->familyGroup($user);
        $this->recipe();

        $this->actingAs($user)->getJson('/api/v1/family-groups/' . $group->id . '/recipes/by-objectives')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_suggestions_devuelve_score_y_reasons()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['is_official' => true, 'servings' => 1]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 50.0);
        $this->stockItem($group, $prod, $grams, 100.0);

        $response = $this->actingAs($user)->getJson('/api/v1/recipes/suggestions?family_group_id=' . $group->id);
        $response->assertStatus(200);
        $first = collect($response->json('data'))->firstWhere('id', $recipe->id);
        $this->assertNotNull($first);
        $this->assertArrayHasKey('score', $first);
        $this->assertArrayHasKey('reasons', $first);
    }

    public function test_paginacion_en_available()
    {
        $user  = factory(User::class)->create();
        $group = $this->familyGroup($user);
        $grams = $this->unit('g');

        for ($i = 0; $i < 5; $i++) {
            $recipe = $this->recipe(['servings' => 1]);
            $ing    = $this->ingredient($grams);
            $prod   = $this->product($ing, $grams);
            $this->addIngredient($recipe, $ing, $grams, 10.0);
            $this->stockItem($group, $prod, $grams, 100.0);
        }

        $this->actingAs($user)->getJson('/api/v1/family-groups/' . $group->id . '/recipes/available?per_page=2&page=1')
            ->assertStatus(200)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_solo_muestra_recetas_visibles()
    {
        $user   = factory(User::class)->create();
        $other  = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');

        $hidden = $this->recipe(['is_public' => false, 'owner_user_id' => $other->id]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $grams);
        $this->addIngredient($hidden, $ing, $grams, 10.0);
        $this->stockItem($group, $prod, $grams, 100.0);

        $response = $this->actingAs($user)->getJson('/api/v1/family-groups/' . $group->id . '/recipes/available');
        $response->assertStatus(200);
        $ids = array_column($response->json('data'), 'id');
        $this->assertNotContains($hidden->id, $ids);
    }
}
