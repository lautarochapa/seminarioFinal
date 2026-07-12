<?php

namespace Tests\Feature\Api\V1\ProductReports;

use App\AuditLog;
use App\Brand;
use App\Ingredient;
use App\Product;
use App\ProductCategory;
use App\ProductReport;
use App\Role;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductReportsTest extends TestCase
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

    private function product(array $data = [])
    {
        $brand = Brand::create([
            'nombre' => 'Marca ' . uniqid(),
            'name'   => 'Marca ' . uniqid(),
            'normalized_name' => 'marca_' . uniqid(),
            'status' => 'active',
            'padre'  => 0,
        ]);

        $category = ProductCategory::create([
            'name'   => 'Cat ' . uniqid(),
            'status' => 'active',
        ]);

        $unit = UnitMeasure::create([
            'code'   => 'u_' . uniqid(),
            'name'   => 'Unidad',
            'type'   => 'mass',
            'symbol' => 'u',
            'status' => 'active',
        ]);

        $ingredient = Ingredient::create([
            'name'             => 'Ing ' . uniqid(),
            'normalized_name'  => 'ing_' . uniqid(),
            'base_unit_id'     => $unit->id,
            'is_generic'       => true,
            'is_preparation'   => false,
            'is_supplement'    => false,
            'status'           => 'active',
        ]);

        $name = $data['name'] ?? 'Producto ' . uniqid();

        return Product::create(array_merge([
            'nombre'          => $name,
            'brand_id'        => $brand->id,
            'codigo'          => 'BC' . uniqid(),
            'img'             => 'product.png',
            'habilitado'      => 1,
            'supply_id'       => 0,
            'name'            => $name,
            'normalized_name' => strtolower($name),
            'category_id'     => $category->id,
            'ingredient_id'   => $ingredient->id,
            'default_unit_id' => $unit->id,
            'net_quantity'    => '1.0000',
            'is_verified'     => false,
            'is_active'       => true,
            'status'          => 'active',
        ], Arr::except($data, ['name'])));
    }

    private function report(Product $product, User $user, array $data = [])
    {
        return ProductReport::create(array_merge([
            'user_id'     => $user->id,
            'product_id'  => $product->id,
            'report_type' => ProductReport::TYPE_OTHER,
            'description' => 'Descripción de prueba',
            'status'      => ProductReport::STATUS_OPEN,
        ], $data));
    }

    // Test 1: creación sin autenticación → 401
    public function test_crear_reporte_sin_autenticacion_retorna_401()
    {
        $product = $this->product();

        $this->postJson('/api/v1/products/' . $product->id . '/reports', [
            'type'        => 'other',
            'description' => 'Test',
        ])->assertStatus(401);
    }

    // Test 2: creación exitosa → 201
    public function test_crear_reporte_exitoso_retorna_201()
    {
        $user    = factory(User::class)->create();
        $product = $this->product();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/products/' . $product->id . '/reports', [
                'type'        => 'incorrect_price',
                'description' => 'El precio publicado no coincide con el real.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.report_type', 'incorrect_price')
            ->assertJsonPath('data.status', ProductReport::STATUS_OPEN)
            ->assertJsonStructure(['data', 'trace_id']);

        $this->assertDatabaseHas('product_reports', [
            'product_id'  => $product->id,
            'user_id'     => $user->id,
            'report_type' => 'incorrect_price',
            'status'      => ProductReport::STATUS_OPEN,
        ]);
    }

    // Test 3: producto inexistente → 404
    public function test_crear_reporte_producto_inexistente_retorna_404()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user)
            ->postJson('/api/v1/products/999999/reports', [
                'type' => 'other',
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'PRODUCT_NOT_FOUND');
    }

    // Test 4: tipo de reporte inválido → 422
    // (price_id no existe en la tabla; se reemplaza con validación de tipo)
    public function test_tipo_invalido_retorna_422()
    {
        $user    = factory(User::class)->create();
        $product = $this->product();

        $this->actingAs($user)
            ->postJson('/api/v1/products/' . $product->id . '/reports', [
                'type' => 'tipo_que_no_existe',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    // Test 5: campos internos rechazados (user_id, status, resolved_by ignorados del body)
    public function test_campos_internos_ignorados_del_body()
    {
        $user    = factory(User::class)->create();
        $admin   = $this->admin();
        $product = $this->product();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/products/' . $product->id . '/reports', [
                'type'        => 'other',
                'user_id'     => $admin->id,
                'status'      => 'resolved',
                'resolved_by' => $admin->id,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('product_reports', [
            'product_id' => $product->id,
            'user_id'    => $user->id,
            'status'     => ProductReport::STATUS_OPEN,
        ]);

        $this->assertDatabaseMissing('product_reports', [
            'user_id' => $admin->id,
            'status'  => 'resolved',
        ]);
    }

    // Test 6: admin sin permiso → 403
    public function test_listado_sin_permiso_retorna_403()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user)
            ->getJson('/api/v1/admin/product-reports')
            ->assertStatus(403);
    }

    // Test 7: listado con filtros básicos
    public function test_listado_con_filtros_basicos()
    {
        $admin   = $this->admin();
        $user    = factory(User::class)->create();
        $product = $this->product();

        $this->report($product, $user, ['report_type' => 'incorrect_price', 'status' => 'open']);
        $this->report($product, $user, ['report_type' => 'other', 'status' => 'resolved', 'resolved_by' => $admin->id, 'resolved_at' => now()]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/product-reports?status=open&type=incorrect_price');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data', 'meta', 'links', 'trace_id',
            ])
            ->assertJsonPath('meta.total', 1);
    }

    // Test 8: resolución exitosa → 200
    public function test_resolver_reporte_retorna_200()
    {
        $admin   = $this->admin();
        $user    = factory(User::class)->create();
        $product = $this->product();
        $report  = $this->report($product, $user);

        $response = $this->actingAs($admin)
            ->patchJson('/api/v1/admin/product-reports/' . $report->id . '/resolve', [
                'status' => 'resolved',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'resolved');

        $this->assertDatabaseHas('product_reports', [
            'id'     => $report->id,
            'status' => 'resolved',
        ]);
    }

    // Test 9: administrador y fecha generados por servidor
    public function test_resolver_asigna_admin_y_fecha_del_servidor()
    {
        $admin   = $this->admin();
        $user    = factory(User::class)->create();
        $product = $this->product();
        $report  = $this->report($product, $user);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/product-reports/' . $report->id . '/resolve', [
                'status'      => 'resolved',
                'resolved_by' => 999,
                'resolved_at' => '2000-01-01 00:00:00',
            ])
            ->assertStatus(200);

        $updated = ProductReport::find($report->id);
        $this->assertEquals($admin->id, $updated->resolved_by);
        $this->assertNotNull($updated->resolved_at);
        $this->assertNotEquals('2000-01-01 00:00:00', (string) $updated->resolved_at);
    }

    // Test 10: reporte inexistente → 404
    public function test_resolver_reporte_inexistente_retorna_404()
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/product-reports/999999/resolve', [
                'status' => 'resolved',
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'PRODUCT_REPORT_NOT_FOUND');
    }

    // Test 11: reporte ya resuelto → 409
    public function test_resolver_reporte_ya_resuelto_retorna_409()
    {
        $admin   = $this->admin();
        $user    = factory(User::class)->create();
        $product = $this->product();
        $report  = $this->report($product, $user, [
            'status'      => ProductReport::STATUS_RESOLVED,
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/product-reports/' . $report->id . '/resolve', [
                'status' => 'resolved',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'REPORT_ALREADY_RESOLVED');
    }

    // Test 12: auditoría registrada
    public function test_auditoria_registrada_al_crear_y_resolver()
    {
        $admin   = $this->admin();
        $user    = factory(User::class)->create();
        $product = $this->product();

        $this->actingAs($user)
            ->postJson('/api/v1/products/' . $product->id . '/reports', [
                'type'        => 'other',
                'description' => 'Problema detectado',
            ])
            ->assertStatus(201);

        $this->assertTrue(
            AuditLog::where('entity_name', 'product_reports')
                ->where('action', 'product_report.created')
                ->exists()
        );

        $report = ProductReport::where('product_id', $product->id)->first();

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/product-reports/' . $report->id . '/resolve', [
                'status' => 'resolved',
            ])
            ->assertStatus(200);

        $this->assertTrue(
            AuditLog::where('entity_name', 'product_reports')
                ->where('action', 'product_report.resolved')
                ->exists()
        );
    }
}
