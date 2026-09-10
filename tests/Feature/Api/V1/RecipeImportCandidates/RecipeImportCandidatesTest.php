<?php

namespace Tests\Feature\Api\V1\RecipeImportCandidates;

use App\ImportedRecipeCandidate;
use App\Ingredient;
use App\Permission;
use App\RecipeReviewLog;
use App\Role;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeImportCandidatesTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = factory(User::class)->create();
        $role = Role::firstOrCreate(['code' => 'recipe_admin'], ['name' => 'Recipe Admin', 'status' => 'active']);
        $perm = Permission::firstOrCreate(['code' => 'recipes.manage'], [
            'name' => 'Manage Recipes', 'module' => 'recipes', 'action' => 'manage', 'status' => 'active',
        ]);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
        return $user;
    }

    private function parsedCandidate(array $overrides = []): ImportedRecipeCandidate
    {
        return ImportedRecipeCandidate::create(array_merge([
            'source_url'      => 'https://cookpad.com/ar/recetas/99999',
            'source_site'     => 'cookpad',
            'raw_title'       => 'Fideos con salsa',
            'raw_description' => 'Receta clasica de fideos.',
            'raw_steps_json'  => [
                ['step_number' => 1, 'description' => 'Hervir el agua.'],
                ['step_number' => 2, 'description' => 'Cocinar los fideos.'],
            ],
            'status'          => 'parsed',
        ], $overrides));
    }

    private function ingredient(): Ingredient
    {
        return Ingredient::create([
            'name'            => 'Fideos',
            'normalized_name' => 'fideos',
            'status'          => 'active',
        ]);
    }

    private function makeIngredient(string $name): Ingredient
    {
        return Ingredient::create([
            'name'            => $name,
            'normalized_name' => mb_strtolower($name, 'UTF-8'),
            'status'          => 'active',
        ]);
    }

    private function unit(): UnitMeasure
    {
        return UnitMeasure::create([
            'code'   => 'gr',
            'name'   => 'Gramos',
            'type'   => 'weight',
            'symbol' => 'g',
            'status' => 'active',
        ]);
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $this->getJson('/api/v1/admin/recipes/import-candidates')->assertStatus(401);
    }

    public function test_sin_permiso_retorna_403()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user)->getJson('/api/v1/admin/recipes/import-candidates')->assertStatus(403);
    }

    public function test_listar_filtra_por_estado()
    {
        $user = $this->adminUser();

        $this->parsedCandidate(['status' => 'parsed']);
        $this->parsedCandidate(['status' => 'rejected', 'source_url' => 'https://cookpad.com/ar/recetas/11111']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/admin/recipes/import-candidates?status=parsed');

        $response->assertStatus(200);
        $statuses = array_column($response->json('data'), 'status');
        $this->assertNotEmpty($statuses);
        foreach ($statuses as $s) {
            $this->assertEquals('parsed', $s);
        }
    }

    public function test_detalle_incluye_datos_parseados()
    {
        $user      = $this->adminUser();
        $candidate = $this->parsedCandidate();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $candidate->id)
            ->assertJsonPath('data.raw_title', 'Fideos con salsa')
            ->assertJsonPath('data.status', 'parsed');
        $this->assertNotNull($response->json('data.raw_steps_json'));
    }

    public function test_patch_edita_titulo_y_no_acepta_campos_internos()
    {
        $user      = $this->adminUser();
        $candidate = $this->parsedCandidate();

        $response = $this->actingAs($user)
            ->patchJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id, [
                'raw_title'   => 'Fideos al dente',
                'reviewed_by' => 999,
                'status'      => 'approved',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.raw_title', 'Fideos al dente')
            ->assertJsonPath('data.status', 'parsed')
            ->assertJsonPath('data.reviewed_by', null);
    }

    public function test_map_ingredient_guarda_mapeo_en_parsed_json()
    {
        $user       = $this->adminUser();
        $candidate  = $this->parsedCandidate([
            'raw_ingredients_json' => ['200g fideos'],
        ]);
        $ingredient = $this->ingredient();
        $unit       = $this->unit();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/map-ingredient', [
                'ingredient_index' => 0,
                'ingredient_id'    => $ingredient->id,
                'unit_id'          => $unit->id,
                'quantity'         => 200,
            ]);

        $response->assertStatus(200);
        $mappings = $response->json('data.parsed_recipe_json.ingredient_mappings');
        $this->assertNotEmpty($mappings);
        $this->assertEquals($ingredient->id, $mappings[0]['ingredient_id']);
    }

    public function test_approve_sin_titulo_retorna_422()
    {
        $user      = $this->adminUser();
        $candidate = $this->parsedCandidate(['raw_title' => null]);

        $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/approve')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'IMPORT_CANDIDATE_INVALID_FOR_APPROVAL');
    }

    public function test_rechazar_guarda_motivo_en_review_log()
    {
        $user      = $this->adminUser();
        $candidate = $this->parsedCandidate();

        $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/reject', [
                'reason' => 'Receta incompleta, faltan pasos.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('recipe_review_logs', [
            'imported_recipe_candidate_id' => $candidate->id,
            'action'                       => 'rejected',
            'comments'                     => 'Receta incompleta, faltan pasos.',
        ]);
    }

    public function test_crear_receta_desde_candidata_aprobada()
    {
        $user       = $this->adminUser();
        $candidate  = $this->parsedCandidate();
        $ingredient = $this->ingredient();
        $unit       = $this->unit();

        // Map ingredient
        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/map-ingredient', [
            'ingredient_index' => 0,
            'ingredient_id'    => $ingredient->id,
            'unit_id'          => $unit->id,
            'quantity'         => 200,
        ]);

        // Approve (no raw_ingredients_json so no unmapped check fails)
        $candidateNoIngredients = $this->parsedCandidate(['source_url' => 'https://cookpad.com/ar/recetas/77777']);
        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidateNoIngredients->id . '/approve');

        // Create recipe
        $response = $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import-candidates/' . $candidateNoIngredients->id . '/create-recipe', [
                'is_public' => false,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Fideos con salsa');

        $this->assertDatabaseHas('imported_recipe_candidates', [
            'id'     => $candidateNoIngredients->id,
            'status' => 'recipe_created',
        ]);
        $this->assertDatabaseHas('recipes', ['name' => 'Fideos con salsa', 'source_type' => 'imported']);
        $this->assertDatabaseHas('recipe_steps', ['description' => 'Hervir el agua.']);
    }

    public function test_doble_procesamiento_retorna_409()
    {
        $user      = $this->adminUser();
        $candidate = $this->parsedCandidate(['status' => 'rejected']);

        $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/reject', [
                'reason' => 'Ya rechazada.',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'IMPORT_CANDIDATE_ALREADY_FINALIZED');
    }

    public function test_show_sugiere_ingredientes_por_texto_y_deja_null_sin_match()
    {
        $user = $this->adminUser();
        $this->makeIngredient('Huevo');
        $this->makeIngredient('Harina');

        $candidate = $this->parsedCandidate([
            'source_url'           => 'https://cookpad.com/ar/recetas/55501',
            'raw_ingredients_json' => ['2 huevos', '200 g de harina', 'un ingrediente inexistente xyz'],
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id);

        $response->assertStatus(200);
        $suggestions = $response->json('data.ingredient_suggestions');
        $this->assertCount(3, $suggestions);

        $this->assertSame(0, $suggestions[0]['index']);
        $this->assertSame('Huevo', $suggestions[0]['suggested_ingredient_name']);
        $this->assertEquals(2, $suggestions[0]['parsed_quantity']);

        $this->assertSame('Harina', $suggestions[1]['suggested_ingredient_name']);
        $this->assertEquals(200, $suggestions[1]['parsed_quantity']);
        $this->assertSame('g', $suggestions[1]['parsed_unit_text']);

        $this->assertNull($suggestions[2]['suggested_ingredient_id']);
        $this->assertNull($suggestions[2]['confidence']);
    }

    public function test_crear_receta_persiste_servings_y_tiempos_de_la_fuente()
    {
        $user       = $this->adminUser();
        $ingredient = $this->ingredient();
        $unit       = $this->unit();

        $candidate = $this->parsedCandidate([
            'source_url'           => 'https://cookpad.com/ar/recetas/55502',
            'raw_ingredients_json' => ['200 g fideos'],
            'parsed_recipe_json'   => ['servings' => 4, 'prep_time_minutes' => 15, 'cook_time_minutes' => 30],
        ]);

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/map-ingredient', [
            'ingredient_index' => 0,
            'ingredient_id'    => $ingredient->id,
            'unit_id'          => $unit->id,
            'quantity'         => 200,
        ])->assertStatus(200);

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/approve')
            ->assertStatus(200);

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/create-recipe', [
            'is_public' => false,
        ]);

        $response->assertStatus(201);
        $recipeId = $response->json('data.id');

        $this->assertDatabaseHas('recipes', [
            'id'                => $recipeId,
            'servings'          => 4,
            'prep_time_minutes' => 15,
            'cook_time_minutes' => 30,
        ]);
        $this->assertDatabaseHas('recipe_ingredients', [
            'recipe_id'     => $recipeId,
            'ingredient_id' => $ingredient->id,
            'unit_id'       => $unit->id,
            'quantity'      => 200,
        ]);
    }

    public function test_crear_receta_con_titulo_utf8()
    {
        $user = $this->adminUser();

        $candidate = $this->parsedCandidate([
            'source_url' => 'https://cookpad.com/ar/recetas/55503',
            'raw_title'  => 'Ñoquis de papá con crema y jamón',
        ]);
        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/approve')
            ->assertStatus(200);

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/create-recipe', [
            'is_public' => false,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Ñoquis de papá con crema y jamón');
        $this->assertDatabaseHas('recipes', ['name' => 'Ñoquis de papá con crema y jamón']);
    }

    public function test_crear_receta_duplicada_por_source_url_retorna_409()
    {
        $user = $this->adminUser();

        $first = $this->parsedCandidate(['source_url' => 'https://cookpad.com/ar/recetas/55504']);
        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $first->id . '/approve')->assertStatus(200);
        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $first->id . '/create-recipe')->assertStatus(201);

        $second = $this->parsedCandidate(['source_url' => 'https://cookpad.com/ar/recetas/55504']);
        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $second->id . '/approve')->assertStatus(200);

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $second->id . '/create-recipe')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'IMPORT_CANDIDATE_DUPLICATE_RECIPE');
    }

    public function test_auditoria_al_actualizar_candidata()
    {
        $user      = $this->adminUser();
        $candidate = $this->parsedCandidate();

        $this->actingAs($user)
            ->patchJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id, [
                'raw_title' => 'Fideos napolitanos',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'import_candidate_updated',
            'entity_name' => 'imported_recipe_candidates',
            'entity_id'   => $candidate->id,
        ]);
    }
}
