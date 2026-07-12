<?php

namespace Tests\Feature\Api\V1\RecipeImportText;

use App\Permission;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeImportTextTest extends TestCase
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

    private function recipeText(): string
    {
        return "Pasta al Pesto\n\nIngredientes\n200 g pasta\n100 g albahaca\n2 dientes de ajo\n\nPreparacion\nHervir el agua con sal.\nAgregar la pasta y cocinar 10 minutos.\nMezclar con el pesto y servir.";
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $this->postJson('/api/v1/admin/recipes/import/text', ['text' => $this->recipeText()])
            ->assertStatus(401);
    }

    public function test_sin_permiso_retorna_403()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import/text', ['text' => $this->recipeText()])
            ->assertStatus(403);
    }

    public function test_texto_vacio_o_corto_retorna_422()
    {
        $user = $this->adminUser();
        $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import/text', ['text' => 'corto'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_importacion_valida_crea_candidata_con_datos()
    {
        $user = $this->adminUser();
        $response = $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import/text', ['text' => $this->recipeText()])
            ->assertStatus(201);

        $this->assertEquals('parsed', $response->json('data.status'));
        $this->assertEquals('Pasta al Pesto', $response->json('data.raw_title'));

        $this->assertDatabaseHas('imported_recipe_candidates', [
            'source_site' => 'text-import',
            'status'      => 'parsed',
            'raw_title'   => 'Pasta al Pesto',
        ]);
    }

    public function test_ingredientes_y_pasos_se_extraen()
    {
        $user = $this->adminUser();
        $response = $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import/text', ['text' => $this->recipeText()])
            ->assertStatus(201);

        $ingredients = $response->json('data.raw_ingredients_json');
        $steps       = $response->json('data.raw_steps_json');

        $this->assertNotEmpty($ingredients);
        $this->assertNotEmpty($steps);

        $unitCodes = array_column($ingredients, 'unit_code');
        $this->assertContains('g', $unitCodes);

        $this->assertGreaterThanOrEqual(2, count($steps));
    }

    public function test_datos_ambiguos_se_marcan_para_revision()
    {
        $text = "Receta sin unidades claras\n\nIngredientes\nun poco de harina\nalgo de azucar\n\nPreparacion\nMezclar todo. Hornear. Servir frio.";

        $user     = $this->adminUser();
        $response = $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import/text', ['text' => $text])
            ->assertStatus(201);

        $parsed = $response->json('data.parsed_recipe_json');
        $this->assertNotEmpty($parsed['ambiguous_flags']);
    }

    public function test_texto_sin_titulo_retorna_error_sin_crear_registro()
    {
        // Solo encabezados de seccion — ninguna linea valida como titulo
        $text = str_repeat("Ingredientes\nPreparacion\n", 3);

        $user = $this->adminUser();
        $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import/text', ['text' => $text])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'RECIPE_IMPORT_PARSE_FAILED');

        $this->assertDatabaseCount('imported_recipe_candidates', 0);
    }

    public function test_texto_duplicado_retorna_409()
    {
        $user = $this->adminUser();
        $text = $this->recipeText();

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import/text', ['text' => $text]);

        $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import/text', ['text' => $text])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'RECIPE_IMPORT_DUPLICATE');
    }

    public function test_auditoria_al_importar()
    {
        $user = $this->adminUser();
        $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import/text', ['text' => $this->recipeText()])
            ->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'recipe_import_text_created',
            'entity_name' => 'imported_recipe_candidates',
        ]);
    }
}
