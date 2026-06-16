<?php

namespace Tests\Feature\Api\V1\Nutrients;

use App\AuditLog;
use App\Ingredient;
use App\IngredientNutrient;
use App\Nutrient;
use App\Product;
use App\ProductNutrient;
use App\Role;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NutrientsTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

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

    private function unit(array $data = [])
    {
        return UnitMeasure::create(array_merge([
            'code'   => 'unit_' . uniqid(),
            'name'   => 'Gram',
            'type'   => 'mass',
            'symbol' => 'g',
            'status' => 'active',
        ], $data));
    }

    private function nutrient(array $data = [])
    {
        $unit = $data['unit_id'] ?? $this->unit()->id;

        return Nutrient::create(array_merge([
            'code'    => 'nutrient_' . uniqid(),
            'name'    => 'Nutriente ' . uniqid(),
            'unit_id' => $unit,
            'status'  => 'active',
        ], $data));
    }

    private function ingredient(array $data = [])
    {
        $name = $data['name'] ?? 'Ingrediente ' . uniqid();

        return Ingredient::create(array_merge([
            'name'            => $name,
            'normalized_name' => strtolower(str_replace(' ', '_', $name)),
            'status'          => 'active',
            'is_generic'      => true,
            'is_preparation'  => false,
            'is_supplement'   => false,
        ], $data));
    }

    private function product(array $data = [])
    {
        $name = $data['name'] ?? 'Producto ' . uniqid();

        return Product::create(array_merge([
            'name'            => $name,
            'normalized_name' => strtolower(str_replace(' ', '_', $name)),
            'status'          => 'active',
            'is_verified'     => false,
            'is_active'       => true,
        ], $data));
    }

    // ---------------------------------------------------------------------------
    // 1. Autorización
    // ---------------------------------------------------------------------------

    public function test_autorizacion_sin_autenticar()
    {
        $this->getJson('/api/v1/admin/nutrients')->assertStatus(401);
        $this->postJson('/api/v1/admin/nutrients', [])->assertStatus(401);
    }

    public function test_autorizacion_sin_permiso()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user)->getJson('/api/v1/admin/nutrients')->assertStatus(403);
        $this->actingAs($user)->postJson('/api/v1/admin/nutrients', [])->assertStatus(403);
    }

    // ---------------------------------------------------------------------------
    // 2. CRUD básico
    // ---------------------------------------------------------------------------

    public function test_crud_basico()
    {
        $admin = $this->admin();
        $unit  = $this->unit(['code' => 'kcal', 'symbol' => 'kcal']);

        // POST (create)
        $create = $this->actingAs($admin)->postJson('/api/v1/admin/nutrients', [
            'code'        => ' ENERGIA ',
            'name'        => 'Energía',
            'unit_id'     => $unit->id,
            'description' => 'Calorías totales',
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.code', 'energia')
            ->assertJsonPath('data.name', 'Energía')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.unit.id', $unit->id);

        $id = $create->json('data.id');

        // GET show
        $this->actingAs($admin)->getJson('/api/v1/admin/nutrients/' . $id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $id);

        // PATCH update parcial
        $this->actingAs($admin)->patchJson('/api/v1/admin/nutrients/' . $id, [
            'name' => 'Energía Total',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Energía Total')
            ->assertJsonPath('data.code', 'energia'); // código no cambió
    }

    // ---------------------------------------------------------------------------
    // 3. Validaciones de campos obligatorios
    // ---------------------------------------------------------------------------

    public function test_validaciones_campos_obligatorios()
    {
        $this->actingAs($this->admin())->postJson('/api/v1/admin/nutrients', [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_campo_no_permitido_rechazado()
    {
        $unit = $this->unit();

        $this->actingAs($this->admin())->postJson('/api/v1/admin/nutrients', [
            'code'          => 'proteinas',
            'name'          => 'Proteínas',
            'unit_id'       => $unit->id,
            'campo_invalido' => 'foo',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    // ---------------------------------------------------------------------------
    // 4. Código duplicado
    // ---------------------------------------------------------------------------

    public function test_codigo_duplicado_rechazado()
    {
        $admin    = $this->admin();
        $unit     = $this->unit();
        $this->nutrient(['code' => 'proteinas', 'unit_id' => $unit->id]);

        $this->actingAs($admin)->postJson('/api/v1/admin/nutrients', [
            'code'    => ' PROTEINAS ',
            'name'    => 'Proteínas 2',
            'unit_id' => $unit->id,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'NUTRIENT_CODE_DUPLICATE');
    }

    // ---------------------------------------------------------------------------
    // 5. Unidad inválida
    // ---------------------------------------------------------------------------

    public function test_unidad_inexistente_rechazada()
    {
        $this->actingAs($this->admin())->postJson('/api/v1/admin/nutrients', [
            'code'    => 'fibra',
            'name'    => 'Fibra',
            'unit_id' => 999999,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_unidad_inactiva_rechazada()
    {
        $unit = $this->unit(['status' => 'inactive']);

        $this->actingAs($this->admin())->postJson('/api/v1/admin/nutrients', [
            'code'    => 'fibra',
            'name'    => 'Fibra',
            'unit_id' => $unit->id,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'NUTRIENT_INVALID_UNIT');
    }

    // ---------------------------------------------------------------------------
    // 6. Filtros, paginación y búsqueda
    // ---------------------------------------------------------------------------

    public function test_listado_paginacion_y_filtro_status()
    {
        $unit = $this->unit();
        $this->nutrient(['code' => 'n_activo1', 'name' => 'Activo 1', 'unit_id' => $unit->id, 'status' => 'active']);
        $this->nutrient(['code' => 'n_activo2', 'name' => 'Activo 2', 'unit_id' => $unit->id, 'status' => 'active']);
        $this->nutrient(['code' => 'n_inactivo', 'name' => 'Inactivo', 'unit_id' => $unit->id, 'status' => 'inactive']);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/v1/admin/nutrients?status=active&per_page=10');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_listado_filtro_unit_id()
    {
        $unitA = $this->unit(['code' => 'ua_' . uniqid()]);
        $unitB = $this->unit(['code' => 'ub_' . uniqid()]);
        $this->nutrient(['code' => 'n_ua', 'unit_id' => $unitA->id]);
        $this->nutrient(['code' => 'n_ub', 'unit_id' => $unitB->id]);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/v1/admin/nutrients?unit_id=' . $unitA->id);

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($unitA->id, $response->json('data.0.unit_id'));
    }

    public function test_listado_sort_valido_e_invalido()
    {
        $unit = $this->unit();
        $this->nutrient(['code' => 'zzz_z', 'name' => 'Z Nutriente', 'unit_id' => $unit->id]);
        $this->nutrient(['code' => 'aaa_a', 'name' => 'A Nutriente', 'unit_id' => $unit->id]);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/v1/admin/nutrients?sort=name&order=asc');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name')->values()->all();
        $this->assertEquals('A Nutriente', $names[0]);
        $this->assertEquals('Z Nutriente', $names[1]);

        // sort inválido cae al default (name)
        $this->actingAs($this->admin())
            ->getJson('/api/v1/admin/nutrients?sort=campo_invalido')
            ->assertStatus(200);
    }

    public function test_listado_created_from_y_created_to()
    {
        $unit = $this->unit();
        $this->nutrient(['code' => 'viejo', 'unit_id' => $unit->id, 'created_at' => now()->subDays(10)]);
        $this->nutrient(['code' => 'nuevo', 'unit_id' => $unit->id]);

        $desde = now()->subDays(5)->format('Y-m-d');

        $response = $this->actingAs($this->admin())
            ->getJson('/api/v1/admin/nutrients?created_from=' . $desde);

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('nuevo', $response->json('data.0.code'));
    }

    // ---------------------------------------------------------------------------
    // 7. Baja lógica
    // ---------------------------------------------------------------------------

    public function test_baja_logica()
    {
        $admin    = $this->admin();
        $unit     = $this->unit();
        $nutrient = $this->nutrient(['code' => 'sodio', 'unit_id' => $unit->id]);

        $delete = $this->actingAs($admin)->deleteJson('/api/v1/admin/nutrients/' . $nutrient->id);
        $delete->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        // Ahora GET show devuelve 404 (no existe activo)
        $this->actingAs($admin)->getJson('/api/v1/admin/nutrients/' . $nutrient->id)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NUTRIENT_NOT_FOUND');

        // PATCH rechazado sobre nutriente inactivo
        $this->actingAs($admin)->patchJson('/api/v1/admin/nutrients/' . $nutrient->id, [
            'name' => 'Sodio V2',
        ])->assertStatus(404)
            ->assertJsonPath('error.code', 'NUTRIENT_NOT_FOUND');
    }

    // ---------------------------------------------------------------------------
    // 8. Restore correcto
    // ---------------------------------------------------------------------------

    public function test_restore_correcto()
    {
        $admin    = $this->admin();
        $unit     = $this->unit();
        $nutrient = $this->nutrient(['code' => 'azucar', 'unit_id' => $unit->id]);

        $this->actingAs($admin)->deleteJson('/api/v1/admin/nutrients/' . $nutrient->id)->assertStatus(200);

        $this->actingAs($admin)->patchJson('/api/v1/admin/nutrients/' . $nutrient->id . '/restore')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.code', 'azucar');
    }

    // ---------------------------------------------------------------------------
    // 9. Restore con conflicto
    // ---------------------------------------------------------------------------

    public function test_restore_con_conflicto()
    {
        $admin = $this->admin();
        $unit  = $this->unit();

        // Crear y eliminar nutriente con código 'calcio'
        $nutrient = $this->nutrient(['code' => 'calcio', 'unit_id' => $unit->id]);
        $this->actingAs($admin)->deleteJson('/api/v1/admin/nutrients/' . $nutrient->id)->assertStatus(200);

        // Crear otro nutriente activo con el mismo código
        $this->nutrient(['code' => 'calcio', 'unit_id' => $unit->id]);

        // Restaurar debe fallar
        $this->actingAs($admin)->patchJson('/api/v1/admin/nutrients/' . $nutrient->id . '/restore')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'NUTRIENT_RESTORE_CONFLICT');
    }

    // ---------------------------------------------------------------------------
    // 10. Auditoría
    // ---------------------------------------------------------------------------

    public function test_auditoria_create_update_delete_restore()
    {
        $admin = $this->admin();
        $unit  = $this->unit();

        $create = $this->actingAs($admin)->postJson('/api/v1/admin/nutrients', [
            'code'    => 'vitamina_c',
            'name'    => 'Vitamina C',
            'unit_id' => $unit->id,
        ])->assertStatus(201);
        $id = $create->json('data.id');

        $this->actingAs($admin)->patchJson('/api/v1/admin/nutrients/' . $id, [
            'description' => 'Ácido ascórbico',
        ])->assertStatus(200);

        $this->actingAs($admin)->deleteJson('/api/v1/admin/nutrients/' . $id)->assertStatus(200);
        $this->actingAs($admin)->patchJson('/api/v1/admin/nutrients/' . $id . '/restore')->assertStatus(200);

        $this->assertTrue(AuditLog::where('entity_name', 'nutrients')->where('action', 'nutrient.created')->exists());
        $this->assertTrue(AuditLog::where('entity_name', 'nutrients')->where('action', 'nutrient.updated')->exists());
        $this->assertTrue(AuditLog::where('entity_name', 'nutrients')->where('action', 'nutrient.deleted')->exists());
        $this->assertTrue(AuditLog::where('entity_name', 'nutrients')->where('action', 'nutrient.restored')->exists());
    }

    public function test_endpoint_audit()
    {
        $admin    = $this->admin();
        $unit     = $this->unit();
        $nutrient = $this->nutrient(['code' => 'hierro', 'unit_id' => $unit->id]);

        // Crear una entrada de auditoría
        AuditLog::create([
            'user_id'     => $admin->id,
            'action'      => 'nutrient.created',
            'entity_name' => 'nutrients',
            'entity_id'   => (string) $nutrient->id,
            'old_values'  => null,
            'new_values'  => ['code' => 'hierro'],
            'ip_address'  => '127.0.0.1',
            'user_agent'  => 'test',
        ]);

        $this->actingAs($admin)->getJson('/api/v1/admin/nutrients/' . $nutrient->id . '/audit')
            ->assertStatus(200)
            ->assertJsonPath('data.0.resource', 'nutrients');
    }

    // ---------------------------------------------------------------------------
    // 11. Nutrientes de ingredientes — listado
    // ---------------------------------------------------------------------------

    public function test_ingrediente_listado_sin_nutrientes()
    {
        $admin      = $this->admin();
        $ingredient = $this->ingredient();

        $this->actingAs($admin)->getJson('/api/v1/admin/ingredients/' . $ingredient->id . '/nutrients')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_ingrediente_listado_con_nutrientes()
    {
        $admin      = $this->admin();
        $unit       = $this->unit();
        $ingredient = $this->ingredient();
        $nutrient   = $this->nutrient(['code' => 'fibra', 'unit_id' => $unit->id]);

        IngredientNutrient::create([
            'ingredient_id'   => $ingredient->id,
            'nutrient_id'     => $nutrient->id,
            'amount_per_100g' => 5.5,
            'status'          => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/ingredients/' . $ingredient->id . '/nutrients');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nutrient.code', 'fibra')
            ->assertJsonPath('data.0.amount_per_100g', '5.5000');
    }

    // ---------------------------------------------------------------------------
    // 12. Asociación de nutriente a ingrediente
    // ---------------------------------------------------------------------------

    public function test_ingrediente_asociacion_nutriente()
    {
        $admin      = $this->admin();
        $unit       = $this->unit();
        $ingredient = $this->ingredient();
        $nutrient   = $this->nutrient(['code' => 'proteinas', 'unit_id' => $unit->id]);

        $response = $this->actingAs($admin)->postJson(
            '/api/v1/admin/ingredients/' . $ingredient->id . '/nutrients',
            [
                'nutrient_id'    => $nutrient->id,
                'amount_per_100g' => 20.5,
                'source'         => 'USDA',
            ]
        );

        $response->assertStatus(201)
            ->assertJsonPath('data.nutrient_id', $nutrient->id)
            ->assertJsonPath('data.nutrient.code', 'proteinas')
            ->assertJsonPath('data.source', 'USDA');

        $this->assertTrue(
            AuditLog::where('entity_name', 'ingredient_nutrients')->where('action', 'ingredient_nutrient.created')->exists()
        );
    }

    // ---------------------------------------------------------------------------
    // 13. Duplicado ingrediente-nutriente
    // ---------------------------------------------------------------------------

    public function test_ingrediente_asociacion_duplicada_rechazada()
    {
        $admin      = $this->admin();
        $unit       = $this->unit();
        $ingredient = $this->ingredient();
        $nutrient   = $this->nutrient(['code' => 'grasas', 'unit_id' => $unit->id]);

        IngredientNutrient::create([
            'ingredient_id'   => $ingredient->id,
            'nutrient_id'     => $nutrient->id,
            'amount_per_100g' => 10,
            'status'          => 'active',
        ]);

        $this->actingAs($admin)->postJson(
            '/api/v1/admin/ingredients/' . $ingredient->id . '/nutrients',
            ['nutrient_id' => $nutrient->id, 'amount_per_100g' => 15]
        )->assertStatus(409)
            ->assertJsonPath('error.code', 'NUTRIENT_RELATION_DUPLICATE');
    }

    // ---------------------------------------------------------------------------
    // 14. Actualización de relación de ingrediente
    // ---------------------------------------------------------------------------

    public function test_ingrediente_actualizacion_relacion()
    {
        $admin      = $this->admin();
        $unit       = $this->unit();
        $ingredient = $this->ingredient();
        $nutrient   = $this->nutrient(['code' => 'carbohidratos', 'unit_id' => $unit->id]);

        IngredientNutrient::create([
            'ingredient_id'   => $ingredient->id,
            'nutrient_id'     => $nutrient->id,
            'amount_per_100g' => 50,
            'status'          => 'active',
        ]);

        $response = $this->actingAs($admin)->patchJson(
            '/api/v1/admin/ingredients/' . $ingredient->id . '/nutrients/' . $nutrient->id,
            ['amount_per_100g' => 55.5, 'source' => 'FAO']
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.source', 'FAO');
    }

    // ---------------------------------------------------------------------------
    // 15. Ingrediente inexistente o inactivo
    // ---------------------------------------------------------------------------

    public function test_ingrediente_inexistente()
    {
        $this->actingAs($this->admin())
            ->getJson('/api/v1/admin/ingredients/999999/nutrients')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NUTRIENT_INGREDIENT_NOT_FOUND');
    }

    public function test_ingrediente_inactivo_rechazado()
    {
        $unit       = $this->unit();
        $ingredient = $this->ingredient(['status' => 'inactive']);
        $nutrient   = $this->nutrient(['code' => 'vitamina_d', 'unit_id' => $unit->id]);

        $this->actingAs($this->admin())->postJson(
            '/api/v1/admin/ingredients/' . $ingredient->id . '/nutrients',
            ['nutrient_id' => $nutrient->id, 'amount_per_100g' => 10]
        )->assertStatus(404)
            ->assertJsonPath('error.code', 'NUTRIENT_INGREDIENT_NOT_FOUND');
    }

    // ---------------------------------------------------------------------------
    // 16. Valor nutricional negativo o inválido
    // ---------------------------------------------------------------------------

    public function test_valor_negativo_rechazado()
    {
        $unit       = $this->unit();
        $ingredient = $this->ingredient();
        $nutrient   = $this->nutrient(['code' => 'magnesio', 'unit_id' => $unit->id]);

        $this->actingAs($this->admin())->postJson(
            '/api/v1/admin/ingredients/' . $ingredient->id . '/nutrients',
            ['nutrient_id' => $nutrient->id, 'amount_per_100g' => -5]
        )->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    // ---------------------------------------------------------------------------
    // 17. Nutriente inexistente o inactivo al asociar a ingrediente
    // ---------------------------------------------------------------------------

    public function test_nutriente_inexistente_al_asociar()
    {
        $ingredient = $this->ingredient();

        $this->actingAs($this->admin())->postJson(
            '/api/v1/admin/ingredients/' . $ingredient->id . '/nutrients',
            ['nutrient_id' => 999999, 'amount_per_100g' => 5]
        )->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_nutriente_inactivo_al_asociar_a_ingrediente()
    {
        $unit       = $this->unit();
        $ingredient = $this->ingredient();
        $nutrient   = $this->nutrient(['code' => 'potasio', 'unit_id' => $unit->id, 'status' => 'inactive']);

        $this->actingAs($this->admin())->postJson(
            '/api/v1/admin/ingredients/' . $ingredient->id . '/nutrients',
            ['nutrient_id' => $nutrient->id, 'amount_per_100g' => 5]
        )->assertStatus(422)
            ->assertJsonPath('error.code', 'NUTRIENT_NOT_FOUND');
    }

    // ---------------------------------------------------------------------------
    // 18. Nutrientes de productos — listado
    // ---------------------------------------------------------------------------

    public function test_producto_listado_sin_nutrientes()
    {
        $admin   = $this->admin();
        $product = $this->product();

        $this->actingAs($admin)->getJson('/api/v1/admin/products/' . $product->id . '/nutrients')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_producto_listado_con_nutrientes()
    {
        $admin   = $this->admin();
        $unit    = $this->unit();
        $product = $this->product();
        $nutrient = $this->nutrient(['code' => 'calorias', 'unit_id' => $unit->id]);

        ProductNutrient::create([
            'product_id'         => $product->id,
            'nutrient_id'        => $nutrient->id,
            'amount_per_100g'    => 250,
            'amount_per_serving' => 50,
            'serving_size'       => 20,
            'status'             => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/products/' . $product->id . '/nutrients');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nutrient.code', 'calorias');
    }

    // ---------------------------------------------------------------------------
    // 19. Asociación de nutriente a producto
    // ---------------------------------------------------------------------------

    public function test_producto_asociacion_nutriente()
    {
        $admin   = $this->admin();
        $unit    = $this->unit();
        $product = $this->product();
        $nutrient = $this->nutrient(['code' => 'lipidos', 'unit_id' => $unit->id]);

        $response = $this->actingAs($admin)->postJson(
            '/api/v1/admin/products/' . $product->id . '/nutrients',
            [
                'nutrient_id'        => $nutrient->id,
                'amount_per_100g'    => 10,
                'amount_per_serving' => 2,
                'serving_size'       => 20,
            ]
        );

        $response->assertStatus(201)
            ->assertJsonPath('data.nutrient_id', $nutrient->id)
            ->assertJsonPath('data.nutrient.code', 'lipidos');

        $this->assertTrue(
            AuditLog::where('entity_name', 'product_nutrients')->where('action', 'product_nutrient.created')->exists()
        );
    }

    // ---------------------------------------------------------------------------
    // 20. Duplicado producto-nutriente
    // ---------------------------------------------------------------------------

    public function test_producto_asociacion_duplicada_rechazada()
    {
        $admin   = $this->admin();
        $unit    = $this->unit();
        $product = $this->product();
        $nutrient = $this->nutrient(['code' => 'zinc', 'unit_id' => $unit->id]);

        ProductNutrient::create([
            'product_id'      => $product->id,
            'nutrient_id'     => $nutrient->id,
            'amount_per_100g' => 5,
            'status'          => 'active',
        ]);

        $this->actingAs($admin)->postJson(
            '/api/v1/admin/products/' . $product->id . '/nutrients',
            ['nutrient_id' => $nutrient->id, 'amount_per_100g' => 6]
        )->assertStatus(409)
            ->assertJsonPath('error.code', 'NUTRIENT_RELATION_DUPLICATE');
    }

    // ---------------------------------------------------------------------------
    // 21. Producto inexistente o inactivo
    // ---------------------------------------------------------------------------

    public function test_producto_inexistente()
    {
        $this->actingAs($this->admin())
            ->getJson('/api/v1/admin/products/999999/nutrients')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NUTRIENT_PRODUCT_NOT_FOUND');
    }

    public function test_producto_inactivo_rechazado()
    {
        $unit     = $this->unit();
        $product  = $this->product(['status' => 'inactive']);
        $nutrient = $this->nutrient(['code' => 'selenio', 'unit_id' => $unit->id]);

        $this->actingAs($this->admin())->postJson(
            '/api/v1/admin/products/' . $product->id . '/nutrients',
            ['nutrient_id' => $nutrient->id, 'amount_per_100g' => 5]
        )->assertStatus(404)
            ->assertJsonPath('error.code', 'NUTRIENT_PRODUCT_NOT_FOUND');
    }

    // ---------------------------------------------------------------------------
    // 22. Sin valor nutricional en producto
    // ---------------------------------------------------------------------------

    public function test_producto_sin_valor_nutricional_rechazado()
    {
        $unit     = $this->unit();
        $product  = $this->product();
        $nutrient = $this->nutrient(['code' => 'yodo', 'unit_id' => $unit->id]);

        $this->actingAs($this->admin())->postJson(
            '/api/v1/admin/products/' . $product->id . '/nutrients',
            ['nutrient_id' => $nutrient->id]
        )->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    // ---------------------------------------------------------------------------
    // 23. Valores negativos en producto
    // ---------------------------------------------------------------------------

    public function test_producto_valor_negativo_rechazado()
    {
        $unit     = $this->unit();
        $product  = $this->product();
        $nutrient = $this->nutrient(['code' => 'cromo', 'unit_id' => $unit->id]);

        $this->actingAs($this->admin())->postJson(
            '/api/v1/admin/products/' . $product->id . '/nutrients',
            ['nutrient_id' => $nutrient->id, 'amount_per_100g' => -1]
        )->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    // ---------------------------------------------------------------------------
    // 24. trace_id presente en respuestas
    // ---------------------------------------------------------------------------

    public function test_trace_id_presente()
    {
        $admin = $this->admin();
        $unit  = $this->unit();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/nutrients', [
            'code'    => 'fosforo',
            'name'    => 'Fósforo',
            'unit_id' => $unit->id,
        ]);

        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('trace_id'));
    }
}
