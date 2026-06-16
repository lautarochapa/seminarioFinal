<?php

namespace Tests\Feature\Api\V1\AdminRecipes;

use App\AuditLog;
use App\Recipe;
use App\RecipeCategory;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminRecipesTest extends TestCase
{
    use RefreshDatabase;

    private function admin()
    {
        $user = factory(User::class)->create();
        $role = Role::where('code', 'super_admin')->first();

        DB::table('user_roles')->insert([
            'user_id'    => $user->id,
            'role_id'    => $role->id,
            'created_at' => now(),
        ]);

        return $user;
    }

    private function recipe(array $data = [])
    {
        $name = $data['name'] ?? ('Receta ' . uniqid());

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
            'is_public'       => false,
            'is_official'     => false,
            'is_verified'     => false,
        ], $data));
    }

    private function activeCategory()
    {
        return RecipeCategory::create([
            'name'   => 'Cat ' . uniqid(),
            'status' => 'active',
        ]);
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $this->getJson('/api/v1/admin/recipes')->assertStatus(401);
    }

    public function test_sin_permiso_retorna_403()
    {
        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/recipes')
            ->assertStatus(403);
    }

    public function test_listado_admin_incluye_recetas_inactivas()
    {
        $active   = $this->recipe(['name' => 'Activa', 'status' => 'active']);
        $inactive = $this->recipe(['name' => 'Inactiva', 'status' => 'inactive']);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/v1/admin/recipes');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertContains($inactive->id, $ids);
    }

    public function test_filtro_por_source_type()
    {
        $official = $this->recipe(['name' => 'Oficial', 'source_type' => 'official', 'is_official' => true]);
        $user     = $this->recipe(['name' => 'Usuario', 'source_type' => 'user']);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/v1/admin/recipes?source_type=official');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($official->id, $ids);
        $this->assertNotContains($user->id, $ids);
    }

    public function test_detalle_admin_incluye_relaciones_completas()
    {
        $cat    = $this->activeCategory();
        $recipe = $this->recipe(['name' => 'Detalle Admin', 'category_id' => $cat->id]);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/v1/admin/recipes/' . $recipe->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $recipe->id)
            ->assertJsonStructure(['data' => ['id', 'name', 'category', 'tags', 'ingredients', 'steps', 'sources']]);
    }

    public function test_alta_receta_oficial()
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/recipes', [
            'name'        => 'Asado Tradicional',
            'description' => 'Receta oficial',
            'is_official' => true,
            'status'      => 'active',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_official', true)
            ->assertJsonPath('data.source_type', 'official');

        $this->assertDatabaseHas('recipes', [
            'name'        => 'Asado Tradicional',
            'is_official' => true,
            'source_type' => 'official',
        ]);
    }

    public function test_alta_receta_externa_con_fuente()
    {
        $response = $this->actingAs($this->admin())->postJson('/api/v1/admin/recipes', [
            'name'          => 'Pizza Napolitana',
            'source_type'   => 'external',
            'source_url'    => 'https://example.com/pizza',
            'source_site'   => 'Example Recipes',
            'source_author' => 'Chef Nap',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.source_type', 'external');

        $this->assertDatabaseHas('recipes', [
            'name'          => 'Pizza Napolitana',
            'source_type'   => 'external',
            'source_author' => 'Chef Nap',
        ]);
    }

    public function test_edicion_externa_conserva_y_actualiza_fuente()
    {
        $recipe = $this->recipe([
            'name'          => 'Externa',
            'source_type'   => 'external',
            'source_url'    => 'https://example.com/old',
            'source_author' => 'Chef Original',
        ]);

        $this->actingAs($this->admin())->patchJson('/api/v1/admin/recipes/' . $recipe->id, [
            'source_url'    => 'https://example.com/new',
            'source_author' => 'Chef Nuevo',
        ])->assertStatus(200)
            ->assertJsonPath('data.source_type', 'external');
    }

    public function test_campo_no_permitido_rechazado()
    {
        $this->actingAs($this->admin())->postJson('/api/v1/admin/recipes', [
            'name'         => 'Test',
            'owner_user_id' => 999,
        ])->assertStatus(422);
    }

    public function test_categoria_inexistente_rechazada()
    {
        $this->actingAs($this->admin())->postJson('/api/v1/admin/recipes', [
            'name'        => 'Sin Cat',
            'category_id' => 999999,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'RECIPE_CATEGORY_NOT_FOUND');
    }

    public function test_baja_logica_admin()
    {
        $admin  = $this->admin();
        $recipe = $this->recipe(['name' => 'A Borrar', 'is_official' => true]);

        $this->actingAs($admin)->deleteJson('/api/v1/admin/recipes/' . $recipe->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertSoftDeleted('recipes', ['id' => $recipe->id]);
    }

    public function test_auditoria_registra_alta_admin()
    {
        $this->actingAs($this->admin())->postJson('/api/v1/admin/recipes', [
            'name'        => 'Auditada Admin',
            'is_official' => true,
        ])->assertStatus(201);

        $this->assertTrue(AuditLog::where('action', 'recipe.admin.created')
            ->where('entity_name', 'recipes')
            ->exists());
    }
}
