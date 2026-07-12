<?php

namespace Tests\Feature\Api\V1\RecipeSearch;

use App\Ingredient;
use App\Recipe;
use App\RecipeCategory;
use App\RecipeIngredient;
use App\RecipeTag;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecipeSearchTest extends TestCase
{
    use RefreshDatabase;

    private UnitMeasure $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unit = UnitMeasure::firstOrCreate(['code' => 'g'], [
            'name' => 'Gramo', 'type' => 'weight', 'symbol' => 'g', 'status' => 'active',
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
        ], $overrides));
    }

    private function ingredient(): Ingredient
    {
        $name = 'Ing ' . uniqid();
        return Ingredient::create([
            'name'            => $name,
            'normalized_name' => mb_strtolower($name),
            'base_unit_id'    => $this->unit->id,
            'is_generic'      => true,
            'is_preparation'  => false,
            'is_supplement'   => false,
            'status'          => 'active',
        ]);
    }

    private function addIngredient(Recipe $recipe, Ingredient $ingredient): void
    {
        RecipeIngredient::create([
            'recipe_id'     => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'unit_id'       => $this->unit->id,
            'quantity'      => 100,
            'is_optional'   => false,
            'sort_order'    => 0,
        ]);
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $this->getJson('/api/v1/recipes/search')->assertStatus(401);
    }

    public function test_parametro_invalido_retorna_422()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user)->getJson('/api/v1/recipes/search?difficulty=invalid')
            ->assertStatus(422);
    }

    public function test_busqueda_por_nombre_retorna_coincidencias()
    {
        $user = factory(User::class)->create();
        $this->recipe(['name' => 'Pasta Carbonara']);
        $this->recipe(['name' => 'Ensalada Cesar']);

        $this->actingAs($user)->getJson('/api/v1/recipes/search?search=Pasta')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Pasta Carbonara');
    }

    public function test_busqueda_por_ingrediente()
    {
        $user = factory(User::class)->create();
        $ing  = $this->ingredient();

        $recipeConIng = $this->recipe(['name' => 'Con ingrediente']);
        $this->addIngredient($recipeConIng, $ing);
        $this->recipe(['name' => 'Sin ingrediente']);

        $this->actingAs($user)->getJson('/api/v1/recipes/search?ingredient_id=' . $ing->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Con ingrediente');
    }

    public function test_busqueda_por_categoria()
    {
        $user = factory(User::class)->create();
        $cat  = RecipeCategory::create(['name' => 'Pastas', 'status' => 'active']);

        $this->recipe(['name' => 'Con categoria', 'category_id' => $cat->id]);
        $this->recipe(['name' => 'Sin categoria']);

        $this->actingAs($user)->getJson('/api/v1/recipes/search?category_id=' . $cat->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_busqueda_por_tag()
    {
        $user = factory(User::class)->create();
        $tag  = RecipeTag::create(['code' => 'vegano', 'name' => 'Vegano', 'status' => 'active']);

        $recipeConTag = $this->recipe(['name' => 'Con tag']);
        DB::table('recipe_tag_pivot')->insert(['recipe_id' => $recipeConTag->id, 'recipe_tag_id' => $tag->id, 'created_at' => now()]);
        $this->recipe(['name' => 'Sin tag']);

        $this->actingAs($user)->getJson('/api/v1/recipes/search?tag_code=vegano')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Con tag');
    }

    public function test_filtro_por_dificultad()
    {
        $user = factory(User::class)->create();
        $this->recipe(['name' => 'Facil', 'difficulty' => 'easy']);
        $this->recipe(['name' => 'Dificil', 'difficulty' => 'hard']);

        $this->actingAs($user)->getJson('/api/v1/recipes/search?difficulty=easy')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Facil');
    }

    public function test_filtro_por_tiempo_total()
    {
        $user = factory(User::class)->create();
        $this->recipe(['name' => 'Rapida', 'prep_time_minutes' => 10, 'cook_time_minutes' => 10]);
        $this->recipe(['name' => 'Lenta',  'prep_time_minutes' => 60, 'cook_time_minutes' => 60]);

        $this->actingAs($user)->getJson('/api/v1/recipes/search?max_total_time=30')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Rapida');
    }

    public function test_exclusion_por_ingrediente()
    {
        $user = factory(User::class)->create();
        $ing  = $this->ingredient();

        $recipeConIng = $this->recipe(['name' => 'Con alergeno']);
        $this->addIngredient($recipeConIng, $ing);
        $this->recipe(['name' => 'Segura']);

        $this->actingAs($user)->getJson('/api/v1/recipes/search?exclude_ingredient_id=' . $ing->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Segura');
    }

    public function test_solo_muestra_recetas_publicas_o_propias()
    {
        $user  = factory(User::class)->create();
        $other = factory(User::class)->create();

        $this->recipe(['name' => 'Publica', 'is_public' => true]);
        $this->recipe(['name' => 'Propia',  'is_public' => false, 'owner_user_id' => $user->id]);
        $this->recipe(['name' => 'Ajena',   'is_public' => false, 'owner_user_id' => $other->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/recipes/search');
        $response->assertStatus(200);
        $names = array_column($response->json('data'), 'name');
        $this->assertContains('Publica', $names);
        $this->assertContains('Propia', $names);
        $this->assertNotContains('Ajena', $names);
    }

    public function test_resultados_vacios_cuando_no_hay_coincidencias()
    {
        $user = factory(User::class)->create();
        $this->recipe(['name' => 'Pasta']);

        $this->actingAs($user)->getJson('/api/v1/recipes/search?search=Sushi')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }
}
