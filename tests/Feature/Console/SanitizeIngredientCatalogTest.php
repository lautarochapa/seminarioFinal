<?php

namespace Tests\Feature\Console;

use App\ImportedRecipeCandidate;
use App\Ingredient;
use App\Product;
use App\Recipe;
use App\RecipeIngredient;
use App\Services\RecipeImportCandidates\IngredientMatchService;
use App\UnitMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanitizeIngredientCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function ingredient(array $data = []): Ingredient
    {
        $name = $data['name'] ?? ('Ingrediente ' . uniqid());

        return Ingredient::create(array_merge([
            'name'            => $name,
            'normalized_name' => mb_strtolower($name, 'UTF-8'),
            'status'          => 'active',
        ], $data));
    }

    private function unit(string $code = 'g', string $symbol = 'g'): UnitMeasure
    {
        return UnitMeasure::firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'type' => 'generic', 'symbol' => $symbol, 'status' => 'active']
        );
    }

    private function recipe(array $data = []): Recipe
    {
        $name = $data['name'] ?? ('Receta ' . uniqid());

        return Recipe::create(array_merge([
            'name'            => $name,
            'nombre'          => $name,
            'normalized_name' => mb_strtolower($name, 'UTF-8'),
            'descripcion'     => '',
            'tiempo'          => '',
            'img'             => '',
            'video'           => '',
            'porcion'         => '',
            'calorias'        => 0,
            'source_type'     => 'user',
            'status'          => 'active',
            'is_public'       => false,
            'is_official'     => false,
            'is_verified'     => false,
        ], $data));
    }

    private function product(?int $ingredientId = null): Product
    {
        $name = 'Producto ' . uniqid();

        return Product::create([
            'nombre'          => $name,
            'name'            => $name,
            'normalized_name' => mb_strtolower($name, 'UTF-8'),
            'brand_id'        => 0,
            'ingredient_id'   => $ingredientId,
            'codigo'          => 'COD-' . uniqid(),
            'img'             => '',
            'habilitado'      => 1,
            'supply_id'       => 0,
            'is_active'       => true,
            'status'          => 'active',
        ]);
    }

    public function test_add_crea_ingrediente_nuevo_con_normalized_name_correcto()
    {
        $this->unit('g', 'g');

        $this->artisan('ingredients:sanitize-catalog', ['--apply' => true, '--add' => ['Zanahoria']])
            ->assertExitCode(0);

        $this->assertDatabaseHas('ingredients', [
            'name'            => 'Zanahoria',
            'normalized_name' => 'zanahoria',
            'status'          => 'active',
        ]);
    }

    public function test_add_preserva_acentos_y_espacios_no_usa_guion_bajo()
    {
        // IngredientService::normalizeName() (contrato ya testeado en
        // IngredientsTest) produce guion bajo y rompe acentos; el comando
        // debe evitarlo para que el matcher (que compara con espacios)
        // pueda encontrar el ingrediente despues.
        $this->unit('g', 'g');

        $this->artisan('ingredients:sanitize-catalog', ['--apply' => true, '--add' => ['Champiñones']])
            ->assertExitCode(0);

        $this->assertDatabaseHas('ingredients', [
            'name'            => 'Champiñones',
            'normalized_name' => 'champiñones',
        ]);
        $this->assertDatabaseMissing('ingredients', ['normalized_name' => 'champi_ones']);
    }

    public function test_add_no_duplica_ingrediente_existente_case_insensitive()
    {
        $this->unit('g', 'g');
        $this->ingredient(['name' => 'zanahoria', 'normalized_name' => 'zanahoria']);

        $this->artisan('ingredients:sanitize-catalog', ['--apply' => true, '--add' => ['Zanahoria']])
            ->assertExitCode(0);

        $this->assertEquals(1, Ingredient::where('normalized_name', 'zanahoria')->where('status', 'active')->count());
    }

    public function test_add_es_idempotente_al_ejecutar_dos_veces()
    {
        $this->unit('g', 'g');

        $this->artisan('ingredients:sanitize-catalog', ['--apply' => true, '--add' => ['Pimiento', 'Laurel']])
            ->assertExitCode(0);
        $this->artisan('ingredients:sanitize-catalog', ['--apply' => true, '--add' => ['Pimiento', 'Laurel']])
            ->assertExitCode(0);

        $this->assertEquals(1, Ingredient::where('normalized_name', 'pimiento')->count());
        $this->assertEquals(1, Ingredient::where('normalized_name', 'laurel')->count());
    }

    public function test_dry_run_sin_apply_no_modifica_nada()
    {
        $this->unit('g', 'g');

        $this->artisan('ingredients:sanitize-catalog', ['--add' => ['Pimiento']])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('ingredients', ['normalized_name' => 'pimiento']);
    }

    public function test_consolidacion_migra_recipe_ingredient_y_product_sin_romper_fk()
    {
        $canonical = $this->ingredient(['name' => 'Pechuga de pollo', 'normalized_name' => 'pechuga de pollo']);
        $duplicate = $this->ingredient(['name' => 'Pechuga de pollo', 'normalized_name' => 'pechuga pollo']);
        $unit = $this->unit();
        $recipe = $this->recipe();
        $recipeIngredient = RecipeIngredient::create([
            'recipe_id'     => $recipe->id,
            'ingredient_id' => $duplicate->id,
            'unit_id'       => $unit->id,
            'quantity'      => 1,
            'is_optional'   => false,
            'sort_order'    => 1,
        ]);
        $product = $this->product($duplicate->id);

        $this->artisan('ingredients:sanitize-catalog', [
            '--apply' => true,
            '--consolidate' => [$duplicate->id . ':' . $canonical->id],
        ])->assertExitCode(0);

        $this->assertDatabaseHas('recipe_ingredients', ['id' => $recipeIngredient->id, 'ingredient_id' => $canonical->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'ingredient_id' => $canonical->id]);
    }

    public function test_consolidacion_desactiva_el_duplicado_sin_borrarlo_fisicamente()
    {
        $canonical = $this->ingredient(['name' => 'Pechuga de pollo', 'normalized_name' => 'pechuga de pollo']);
        $duplicate = $this->ingredient(['name' => 'Pechuga de pollo', 'normalized_name' => 'pechuga pollo']);

        $this->artisan('ingredients:sanitize-catalog', [
            '--apply' => true,
            '--consolidate' => [$duplicate->id . ':' . $canonical->id],
        ])->assertExitCode(0);

        $this->assertDatabaseHas('ingredients', ['id' => $duplicate->id, 'status' => 'inactive']);
        // sigue existiendo la fila (no fisicamente borrada), solo soft-deleted.
        $this->assertNotNull(Ingredient::withTrashed()->find($duplicate->id));
        $this->assertDatabaseHas('ingredients', ['id' => $canonical->id, 'status' => 'active']);
    }

    public function test_matcher_pechuga_queda_inequivoco_tras_consolidar_duplicado()
    {
        $canonical = $this->ingredient(['name' => 'Pechuga de pollo', 'normalized_name' => 'pechuga de pollo']);
        $duplicate = $this->ingredient(['name' => 'Pechuga de pollo', 'normalized_name' => 'pechuga pollo']);

        $matcher = app(IngredientMatchService::class);
        $before = $matcher->matchText('1 pechuga cortada en cubos');
        $this->assertNull($before['suggested_ingredient_id'], 'Con el duplicado activo, la ambiguedad debe dejarlo unresolved.');

        $this->artisan('ingredients:sanitize-catalog', [
            '--apply' => true,
            '--consolidate' => [$duplicate->id . ':' . $canonical->id],
        ])->assertExitCode(0);

        $after = $matcher->matchText('1 pechuga cortada en cubos');
        $this->assertEquals($canonical->id, $after['suggested_ingredient_id']);
        $this->assertEquals('conservative_prefix', $after['confidence']);
    }

    public function test_zanahoria_zapallito_champinones_resuelven_despues_de_sembrar_catalogo()
    {
        $this->unit('g', 'g');

        $this->artisan('ingredients:sanitize-catalog', [
            '--apply' => true,
            '--add'   => ['Zanahoria', 'Zapallito', 'Champiñones'],
        ])->assertExitCode(0);

        $matcher = app(IngredientMatchService::class);

        $r1 = $matcher->matchText('1 zanahoria rallada');
        $this->assertEquals('Zanahoria', $r1['suggested_ingredient_name']);

        $r2 = $matcher->matchText('1 zapallito rallado');
        $this->assertEquals('Zapallito', $r2['suggested_ingredient_name']);

        $r3 = $matcher->matchText('250 g champiñones');
        $this->assertEquals('Champiñones', $r3['suggested_ingredient_name']);
        $this->assertEquals('exact', $r3['confidence']);
    }

    public function test_recalcular_candidate_9_like_no_crea_ingredients_al_recalcular_ni_aplicar()
    {
        $this->unit('g', 'g');
        $this->artisan('ingredients:sanitize-catalog', [
            '--apply' => true,
            '--add'   => ['Cebolla', 'Zanahoria', 'Zapallito', 'Champiñones', 'Arroz', 'Pechuga de pollo'],
        ])->assertExitCode(0);

        $countBefore = Ingredient::count();

        $candidate = ImportedRecipeCandidate::create([
            'source_url'           => 'https://cookpad.com/ar/recetas/900009',
            'source_site'          => 'cookpad',
            'raw_title'            => 'Arroz con pollo',
            'raw_ingredients_json' => [
                '1 cebolla morada',
                '1 zanahoria rallada',
                '1 zapallito rallado',
                '250 g champiñones',
                '1 pechuga cortada en cubos',
                '3 tacitas café de arroz',
            ],
            'status' => 'parsed',
        ]);

        $user = factory(\App\User::class)->create();
        $role = \App\Role::where('code', 'super_admin')->first();
        \Illuminate\Support\Facades\DB::table('user_roles')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'created_at' => now()]);

        $service = app(\App\Services\RecipeImportCandidates\RecipeImportCandidatesService::class);
        $service->recalculateSuggestions($user, $candidate->id, '127.0.0.1', 'test');
        $service->applySuggestedMappings($user, $candidate->id, '127.0.0.1', 'test');

        $this->assertEquals($countBefore, Ingredient::count());
    }

    public function test_recipe_approval_sigue_bloqueando_igual_tras_sanear_catalogo()
    {
        $this->unit('g', 'g');
        $this->artisan('ingredients:sanitize-catalog', ['--apply' => true, '--add' => ['Champiñones']])
            ->assertExitCode(0);

        $user = factory(\App\User::class)->create();
        $role = \App\Role::where('code', 'super_admin')->first();
        \Illuminate\Support\Facades\DB::table('user_roles')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'created_at' => now()]);

        $candidate = ImportedRecipeCandidate::create([
            'source_url'           => 'https://cookpad.com/ar/recetas/900010',
            'source_site'          => 'cookpad',
            'raw_title'            => 'Test',
            'raw_steps_json'       => [['step_number' => 1, 'description' => 'Cocinar.']],
            'raw_ingredients_json' => ['250 g champiñones', 'laurel'],
            'status'               => 'parsed',
        ]);

        $service = app(\App\Services\RecipeImportCandidates\RecipeImportCandidatesService::class);
        $service->applySuggestedMappings($user, $candidate->id, '127.0.0.1', 'test');

        $this->expectException(\App\Exceptions\RecipeImportCandidates\RecipeImportCandidatesException::class);
        $service->approve($user, $candidate->id, '127.0.0.1', 'test');
    }
}
