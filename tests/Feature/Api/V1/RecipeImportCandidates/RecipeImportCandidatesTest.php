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

    private function unitByCode(string $code, string $name, string $symbol): UnitMeasure
    {
        return UnitMeasure::create([
            'code'   => $code,
            'name'   => $name,
            'type'   => 'generic',
            'symbol' => $symbol,
            'status' => 'active',
        ]);
    }

    /**
     * Mapeo automatico conservador de ingredientes (matchText -> lookup):
     * ejemplos reales de recetas Cookpad tomados del reporte de la tarea.
     */
    public function test_show_sugiere_cebolla_morada_como_cebolla_por_prefijo()
    {
        $user = $this->adminUser();
        $cebolla = $this->makeIngredient('Cebolla');
        $candidate = $this->parsedCandidate(['raw_ingredients_json' => ['1 cebolla morada']]);

        $response = $this->actingAs($user)->getJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.ingredient_suggestions.0.suggested_ingredient_id', $cebolla->id)
            ->assertJsonPath('data.ingredient_suggestions.0.confidence', 'conservative_prefix');
    }

    public function test_show_sugiere_champinones_exacto_con_cantidad_y_unidad_parseada()
    {
        $user = $this->adminUser();
        $champi = $this->makeIngredient('Champiñones');
        $candidate = $this->parsedCandidate(['raw_ingredients_json' => ['250 g champiñones']]);

        $response = $this->actingAs($user)->getJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.ingredient_suggestions.0.suggested_ingredient_id', $champi->id)
            ->assertJsonPath('data.ingredient_suggestions.0.confidence', 'exact')
            ->assertJsonPath('data.ingredient_suggestions.0.parsed_quantity', 250)
            ->assertJsonPath('data.ingredient_suggestions.0.parsed_unit_text', 'g');
    }

    public function test_show_sugiere_arroz_desde_tacitas_de_cafe_de_arroz()
    {
        $user = $this->adminUser();
        $arroz = $this->makeIngredient('Arroz');
        $candidate = $this->parsedCandidate(['raw_ingredients_json' => ['3 tacitas café de arroz']]);

        $response = $this->actingAs($user)->getJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.ingredient_suggestions.0.suggested_ingredient_id', $arroz->id)
            ->assertJsonPath('data.ingredient_suggestions.0.confidence', 'exact');
    }

    public function test_show_sugiere_zanahoria_y_zapallito_pese_al_modificador_rallado()
    {
        $user = $this->adminUser();
        $zanahoria = $this->makeIngredient('Zanahoria');
        $zapallito = $this->makeIngredient('Zapallito');
        $candidate = $this->parsedCandidate(['raw_ingredients_json' => ['1 zanahoria rallada', '1 zapallito rallado']]);

        $response = $this->actingAs($user)->getJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.ingredient_suggestions.0.suggested_ingredient_id', $zanahoria->id)
            ->assertJsonPath('data.ingredient_suggestions.1.suggested_ingredient_id', $zapallito->id);
    }

    public function test_show_sugiere_pechuga_de_pollo_cuando_es_inequivoco_en_el_catalogo()
    {
        $user = $this->adminUser();
        $pechuga = $this->makeIngredient('Pechuga de pollo');
        $candidate = $this->parsedCandidate(['raw_ingredients_json' => ['1 pechuga cortada en cubos']]);

        $response = $this->actingAs($user)->getJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.ingredient_suggestions.0.suggested_ingredient_id', $pechuga->id)
            ->assertJsonPath('data.ingredient_suggestions.0.confidence', 'conservative_prefix');
    }

    public function test_show_no_sugiere_pechuga_cuando_el_catalogo_es_ambiguo()
    {
        $user = $this->adminUser();
        $this->makeIngredient('Pechuga de pollo');
        $this->makeIngredient('Pechuga de pavo');
        $candidate = $this->parsedCandidate(['raw_ingredients_json' => ['1 pechuga cortada en cubos']]);

        $response = $this->actingAs($user)->getJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.ingredient_suggestions.0.suggested_ingredient_id', null);
    }

    public function test_show_no_mapea_galletitas_de_arroz_a_arroz()
    {
        $user = $this->adminUser();
        $this->makeIngredient('Arroz');
        $candidate = $this->parsedCandidate(['raw_ingredients_json' => ['galletitas de arroz']]);

        $response = $this->actingAs($user)->getJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.ingredient_suggestions.0.suggested_ingredient_id', null);
    }

    public function test_show_no_mapea_aceite_de_oliva_a_oliva()
    {
        $user = $this->adminUser();
        $this->makeIngredient('Oliva');
        $candidate = $this->parsedCandidate(['raw_ingredients_json' => ['aceite de oliva']]);

        $response = $this->actingAs($user)->getJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.ingredient_suggestions.0.suggested_ingredient_id', null);
    }

    public function test_show_expone_mapping_summary_con_ingredientes_sin_mapear()
    {
        $user = $this->adminUser();
        $this->makeIngredient('Cebolla');
        $candidate = $this->parsedCandidate(['raw_ingredients_json' => ['1 cebolla morada', 'laurel']]);

        $response = $this->actingAs($user)->getJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.mapping_summary.total_count', 2)
            ->assertJsonPath('data.mapping_summary.mapped_count', 0)
            ->assertJsonPath('data.mapping_summary.unmapped_count', 2)
            ->assertJsonPath('data.mapping_summary.mapping_ready', false);
    }

    public function test_listado_expone_mapping_summary_por_fila()
    {
        $user = $this->adminUser();
        $this->parsedCandidate(['raw_ingredients_json' => ['1 cebolla morada']]);

        $response = $this->actingAs($user)->getJson('/api/v1/admin/recipes/import-candidates');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['mapping_summary' => ['total_count', 'mapped_count', 'unmapped_count', 'mapping_ready']]]]);
    }

    public function test_recalcular_sugerencias_persiste_nuevas_sugerencias()
    {
        $user = $this->adminUser();
        $candidate = $this->parsedCandidate(['raw_ingredients_json' => ['1 cebolla morada']]);

        // Sin "Cebolla" todavia: no hay sugerencia.
        $this->actingAs($user)->getJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id)
            ->assertJsonPath('data.ingredient_suggestions.0.suggested_ingredient_id', null);

        $cebolla = $this->makeIngredient('Cebolla');

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/recalculate-suggestions');

        $response->assertStatus(200)
            ->assertJsonPath('data.ingredient_suggestions.0.suggested_ingredient_id', $cebolla->id);

        $candidate->refresh();
        $this->assertEquals($cebolla->id, $candidate->parsed_recipe_json['ingredient_suggestions'][0]['suggested_ingredient_id']);
    }

    public function test_recalcular_sugerencias_no_pisa_mapeo_manual_existente()
    {
        $user = $this->adminUser();
        $cebolla = $this->makeIngredient('Cebolla');
        $morada = $this->makeIngredient('Cebolla morada especial');
        $unit = $this->unitByCode('unit', 'Unidad', 'u');
        $candidate = $this->parsedCandidate(['raw_ingredients_json' => ['1 cebolla morada']]);

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/map-ingredient', [
            'ingredient_index' => 0,
            'ingredient_id'    => $morada->id,
            'unit_id'          => $unit->id,
            'quantity'         => 1,
        ])->assertStatus(200);

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/recalculate-suggestions')
            ->assertStatus(200);

        $candidate->refresh();
        $mappings = $candidate->parsed_recipe_json['ingredient_mappings'];
        $this->assertEquals($morada->id, $mappings[0]['ingredient_id']);
    }

    public function test_recalcular_sugerencias_bulk_omite_finalizadas()
    {
        $user = $this->adminUser();
        $pending = $this->parsedCandidate(['raw_ingredients_json' => ['1 cebolla morada']]);
        $rejected = $this->parsedCandidate([
            'status'               => 'rejected',
            'source_url'           => 'https://cookpad.com/ar/recetas/70001',
            'raw_ingredients_json' => ['1 cebolla morada'],
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/recalculate-suggestions-bulk', [
            'candidate_ids' => [$pending->id, $rejected->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('requested', 2)
            ->assertJsonPath('recalculated', 1)
            ->assertJsonPath('skipped', 1);
    }

    public function test_recalcular_sugerencias_bulk_rechaza_mas_de_100()
    {
        $user = $this->adminUser();
        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/recalculate-suggestions-bulk', [
            'candidate_ids' => range(1, 101),
        ])->assertStatus(422);
    }

    public function test_aplicar_sugerencias_mapea_ingrediente_confiado_con_unidad_resoluble()
    {
        $user = $this->adminUser();
        $champi = $this->makeIngredient('Champiñones');
        $this->unitByCode('g', 'Gramo', 'g');
        $candidate = $this->parsedCandidate(['raw_ingredients_json' => ['250 g champiñones']]);

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/apply-suggestions');

        $response->assertStatus(200)
            ->assertJsonPath('applied', 1)
            ->assertJsonPath('skipped_unresolved', 0)
            ->assertJsonPath('data.mapping_summary.mapped_count', 1);

        $candidate->refresh();
        $mapping = $candidate->parsed_recipe_json['ingredient_mappings'][0];
        $this->assertEquals($champi->id, $mapping['ingredient_id']);
        $this->assertEquals(250, $mapping['quantity']);
    }

    public function test_aplicar_sugerencias_no_aplica_sin_unidad_resoluble()
    {
        $user = $this->adminUser();
        $this->makeIngredient('Laurel');
        // "laurel" no trae unidad detectable (no hay cantidad/unidad en el texto).
        $candidate = $this->parsedCandidate(['raw_ingredients_json' => ['laurel']]);

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/apply-suggestions');

        $response->assertStatus(200)
            ->assertJsonPath('applied', 0)
            ->assertJsonPath('skipped_no_unit', 1);

        $candidate->refresh();
        $this->assertArrayNotHasKey('ingredient_mappings', $candidate->parsed_recipe_json ?? []);
    }

    public function test_aplicar_sugerencias_no_aplica_ingredientes_sin_resolver()
    {
        $user = $this->adminUser();
        // "laurel" y "azafran" sin ingrediente en catalogo -> unresolved.
        $candidate = $this->parsedCandidate(['raw_ingredients_json' => ['laurel', 'azafran']]);

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/apply-suggestions');

        $response->assertStatus(200)
            ->assertJsonPath('applied', 0)
            ->assertJsonPath('skipped_unresolved', 2);
    }

    public function test_aplicar_sugerencias_no_pisa_mapeo_manual_ya_existente()
    {
        $user = $this->adminUser();
        $this->makeIngredient('Champiñones');
        $otro = $this->makeIngredient('Otro ingrediente manual');
        $gramo = $this->unitByCode('g', 'Gramo', 'g');
        $candidate = $this->parsedCandidate(['raw_ingredients_json' => ['250 g champiñones']]);

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/map-ingredient', [
            'ingredient_index' => 0,
            'ingredient_id'    => $otro->id,
            'unit_id'          => $gramo->id,
            'quantity'         => 999,
        ])->assertStatus(200);

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/apply-suggestions');

        $response->assertStatus(200)
            ->assertJsonPath('applied', 0)
            ->assertJsonPath('skipped_already_mapped', 1);

        $candidate->refresh();
        $this->assertEquals($otro->id, $candidate->parsed_recipe_json['ingredient_mappings'][0]['ingredient_id']);
    }

    public function test_aplicar_sugerencias_no_crea_ingredients()
    {
        $user = $this->adminUser();
        $countBefore = Ingredient::count();
        $candidate = $this->parsedCandidate(['raw_ingredients_json' => ['1 cebolla morada', '250 g champiñones']]);

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/apply-suggestions')
            ->assertStatus(200);

        $this->assertEquals($countBefore, Ingredient::count());
    }

    public function test_recipe_approval_sigue_bloqueada_si_queda_required_unmapped_tras_aplicar_sugerencias()
    {
        $user = $this->adminUser();
        $this->makeIngredient('Champiñones');
        $this->unitByCode('g', 'Gramo', 'g');
        // "laurel" queda sin ingrediente en catalogo -> sigue sin mapear.
        $candidate = $this->parsedCandidate(['raw_ingredients_json' => ['250 g champiñones', 'laurel']]);

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/apply-suggestions')
            ->assertStatus(200)
            ->assertJsonPath('applied', 1);

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/approve')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'IMPORT_CANDIDATE_MISSING_MAPPINGS');
    }

    public function test_candidato_nuevo_de_scraping_recibe_sugerencias_precalculadas()
    {
        $user = $this->adminUser();
        $cebolla = $this->makeIngredient('Cebolla');
        $source = \App\ScrapingSource::create([
            'code' => 'cookpad', 'name' => 'Cookpad Argentina', 'type' => 'web_scraper',
            'base_url' => 'https://cookpad.com/ar', 'is_active' => true, 'status' => 'active',
        ]);
        $job = \App\ScrapingJob::create([
            'source_id' => $source->id, 'job_type' => 'recipe_scraping', 'status' => 'pending',
            'parameters_json' => ['max_pages' => 1],
        ]);

        \Illuminate\Support\Facades\Http::fake([
            '*buscar*' => \Illuminate\Support\Facades\Http::response(
                '<html><body><a href="https://cookpad.com/ar/recetas/900001">R</a></body></html>', 200
            ),
            '*recetas/900001*' => \Illuminate\Support\Facades\Http::response(
                '<html><head><script type="application/ld+json">' . json_encode([
                    '@context' => 'https://schema.org', '@type' => 'Recipe', 'name' => 'Test',
                    'recipeIngredient' => ['1 cebolla morada'],
                ]) . '</script></head><body></body></html>', 200
            ),
            '*' => \Illuminate\Support\Facades\Http::response('<html><body></body></html>', 200),
        ]);

        (new \App\Jobs\RunRecipeScrapingJob($job->id))->handle(
            app(\App\Repositories\Scraping\ScrapingRepository::class),
            app(\App\Scraping\Adapters\CookpadRecipeScraper::class),
            app(\App\Services\Scraping\ScrapingExecutionGuard::class),
            app(\App\Services\Scraping\ScrapingCircuitBreaker::class),
            app(\App\Services\Scraping\UrlSecurityValidator::class)
        );

        $created = ImportedRecipeCandidate::where('raw_title', 'Test')->firstOrFail();
        $this->assertNotNull($created->parsed_recipe_json['ingredient_suggestions'] ?? null);
        $this->assertEquals($cebolla->id, $created->parsed_recipe_json['ingredient_suggestions'][0]['suggested_ingredient_id']);

        $response = $this->actingAs($user)->getJson('/api/v1/admin/recipes/import-candidates/' . $created->id);
        $response->assertStatus(200)
            ->assertJsonPath('data.ingredient_suggestions.0.suggested_ingredient_id', $cebolla->id);
    }

    private function matcher(): \App\Services\RecipeImportCandidates\IngredientMatchService
    {
        return app(\App\Services\RecipeImportCandidates\IngredientMatchService::class);
    }

    public function test_infiere_unidad_u_para_cantidad_contable_sin_unidad_explicita()
    {
        $r1 = $this->matcher()->matchText('1 cebolla');
        $this->assertEquals(1.0, $r1['parsed_quantity']);
        $this->assertEquals('u', $r1['parsed_unit_text']);

        $r2 = $this->matcher()->matchText('1/2 pimiento rojo');
        $this->assertEquals(0.5, $r2['parsed_quantity']);
        $this->assertEquals('u', $r2['parsed_unit_text']);

        $r3 = $this->matcher()->matchText('2 zanahorias ralladas');
        $this->assertEquals(2.0, $r3['parsed_quantity']);
        $this->assertEquals('u', $r3['parsed_unit_text']);

        $r4 = $this->matcher()->matchText('1 pechuga cortada en cubos');
        $this->assertEquals(1.0, $r4['parsed_quantity']);
        $this->assertEquals('u', $r4['parsed_unit_text']);
    }

    public function test_no_sobreescribe_unidad_explicita_con_u()
    {
        $r1 = $this->matcher()->matchText('250 g champiñones');
        $this->assertEquals(250.0, $r1['parsed_quantity']);
        $this->assertEquals('g', $r1['parsed_unit_text']);

        $r2 = $this->matcher()->matchText('3 tacitas café de arroz');
        $this->assertEquals(3.0, $r2['parsed_quantity']);
        $this->assertEquals('tacitas', $r2['parsed_unit_text']);
    }

    public function test_no_inventa_cantidad_ni_unidad_sin_cantidad_explicita()
    {
        $r1 = $this->matcher()->matchText('sal a gusto');
        $this->assertNull($r1['parsed_quantity']);
        $this->assertNull($r1['parsed_unit_text']);

        $r2 = $this->matcher()->matchText('aceite c/n');
        $this->assertNull($r2['parsed_quantity']);
        $this->assertNull($r2['parsed_unit_text']);

        $r3 = $this->matcher()->matchText('pimienta');
        $this->assertNull($r3['parsed_quantity']);
        $this->assertNull($r3['parsed_unit_text']);
    }

    public function test_detecta_opcionalidad_solo_por_senal_al_inicio()
    {
        $this->assertTrue($this->matcher()->matchText('Opcional laurel y azafrán')['is_optional']);
        $this->assertTrue($this->matcher()->matchText('Opcional: laurel')['is_optional']);
        $this->assertTrue($this->matcher()->matchText('Opcionalmente, laurel')['is_optional']);
        $this->assertFalse($this->matcher()->matchText('Laurel y azafrán')['is_optional']);
    }

    public function test_aplicar_sugerencias_resuelve_unidad_implicita_u()
    {
        $user = $this->adminUser();
        $cebolla = $this->makeIngredient('Cebolla');
        $unit = $this->unitByCode('unit', 'Unidad', 'u');
        $candidate = $this->parsedCandidate(['raw_ingredients_json' => ['1 cebolla morada']]);

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/apply-suggestions');

        $response->assertStatus(200)->assertJsonPath('applied', 1);

        $candidate->refresh();
        $mapping = $candidate->parsed_recipe_json['ingredient_mappings'][0];
        $this->assertEquals($cebolla->id, $mapping['ingredient_id']);
        $this->assertEquals($unit->id, $mapping['unit_id']);
        $this->assertEquals(1, $mapping['quantity']);
    }

    public function test_ingrediente_opcional_sin_mapear_no_bloquea_aprobacion()
    {
        $user = $this->adminUser();
        $this->makeIngredient('Champiñones');
        $this->unitByCode('g', 'Gramo', 'g');
        $candidate = $this->parsedCandidate([
            'raw_ingredients_json' => ['250 g champiñones', 'Opcional laurel y azafrán'],
        ]);

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/apply-suggestions')
            ->assertStatus(200)
            ->assertJsonPath('applied', 1);

        $show = $this->actingAs($user)->getJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id);
        $show->assertStatus(200)
            ->assertJsonPath('data.mapping_summary.required_unmapped_count', 0)
            ->assertJsonPath('data.mapping_summary.optional_unmapped_count', 1)
            ->assertJsonPath('data.mapping_summary.mapping_ready', true);

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/approve')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_ingrediente_requerido_sin_mapear_sigue_bloqueando_aunque_haya_un_opcional()
    {
        $user = $this->adminUser();
        $this->makeIngredient('Champiñones');
        $this->unitByCode('g', 'Gramo', 'g');
        // "laurel" no esta en el catalogo -> unresolved y NO opcional -> bloquea.
        // "Opcional azafran" tampoco esta en el catalogo -> unresolved pero opcional -> no bloquea.
        $candidate = $this->parsedCandidate([
            'raw_ingredients_json' => ['250 g champiñones', 'laurel', 'Opcional azafrán'],
        ]);

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/apply-suggestions')
            ->assertStatus(200);

        $show = $this->actingAs($user)->getJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id);
        $show->assertJsonPath('data.mapping_summary.required_unmapped_count', 1)
            ->assertJsonPath('data.mapping_summary.optional_unmapped_count', 1)
            ->assertJsonPath('data.mapping_summary.mapping_ready', false);

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/approve')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'IMPORT_CANDIDATE_MISSING_MAPPINGS');
    }

    // ------------------------------------------------------------------
    // Procesamiento batch: apply-suggestions-bulk / approve-bulk
    // ------------------------------------------------------------------

    private function readyCandidate(string $url): ImportedRecipeCandidate
    {
        return $this->parsedCandidate([
            'source_url'           => $url,
            'raw_ingredients_json' => ['250 g champiñones'],
        ]);
    }

    public function test_bulk_endpoints_sin_permiso_retorna_403()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/apply-suggestions-bulk', ['candidate_ids' => [1]])
            ->assertStatus(403);
        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/approve-bulk', ['candidate_ids' => [1]])
            ->assertStatus(403);
    }

    public function test_bulk_endpoints_rechazan_mas_de_100()
    {
        $user = $this->adminUser();
        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/apply-suggestions-bulk', ['candidate_ids' => range(1, 101)])
            ->assertStatus(422);
        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/approve-bulk', ['candidate_ids' => range(1, 101)])
            ->assertStatus(422);
    }

    public function test_bulk_apply_sugerencias_sobre_tres_candidatos()
    {
        $user = $this->adminUser();
        $this->makeIngredient('Champiñones');
        $this->unitByCode('g', 'Gramo', 'g');
        $a = $this->readyCandidate('https://cookpad.com/ar/recetas/80001');
        $b = $this->readyCandidate('https://cookpad.com/ar/recetas/80002');
        $c = $this->readyCandidate('https://cookpad.com/ar/recetas/80003');

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/apply-suggestions-bulk', [
            'candidate_ids' => [$a->id, $b->id, $c->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('requested', 3)
            ->assertJsonPath('processed', 3)
            ->assertJsonPath('failed', 0);

        foreach ([$a, $b, $c] as $candidate) {
            $candidate->refresh();
            $this->assertNotEmpty($candidate->parsed_recipe_json['ingredient_mappings'] ?? []);
        }
    }

    public function test_bulk_apply_no_pisa_mapeo_manual_existente()
    {
        $user = $this->adminUser();
        $this->makeIngredient('Champiñones');
        $otro = $this->makeIngredient('Otro ingrediente manual');
        $gramo = $this->unitByCode('g', 'Gramo', 'g');
        $candidate = $this->readyCandidate('https://cookpad.com/ar/recetas/80010');

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/map-ingredient', [
            'ingredient_index' => 0,
            'ingredient_id'    => $otro->id,
            'unit_id'          => $gramo->id,
            'quantity'         => 999,
        ])->assertStatus(200);

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/apply-suggestions-bulk', [
            'candidate_ids' => [$candidate->id],
        ])->assertStatus(200)
            ->assertJsonPath('results.0.skipped_already_mapped', 1)
            ->assertJsonPath('results.0.applied', 0);

        $candidate->refresh();
        $this->assertEquals($otro->id, $candidate->parsed_recipe_json['ingredient_mappings'][0]['ingredient_id']);
    }

    public function test_bulk_apply_mantiene_unresolved()
    {
        $user = $this->adminUser();
        // "laurel" no esta en el catalogo -> unresolved, queda asi.
        $candidate = $this->parsedCandidate([
            'source_url'           => 'https://cookpad.com/ar/recetas/80020',
            'raw_ingredients_json' => ['laurel'],
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/apply-suggestions-bulk', [
            'candidate_ids' => [$candidate->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('results.0.applied', 0)
            ->assertJsonPath('results.0.skipped_unresolved', 1)
            ->assertJsonPath('results.0.mapping_ready', false);
    }

    public function test_bulk_apply_optional_unresolved_puede_dejar_mapping_ready_true()
    {
        $user = $this->adminUser();
        $this->makeIngredient('Champiñones');
        $this->unitByCode('g', 'Gramo', 'g');
        $candidate = $this->parsedCandidate([
            'source_url'           => 'https://cookpad.com/ar/recetas/80030',
            'raw_ingredients_json' => ['250 g champiñones', 'Opcional laurel'],
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/apply-suggestions-bulk', [
            'candidate_ids' => [$candidate->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('results.0.mapping_ready', true);
    }

    public function test_bulk_apply_no_crea_ingredient_ni_unitmeasure()
    {
        $user = $this->adminUser();
        $ingredientsBefore = Ingredient::count();
        $unitsBefore = UnitMeasure::count();
        $candidate = $this->parsedCandidate([
            'source_url'           => 'https://cookpad.com/ar/recetas/80040',
            'raw_ingredients_json' => ['1 cebolla morada', '250 g champiñones'],
        ]);

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/apply-suggestions-bulk', [
            'candidate_ids' => [$candidate->id],
        ])->assertStatus(200);

        $this->assertEquals($ingredientsBefore, Ingredient::count());
        $this->assertEquals($unitsBefore, UnitMeasure::count());
    }

    public function test_bulk_approve_de_tres_listas_crea_tres_recipes()
    {
        $user = $this->adminUser();
        $this->makeIngredient('Champiñones');
        $this->unitByCode('g', 'Gramo', 'g');
        $a = $this->readyCandidate('https://cookpad.com/ar/recetas/81001');
        $b = $this->readyCandidate('https://cookpad.com/ar/recetas/81002');
        $c = $this->readyCandidate('https://cookpad.com/ar/recetas/81003');

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/apply-suggestions-bulk', [
            'candidate_ids' => [$a->id, $b->id, $c->id],
        ])->assertStatus(200);

        $recipesBefore = \App\Recipe::count();

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/approve-bulk', [
            'candidate_ids' => [$a->id, $b->id, $c->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('requested', 3)
            ->assertJsonPath('approved', 3)
            ->assertJsonPath('skipped', 0)
            ->assertJsonPath('failed', 0);

        $this->assertEquals($recipesBefore + 3, \App\Recipe::count());

        foreach ([$a, $b, $c] as $candidate) {
            $candidate->refresh();
            $this->assertEquals('recipe_created', $candidate->status);
            $this->assertNotNull($candidate->created_recipe_id);
        }
    }

    public function test_bulk_approve_omite_not_ready()
    {
        $user = $this->adminUser();
        // Sin ingrediente "laurel" en catalogo -> nunca queda mapeado -> not ready.
        $candidate = $this->parsedCandidate([
            'source_url'           => 'https://cookpad.com/ar/recetas/81010',
            'raw_ingredients_json' => ['laurel'],
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/approve-bulk', [
            'candidate_ids' => [$candidate->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('approved', 0)
            ->assertJsonPath('skipped', 1)
            ->assertJsonPath('results.0.status', 'skipped')
            ->assertJsonPath('results.0.reason', 'mapping_not_ready');

        $candidate->refresh();
        $this->assertEquals('parsed', $candidate->status);
    }

    public function test_bulk_approve_ignora_mapping_ready_desactualizado_del_frontend()
    {
        // El payload solo manda candidate_ids: el backend siempre recalcula
        // required_unmapped_count sobre el estado actual en DB, nunca
        // confia en un mapping_ready que el frontend pudo haber mostrado
        // antes (ej. calculado antes de que se agregara una linea nueva).
        $user = $this->adminUser();
        $candidate = $this->parsedCandidate([
            'source_url'           => 'https://cookpad.com/ar/recetas/81015',
            'raw_ingredients_json' => ['laurel'],
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/approve-bulk', [
            'candidate_ids' => [$candidate->id],
        ]);

        $response->assertJsonPath('results.0.reason', 'mapping_not_ready');
    }

    public function test_bulk_approve_completa_recipe_para_approved_historico_sin_recipe()
    {
        // Candidato aprobado a mano (boton individual "Aprobar") pero sin
        // "Crear receta" todavia: el batch debe completar ese flujo en vez
        // de omitirlo, que es justamente el objetivo de esta accion (ver
        // seccion 4 del fix: "preferencia para UX: intentar completar").
        $user = $this->adminUser();
        $this->makeIngredient('Champiñones');
        $this->unitByCode('g', 'Gramo', 'g');
        $candidate = $this->readyCandidate('https://cookpad.com/ar/recetas/81020');

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/apply-suggestions')->assertStatus(200);
        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/approve')->assertStatus(200);

        $recipesBefore = \App\Recipe::count();

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/approve-bulk', [
            'candidate_ids' => [$candidate->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('approved', 1)
            ->assertJsonPath('skipped', 0)
            ->assertJsonPath('results.0.status', 'approved');

        $this->assertEquals($recipesBefore + 1, \App\Recipe::count());

        $candidate->refresh();
        $this->assertEquals('recipe_created', $candidate->status);
        $this->assertNotNull($candidate->created_recipe_id);
    }

    public function test_bulk_approve_omite_rechazado_y_receta_ya_creada()
    {
        $user = $this->adminUser();
        $rejected = $this->parsedCandidate(['source_url' => 'https://cookpad.com/ar/recetas/81030', 'status' => 'rejected']);

        $this->makeIngredient('Champiñones');
        $this->unitByCode('g', 'Gramo', 'g');
        $created = $this->readyCandidate('https://cookpad.com/ar/recetas/81031');
        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $created->id . '/apply-suggestions')->assertStatus(200);
        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $created->id . '/approve')->assertStatus(200);
        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $created->id . '/create-recipe', ['is_public' => false])->assertStatus(201);

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/approve-bulk', [
            'candidate_ids' => [$rejected->id, $created->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('approved', 0)
            ->assertJsonPath('skipped', 2)
            ->assertJsonPath('results.0.reason', 'already_finalized')
            ->assertJsonPath('results.1.reason', 'already_finalized');
    }

    private function blockingRecipe(string $sourceUrl): \App\Recipe
    {
        return \App\Recipe::create([
            'name' => 'Ya existe', 'nombre' => 'Ya existe', 'normalized_name' => 'ya existe',
            'descripcion' => '', 'tiempo' => '', 'img' => '', 'video' => '', 'porcion' => '', 'calorias' => 0,
            'source_url' => $sourceUrl, 'source_type' => 'user', 'status' => 'active',
            'is_public' => false, 'is_official' => false, 'is_verified' => false,
        ]);
    }

    /**
     * Test critico del fix: candidate parsed + mapping_ready=true, approve()
     * pasa pero createRecipe() lanza excepcion (URL duplicada) -> TODO debe
     * revertirse -> el candidate sigue "parsed" (su estado original), sin
     * quedar "approved" huerfano, y sin Recipe creada.
     */
    public function test_bulk_approve_revierte_approve_completo_si_createRecipe_falla()
    {
        $user = $this->adminUser();
        $this->makeIngredient('Champiñones');
        $this->unitByCode('g', 'Gramo', 'g');
        $this->blockingRecipe('https://cookpad.com/ar/recetas/82100');

        $candidate = $this->readyCandidate('https://cookpad.com/ar/recetas/82100');
        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/apply-suggestions')
            ->assertStatus(200);

        $recipesBefore = \App\Recipe::count();

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/approve-bulk', [
            'candidate_ids' => [$candidate->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('approved', 0)
            ->assertJsonPath('failed', 1)
            ->assertJsonPath('results.0.status', 'failed')
            ->assertJsonPath('results.0.reason', 'IMPORT_CANDIDATE_DUPLICATE_RECIPE');

        $this->assertEquals($recipesBefore, \App\Recipe::count());

        $candidate->refresh();
        $this->assertEquals('parsed', $candidate->status);
        $this->assertNull($candidate->created_recipe_id);
        $this->assertNull($candidate->reviewed_by);
        $this->assertNull($candidate->reviewed_at);
    }

    public function test_bulk_approve_error_en_uno_no_revierte_los_demas()
    {
        $user = $this->adminUser();
        $this->makeIngredient('Champiñones');
        $this->unitByCode('g', 'Gramo', 'g');

        // Receta ya existente con esta URL: createRecipe() va a rechazar
        // el candidato B por duplicado (y revertir SU aprobacion, ver test
        // dedicado arriba), pero A y C deben aprobarse igual.
        $this->blockingRecipe('https://cookpad.com/ar/recetas/82002');

        $a = $this->readyCandidate('https://cookpad.com/ar/recetas/82001');
        $b = $this->readyCandidate('https://cookpad.com/ar/recetas/82002');
        $c = $this->readyCandidate('https://cookpad.com/ar/recetas/82003');

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/apply-suggestions-bulk', [
            'candidate_ids' => [$a->id, $b->id, $c->id],
        ])->assertStatus(200);

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/approve-bulk', [
            'candidate_ids' => [$a->id, $b->id, $c->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('approved', 2)
            ->assertJsonPath('failed', 1);

        $a->refresh();
        $b->refresh();
        $c->refresh();
        $this->assertEquals('recipe_created', $a->status);
        $this->assertEquals('parsed', $b->status); // revertido por completo: createRecipe() fallo.
        $this->assertNull($b->created_recipe_id);
        $this->assertEquals('recipe_created', $c->status);
    }

    public function test_bulk_approve_rerun_es_idempotente_no_duplica_recipe()
    {
        $user = $this->adminUser();
        $this->makeIngredient('Champiñones');
        $this->unitByCode('g', 'Gramo', 'g');
        $candidate = $this->readyCandidate('https://cookpad.com/ar/recetas/83001');

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/apply-suggestions-bulk', [
            'candidate_ids' => [$candidate->id],
        ])->assertStatus(200);

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/approve-bulk', [
            'candidate_ids' => [$candidate->id],
        ])->assertStatus(200)->assertJsonPath('approved', 1);

        $recipesAfterFirstRun = \App\Recipe::count();

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/approve-bulk', [
            'candidate_ids' => [$candidate->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('approved', 0)
            ->assertJsonPath('skipped', 1)
            ->assertJsonPath('results.0.reason', 'already_finalized');

        $this->assertEquals($recipesAfterFirstRun, \App\Recipe::count());
    }

    public function test_bulk_approve_recipe_creada_igual_que_aprobacion_individual()
    {
        $user = $this->adminUser();
        $this->makeIngredient('Champiñones');
        $this->unitByCode('g', 'Gramo', 'g');
        $candidate = $this->readyCandidate('https://cookpad.com/ar/recetas/84001');

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/apply-suggestions-bulk', [
            'candidate_ids' => [$candidate->id],
        ])->assertStatus(200);

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/approve-bulk', [
            'candidate_ids' => [$candidate->id],
        ])->assertStatus(200)->assertJsonPath('approved', 1);

        $candidate->refresh();
        $recipe = \App\Recipe::find($candidate->created_recipe_id);
        $this->assertNotNull($recipe);
        $this->assertEquals('imported', $recipe->source_type);
        $this->assertEquals('https://cookpad.com/ar/recetas/84001', $recipe->source_url);
        $this->assertCount(1, $recipe->ingredients()->get());
    }

    // ------------------------------------------------------------------
    // B3: candidatas de "Importar receta por texto" (raw_ingredients_json
    // como objetos con name_raw, no strings ni {name,text}) deben poder
    // mapearse igual que las de scraping.
    // ------------------------------------------------------------------

    public function test_candidata_de_importar_por_texto_resuelve_sugerencias_con_name_raw()
    {
        $user = $this->adminUser();
        $cebolla = $this->makeIngredient('Cebolla');
        $champi = $this->makeIngredient('Champiñones');
        $unit = $this->unitByCode('unit', 'Unidad', 'u');
        $gramo = $this->unitByCode('g', 'Gramo', 'g');

        // Misma forma que produce el parser de "Importar receta por texto"
        // real (name_raw, no name/text): sin el fix de B3, raw_text queda
        // vacio y no hay ninguna sugerencia.
        $candidate = $this->parsedCandidate([
            'raw_ingredients_json' => [
                ['name_raw' => 'cebolla', 'quantity' => 1, 'raw_line' => '- 1 cebolla', 'unit_raw' => null, 'unit_code' => null, 'unit_ambiguous' => false],
                ['name_raw' => 'champiñones', 'quantity' => 250, 'raw_line' => '- 250 g champiñones', 'unit_raw' => 'g', 'unit_code' => 'g', 'unit_ambiguous' => false],
            ],
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/recalculate-suggestions');

        $response->assertStatus(200)
            ->assertJsonPath('data.ingredient_suggestions.0.raw_text', '1 cebolla')
            ->assertJsonPath('data.ingredient_suggestions.0.suggested_ingredient_id', $cebolla->id)
            ->assertJsonPath('data.ingredient_suggestions.1.raw_text', '250 g champiñones')
            ->assertJsonPath('data.ingredient_suggestions.1.suggested_ingredient_id', $champi->id);

        $apply = $this->actingAs($user)->postJson('/api/v1/admin/recipes/import-candidates/' . $candidate->id . '/apply-suggestions');

        $apply->assertStatus(200)->assertJsonPath('applied', 2);

        $candidate->refresh();
        $mappings = $candidate->parsed_recipe_json['ingredient_mappings'];
        $this->assertEquals($cebolla->id, $mappings[0]['ingredient_id']);
        $this->assertEquals($unit->id, $mappings[0]['unit_id']);
        $this->assertEquals($champi->id, $mappings[1]['ingredient_id']);
        $this->assertEquals($gramo->id, $mappings[1]['unit_id']);
    }
}
