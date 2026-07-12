<?php

namespace Tests\Feature\Api\V1\Recipes;

use App\AuditLog;
use App\Recipe;
use App\RecipeCategory;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecipesTest extends TestCase
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
            'name'   => 'Categoria ' . uniqid(),
            'status' => 'active',
        ]);
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $this->getJson('/api/v1/recipes')->assertStatus(401);
    }

    public function test_listado_paginado_retorna_solo_activas()
    {
        $active   = $this->recipe(['name' => 'Activa', 'status' => 'active']);
        $inactive = $this->recipe(['name' => 'Inactiva', 'status' => 'inactive']);

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/recipes');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta', 'links', 'trace_id']);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_filtro_por_categoria()
    {
        $cat  = $this->activeCategory();
        $with = $this->recipe(['name' => 'Con Cat', 'category_id' => $cat->id]);
        $without = $this->recipe(['name' => 'Sin Cat']);

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/recipes?category_id=' . $cat->id);

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($with->id, $ids);
        $this->assertNotContains($without->id, $ids);
    }

    public function test_detalle_incluye_relaciones()
    {
        $cat    = $this->activeCategory();
        $owner  = factory(User::class)->create();
        $recipe = $this->recipe([
            'name'         => 'Con Relaciones',
            'category_id'  => $cat->id,
            'owner_user_id' => $owner->id,
        ]);

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/recipes/' . $recipe->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $recipe->id)
            ->assertJsonStructure(['data' => ['id', 'name', 'category', 'owner', 'tags', 'ingredients', 'steps']]);
    }

    public function test_alta_crea_receta_con_owner_autenticado()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->postJson('/api/v1/recipes', [
            'name'        => 'Milanesa Napolitana',
            'description' => 'Clasico argentino',
            'difficulty'  => 'medium',
            'servings'    => 4,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Milanesa Napolitana')
            ->assertJsonPath('data.source_type', 'user');

        $this->assertDatabaseHas('recipes', [
            'name'          => 'Milanesa Napolitana',
            'owner_user_id' => $user->id,
        ]);
    }

    public function test_alta_sin_name_falla_validacion()
    {
        $this->actingAs(factory(User::class)->create())
            ->postJson('/api/v1/recipes', ['description' => 'Sin nombre'])
            ->assertStatus(422);
    }

    public function test_campo_no_permitido_rechazado()
    {
        $this->actingAs(factory(User::class)->create())
            ->postJson('/api/v1/recipes', [
                'name'       => 'Valida',
                'is_official' => true,
            ])->assertStatus(422);
    }

    public function test_edicion_propia_exitosa()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['name' => 'Original', 'owner_user_id' => $user->id]);

        $this->actingAs($user)->patchJson('/api/v1/recipes/' . $recipe->id, [
            'name' => 'Editada',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Editada');
    }

    public function test_edicion_de_receta_ajena_retorna_403()
    {
        $owner  = factory(User::class)->create();
        $other  = factory(User::class)->create();
        $recipe = $this->recipe(['name' => 'Ajena', 'owner_user_id' => $owner->id]);

        $this->actingAs($other)->patchJson('/api/v1/recipes/' . $recipe->id, [
            'name' => 'Intento',
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'RECIPE_EDIT_FORBIDDEN');
    }

    public function test_edicion_de_receta_oficial_sin_permiso_retorna_403()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['name' => 'Oficial', 'is_official' => true, 'owner_user_id' => $user->id]);

        $this->actingAs($user)->patchJson('/api/v1/recipes/' . $recipe->id, [
            'name' => 'Cambio oficial',
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'RECIPE_OFFICIAL_FORBIDDEN');
    }

    public function test_admin_puede_editar_receta_oficial()
    {
        $admin  = $this->admin();
        $recipe = $this->recipe(['name' => 'Oficial Admin', 'is_official' => true]);

        $this->actingAs($admin)->patchJson('/api/v1/recipes/' . $recipe->id, [
            'name' => 'Oficial Editada',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Oficial Editada');
    }

    public function test_baja_logica_por_autor()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['name' => 'A Eliminar', 'owner_user_id' => $user->id]);

        $this->actingAs($user)->deleteJson('/api/v1/recipes/' . $recipe->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertSoftDeleted('recipes', ['id' => $recipe->id]);
    }

    public function test_auditoria_registra_escritura()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['name' => 'Auditada', 'owner_user_id' => $user->id]);

        $this->actingAs($user)->patchJson('/api/v1/recipes/' . $recipe->id, [
            'name' => 'Auditada v2',
        ])->assertStatus(200);

        $this->assertTrue(AuditLog::where('action', 'recipe.updated')
            ->where('entity_name', 'recipes')
            ->exists());
    }
}
