<?php

namespace Tests\Feature\Api\V1\RecipeCategories;

use App\AuditLog;
use App\RecipeCategory;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecipeCategoriesTest extends TestCase
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

    private function category(array $data = [])
    {
        return RecipeCategory::create(array_merge([
            'name'        => 'Category ' . uniqid(),
            'description' => null,
            'parent_id'   => null,
            'status'      => 'active',
        ], $data));
    }

    public function test_admin_sin_autenticacion_retorna_401()
    {
        $this->getJson('/api/v1/admin/recipe-categories')->assertStatus(401);
    }

    public function test_admin_sin_permiso_retorna_403()
    {
        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/recipe-categories')
            ->assertStatus(403);
    }

    public function test_alta_categoria_raiz()
    {
        $response = $this->actingAs($this->admin())->postJson('/api/v1/admin/recipe-categories', [
            'name'   => 'Postres',
            'status' => 'active',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Postres')
            ->assertJsonPath('data.parent_id', null);

        $this->assertDatabaseHas('recipe_categories', ['name' => 'Postres']);
    }

    public function test_nombre_duplicado_retorna_409()
    {
        $this->category(['name' => 'Sopas']);

        $this->actingAs($this->admin())->postJson('/api/v1/admin/recipe-categories', [
            'name' => 'Sopas',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'RECIPE_CATEGORY_NAME_ALREADY_EXISTS');
    }

    public function test_actualizacion_parcial()
    {
        $category = $this->category(['name' => 'Bebidas']);

        $this->actingAs($this->admin())->patchJson('/api/v1/admin/recipe-categories/' . $category->id, [
            'name'   => 'Bebidas Calientes',
            'status' => 'inactive',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Bebidas Calientes')
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_baja_logica_y_restore()
    {
        $admin    = $this->admin();
        $category = $this->category();

        $delete = $this->actingAs($admin)->deleteJson('/api/v1/admin/recipe-categories/' . $category->id);
        $delete->assertStatus(200);
        $this->assertNotNull($delete->json('data.deleted_at'));

        $this->actingAs($admin)->patchJson('/api/v1/admin/recipe-categories/' . $category->id . '/restore')
            ->assertStatus(200)
            ->assertJsonPath('data.deleted_at', null)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_no_elimina_categoria_con_hijos()
    {
        $parent = $this->category(['name' => 'Carnes']);
        $this->category(['name' => 'Carnes Rojas', 'parent_id' => $parent->id]);

        $this->actingAs($this->admin())->deleteJson('/api/v1/admin/recipe-categories/' . $parent->id)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'RECIPE_CATEGORY_HAS_CHILDREN');
    }

    public function test_auto_parent_rechazado()
    {
        $category = $this->category(['name' => 'Ensaladas']);

        $this->actingAs($this->admin())->patchJson('/api/v1/admin/recipe-categories/' . $category->id, [
            'parent_id' => $category->id,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'RECIPE_CATEGORY_SELF_PARENT_FORBIDDEN');
    }

    public function test_padre_inactivo_rechazado()
    {
        $inactive = $this->category(['name' => 'Inactiva', 'status' => 'inactive']);

        $this->actingAs($this->admin())->postJson('/api/v1/admin/recipe-categories', [
            'name'      => 'Hija',
            'parent_id' => $inactive->id,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'RECIPE_CATEGORY_PARENT_INACTIVE');
    }

    public function test_catalogo_solo_categorias_activas()
    {
        $active   = $this->category(['name' => 'Activa']);
        $inactive = $this->category(['name' => 'Inactiva', 'status' => 'inactive']);

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/recipe-categories');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains($active->name, $names);
        $this->assertNotContains($inactive->name, $names);
    }

    public function test_catalogo_arbol_jerarquico_ordenado_por_nombre()
    {
        $root  = $this->category(['name' => 'Verduras']);
        $child = $this->category(['name' => 'Hojas Verdes', 'parent_id' => $root->id]);

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/recipe-categories');

        $response->assertStatus(200);
        $found = collect($response->json('data'))->firstWhere('name', 'Verduras');
        $this->assertNotNull($found);
        $this->assertEquals($child->id, $found['children'][0]['id']);
    }

    public function test_auditoria_registra_escritura()
    {
        $admin    = $this->admin();
        $category = $this->category(['name' => 'Pastas']);

        $this->actingAs($admin)->patchJson('/api/v1/admin/recipe-categories/' . $category->id, [
            'name' => 'Pastas y Arroces',
        ])->assertStatus(200);

        $this->assertTrue(AuditLog::where('action', 'recipe-category.updated')
            ->where('entity_name', 'recipe_categories')
            ->exists());
    }
}
