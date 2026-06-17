<?php

namespace Tests\Feature\Api\V1\RecipeImportUrl;

use App\ImportedRecipeCandidate;
use App\Permission;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecipeImportUrlTest extends TestCase
{
    use RefreshDatabase;

    const SUPPORTED_URL  = 'https://allrecipes.com/recipe/123/pasta';
    const UNSUPPORTED_URL = 'https://unknownsite.xyz/recipe/pasta';

    private function recipeHtmlFixture(string $title = 'Pasta al Pesto'): string
    {
        $json = json_encode([
            '@context' => 'https://schema.org',
            '@type'    => 'Recipe',
            'name'     => $title,
            'description'  => 'Una deliciosa pasta.',
            'recipeYield'  => '4',
            'prepTime'     => 'PT10M',
            'cookTime'     => 'PT20M',
            'recipeIngredient' => ['200g pasta', '100g albahaca'],
            'recipeInstructions' => [
                ['@type' => 'HowToStep', 'text' => 'Hervir el agua.'],
                ['@type' => 'HowToStep', 'text' => 'Agregar la pasta.'],
            ],
            'image' => 'https://allrecipes.com/image.jpg',
        ]);

        return '<html><head><script type="application/ld+json">' . $json . '</script></head><body><h1>' . $title . '</h1></body></html>';
    }

    private function adminUser(): User
    {
        $user = factory(User::class)->create();
        $role = Role::firstOrCreate(['code' => 'recipe_admin'], ['name' => 'Recipe Admin', 'status' => 'active']);
        $perm = Permission::firstOrCreate(['code' => 'recipes.manage'], ['name' => 'Manage Recipes', 'module' => 'recipes', 'action' => 'manage', 'status' => 'active']);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
        return $user;
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $this->postJson('/api/v1/admin/recipes/import/url', ['url' => self::SUPPORTED_URL])
            ->assertStatus(401);
    }

    public function test_sin_permiso_retorna_403()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import/url', ['url' => self::SUPPORTED_URL])
            ->assertStatus(403);
    }

    public function test_url_invalida_retorna_422()
    {
        $user = $this->adminUser();
        $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import/url', ['url' => 'not-a-url'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'RECIPE_IMPORT_INVALID_URL');
    }

    public function test_url_ssrf_localhost_retorna_422()
    {
        $user = $this->adminUser();
        $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import/url', ['url' => 'http://localhost/admin'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'RECIPE_IMPORT_SSRF_BLOCKED');
    }

    public function test_fuente_no_soportada_retorna_422()
    {
        $user = $this->adminUser();
        $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import/url', ['url' => self::UNSUPPORTED_URL])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'RECIPE_IMPORT_UNSUPPORTED_SOURCE');
    }

    public function test_importacion_valida_crea_candidata_con_datos()
    {
        Http::fake([self::SUPPORTED_URL => Http::response($this->recipeHtmlFixture(), 200)]);

        $user = $this->adminUser();
        $response = $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import/url', ['url' => self::SUPPORTED_URL])
            ->assertStatus(201);

        $this->assertEquals('parsed', $response->json('data.status'));
        $this->assertEquals('Pasta al Pesto', $response->json('data.raw_title'));
        $this->assertNotEmpty($response->json('data.raw_ingredients_json'));
        $this->assertNotEmpty($response->json('data.raw_steps_json'));

        $this->assertDatabaseHas('imported_recipe_candidates', [
            'source_url' => self::SUPPORTED_URL,
            'status'     => 'parsed',
        ]);
    }

    public function test_url_duplicada_retorna_409()
    {
        Http::fake([self::SUPPORTED_URL => Http::response($this->recipeHtmlFixture(), 200)]);

        $user = $this->adminUser();
        $this->actingAs($user)->postJson('/api/v1/admin/recipes/import/url', ['url' => self::SUPPORTED_URL]);

        $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import/url', ['url' => self::SUPPORTED_URL])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'RECIPE_IMPORT_DUPLICATE');
    }

    public function test_html_sin_receta_retorna_error_parseo()
    {
        $html = '<html><body><p>Pagina sin receta</p></body></html>';
        Http::fake([self::SUPPORTED_URL => Http::response($html, 200)]);

        $user = $this->adminUser();
        $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import/url', ['url' => self::SUPPORTED_URL])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'RECIPE_IMPORT_PARSE_FAILED');

        $this->assertDatabaseHas('imported_recipe_candidates', [
            'source_url' => self::SUPPORTED_URL,
            'status'     => 'failed',
        ]);
    }

    public function test_auditoria_al_importar_exitosamente()
    {
        Http::fake([self::SUPPORTED_URL => Http::response($this->recipeHtmlFixture('Milanesa'), 200)]);

        $user = $this->adminUser();
        $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/import/url', ['url' => self::SUPPORTED_URL])
            ->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'recipe_import_created',
            'entity_name' => 'imported_recipe_candidates',
        ]);
    }
}
