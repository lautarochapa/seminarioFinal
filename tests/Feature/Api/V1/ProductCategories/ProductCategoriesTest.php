<?php

namespace Tests\Feature\Api\V1\ProductCategories;

use App\AuditLog;
use App\Brand;
use App\Product;
use App\ProductCategory;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductCategoriesTest extends TestCase
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
        return ProductCategory::create(array_merge([
            'name' => 'Category '.uniqid(),
            'description' => null,
            'parent_id' => null,
            'status' => 'active',
        ], $data));
    }

    public function test_admin_autenticacion_y_permiso()
    {
        $this->getJson('/api/v1/admin/product-categories')->assertStatus(401);

        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/product-categories')
            ->assertStatus(403);
    }

    public function test_alta_raiz_y_subcategoria_con_nombre_normalizado()
    {
        $admin = $this->admin();

        $root = $this->actingAs($admin)->postJson('/api/v1/admin/product-categories', [
            'name' => '  Lacteos  ',
            'description' => 'Productos lacteos',
        ]);

        $root->assertStatus(201)
            ->assertJsonPath('data.name', 'Lacteos')
            ->assertJsonPath('data.parent_id', null);

        $this->actingAs($admin)->postJson('/api/v1/admin/product-categories', [
            'name' => 'Leches',
            'parent_id' => $root->json('data.id'),
        ])->assertStatus(201)
            ->assertJsonPath('data.parent_id', $root->json('data.id'));
    }

    public function test_nombre_duplicado_y_detalle_inexistente()
    {
        $this->category(['name' => 'Bebidas']);

        $this->actingAs($this->admin())->postJson('/api/v1/admin/product-categories', [
            'name' => ' bebidas ',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'PRODUCT_CATEGORY_NAME_ALREADY_EXISTS');

        $this->actingAs($this->admin())->getJson('/api/v1/admin/product-categories/999999')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'PRODUCT_CATEGORY_NOT_FOUND');
    }

    public function test_padre_inexistente_inactivo_auto_parent_y_ciclo()
    {
        $admin = $this->admin();
        $inactive = $this->category(['status' => 'inactive']);

        $this->actingAs($admin)->postJson('/api/v1/admin/product-categories', [
            'name' => 'Child one',
            'parent_id' => 999999,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'PRODUCT_CATEGORY_PARENT_NOT_FOUND');

        $this->actingAs($admin)->postJson('/api/v1/admin/product-categories', [
            'name' => 'Child two',
            'parent_id' => $inactive->id,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'PRODUCT_CATEGORY_PARENT_INACTIVE');

        $root = $this->category(['name' => 'Root']);
        $child = $this->category(['name' => 'Child', 'parent_id' => $root->id]);
        $grandchild = $this->category(['name' => 'Grandchild', 'parent_id' => $child->id]);

        $this->actingAs($admin)->patchJson('/api/v1/admin/product-categories/'.$root->id, [
            'parent_id' => $root->id,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'PRODUCT_CATEGORY_SELF_PARENT_FORBIDDEN');

        $this->actingAs($admin)->patchJson('/api/v1/admin/product-categories/'.$root->id, [
            'parent_id' => $grandchild->id,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'PRODUCT_CATEGORY_CYCLE_FORBIDDEN');
    }

    public function test_actualizacion_parcial()
    {
        $category = $this->category(['name' => 'Old', 'description' => 'Keep']);

        $this->actingAs($this->admin())->patchJson('/api/v1/admin/product-categories/'.$category->id, [
            'name' => 'New',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'New')
            ->assertJsonPath('data.description', 'Keep');
    }

    public function test_baja_restore_y_relaciones_historicas()
    {
        $admin = $this->admin();
        $category = $this->category();
        $brand = Brand::create([
            'nombre' => 'Marca',
            'name' => 'Marca',
            'normalized_name' => 'marca',
            'status' => 'active',
            'padre' => 0,
        ]);
        $supplyId = DB::table('supplies')->insertGetId([
            'nombre' => 'Insumo',
            'medida' => 'g',
            'category_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Product::create([
            'nombre' => 'Producto',
            'brand_id' => $brand->id,
            'codigo' => 'P001',
            'img' => 'producto.png',
            'habilitado' => 1,
            'name' => 'Producto',
            'normalized_name' => 'producto',
            'category_id' => $category->id,
            'supply_id' => $supplyId,
            'status' => 'active',
        ]);

        $this->actingAs($admin)->deleteJson('/api/v1/admin/product-categories/'.$category->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertSoftDeleted('product_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['category_id' => $category->id]);

        $this->actingAs($admin)->patchJson('/api/v1/admin/product-categories/'.$category->id.'/restore')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_restore_valida_conflicto_de_nombre()
    {
        $admin = $this->admin();
        $deleted = $this->category(['name' => 'Almacen']);
        $deleted->status = 'inactive';
        $deleted->save();
        $deleted->delete();
        $this->category(['name' => 'almacen']);

        $this->actingAs($admin)->patchJson('/api/v1/admin/product-categories/'.$deleted->id.'/restore')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PRODUCT_CATEGORY_NAME_ALREADY_EXISTS');
    }

    public function test_catalogo_solo_activas_y_arbol()
    {
        $root = $this->category(['name' => 'Bebidas']);
        $child = $this->category(['name' => 'Gaseosas', 'parent_id' => $root->id]);
        $this->category(['name' => 'Inactiva', 'status' => 'inactive']);

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/product-categories');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $root->id)
            ->assertJsonPath('data.0.children.0.id', $child->id)
            ->assertJsonMissing(['name' => 'Inactiva']);
    }

    public function test_auditoria()
    {
        $admin = $this->admin();
        $category = $this->category();

        $this->actingAs($admin)->patchJson('/api/v1/admin/product-categories/'.$category->id, [
            'description' => 'Audited',
        ])->assertStatus(200);

        $this->assertTrue(AuditLog::where('entity_name', 'product_categories')->where('action', 'product-category.updated')->exists());
    }

    public function test_rutas_registradas_en_api()
    {
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/admin/product-categories', 'GET')));
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/product-categories', 'GET')));
    }
}
