<?php

namespace Tests\Feature\Api\V1\IngredientCategories;

use App\AuditLog;
use App\IngredientCategory;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IngredientCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function admin()
    {
        $user = factory(User::class)->create();
        $role = Role::where('code', 'super_admin')->first();

        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
        ]);

        return $user;
    }

    private function category(array $data = [])
    {
        return IngredientCategory::create(array_merge([
            'code' => 'cat_'.uniqid(),
            'name' => 'Category',
            'description' => null,
            'parent_id' => null,
            'sort_order' => 0,
            'is_active' => true,
            'status' => 'active',
        ], $data));
    }

    public function test_admin_sin_autenticacion()
    {
        $this->getJson('/api/v1/admin/ingredient-categories')->assertStatus(401);
    }

    public function test_admin_sin_permiso()
    {
        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/ingredient-categories')
            ->assertStatus(403);
    }

    public function test_alta_categoria_raiz()
    {
        $response = $this->actingAs($this->admin())->postJson('/api/v1/admin/ingredient-categories', [
            'code' => ' Fresh Vegetables ',
            'name' => 'Verduras',
            'description' => 'Frescas',
            'sort_order' => 2,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'fresh_vegetables')
            ->assertJsonPath('data.parent_id', null);

        $this->assertDatabaseHas('ingredient_categories', ['code' => 'fresh_vegetables']);
    }

    public function test_alta_subcategoria()
    {
        $parent = $this->category(['code' => 'vegetables', 'name' => 'Verduras']);

        $this->actingAs($this->admin())->postJson('/api/v1/admin/ingredient-categories', [
            'code' => 'leafy',
            'name' => 'Hojas',
            'parent_id' => $parent->id,
        ])->assertStatus(201)
            ->assertJsonPath('data.parent_id', $parent->id);
    }

    public function test_codigo_duplicado()
    {
        $this->category(['code' => 'fruits']);

        $this->actingAs($this->admin())->postJson('/api/v1/admin/ingredient-categories', [
            'code' => 'FRUITS',
            'name' => 'Frutas',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'INGREDIENT_CATEGORY_CODE_ALREADY_EXISTS');
    }

    public function test_padre_inexistente_o_inactivo()
    {
        $inactive = $this->category(['status' => 'inactive', 'is_active' => false]);

        $this->actingAs($this->admin())->postJson('/api/v1/admin/ingredient-categories', [
            'code' => 'child_one',
            'name' => 'Child',
            'parent_id' => 999999,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'INGREDIENT_CATEGORY_PARENT_NOT_FOUND');

        $this->actingAs($this->admin())->postJson('/api/v1/admin/ingredient-categories', [
            'code' => 'child_two',
            'name' => 'Child',
            'parent_id' => $inactive->id,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'INGREDIENT_CATEGORY_PARENT_INACTIVE');
    }

    public function test_auto_parent_rechazado()
    {
        $category = $this->category();

        $this->actingAs($this->admin())->patchJson('/api/v1/admin/ingredient-categories/'.$category->id, [
            'parent_id' => $category->id,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'INGREDIENT_CATEGORY_SELF_PARENT_FORBIDDEN');
    }

    public function test_ciclo_jerarquico_rechazado()
    {
        $root = $this->category(['code' => 'root']);
        $child = $this->category(['code' => 'child', 'parent_id' => $root->id]);
        $grandchild = $this->category(['code' => 'grandchild', 'parent_id' => $child->id]);

        $this->actingAs($this->admin())->patchJson('/api/v1/admin/ingredient-categories/'.$root->id, [
            'parent_id' => $grandchild->id,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'INGREDIENT_CATEGORY_CYCLE_FORBIDDEN');
    }

    public function test_actualizacion_parcial()
    {
        $category = $this->category(['name' => 'Old']);

        $this->actingAs($this->admin())->patchJson('/api/v1/admin/ingredient-categories/'.$category->id, [
            'name' => 'New',
            'status' => 'inactive',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'New')
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_baja_y_restore()
    {
        $admin = $this->admin();
        $category = $this->category();

        $deleteResponse = $this->actingAs($admin)->deleteJson('/api/v1/admin/ingredient-categories/'.$category->id);
        $deleteResponse->assertStatus(200);
        $this->assertNotNull($deleteResponse->json('data.deleted_at'));

        $this->actingAs($admin)->patchJson('/api/v1/admin/ingredient-categories/'.$category->id.'/restore')
            ->assertStatus(200)
            ->assertJsonPath('data.deleted_at', null)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_categoria_con_hijos_no_se_elimina()
    {
        $parent = $this->category();
        $this->category(['parent_id' => $parent->id]);

        $this->actingAs($this->admin())->deleteJson('/api/v1/admin/ingredient-categories/'.$parent->id)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'INGREDIENT_CATEGORY_HAS_CHILDREN');
    }

    public function test_catalogo_devuelve_solo_activas()
    {
        $active = $this->category(['code' => 'active_category', 'name' => 'Activa']);
        $this->category(['code' => 'inactive_category', 'name' => 'Inactiva', 'status' => 'inactive', 'is_active' => false]);

        $response = $this->actingAs(factory(User::class)->create())->getJson('/api/v1/ingredient-categories');

        $response->assertStatus(200);
        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains($active->code, $codes);
        $this->assertNotContains('inactive_category', $codes);
    }

    public function test_arbol_jerarquico_correcto()
    {
        $root = $this->category(['code' => 'vegetables', 'name' => 'Verduras', 'sort_order' => 1]);
        $child = $this->category(['code' => 'leafy', 'name' => 'Hojas', 'parent_id' => $root->id, 'sort_order' => 1]);

        $response = $this->actingAs(factory(User::class)->create())->getJson('/api/v1/ingredient-categories');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.code', 'vegetables')
            ->assertJsonPath('data.0.children.0.id', $child->id)
            ->assertJsonPath('data.0.children.0.children', []);
    }

    public function test_auditoria()
    {
        $admin = $this->admin();
        $category = $this->category();

        $this->actingAs($admin)->patchJson('/api/v1/admin/ingredient-categories/'.$category->id, [
            'name' => 'Audited',
        ])->assertStatus(200);

        $this->assertTrue(AuditLog::where('action', 'ingredient-category.updated')->exists());
    }
}
