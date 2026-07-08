<?php

namespace Tests\Feature\Api\V1\ProductRequests;

use App\AuditLog;
use App\Brand;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Product;
use App\ProductBarcode;
use App\ProductRequest;
use App\Role;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductRequestsTest extends TestCase
{
    use RefreshDatabase;

    private function user()
    {
        return factory(User::class)->create();
    }

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

    private function unit(array $data = [])
    {
        return UnitMeasure::create(array_merge([
            'code' => 'u_'.uniqid(),
            'name' => 'Unidad',
            'type' => 'mass',
            'symbol' => 'u',
            'status' => 'active',
        ], $data));
    }

    private function brand(array $data = [])
    {
        $name = $data['name'] ?? 'Marca '.uniqid();

        return Brand::create(array_merge([
            'nombre' => $name,
            'name' => $name,
            'normalized_name' => strtolower($name),
            'status' => 'active',
            'padre' => 0,
        ], $data));
    }

    private function familyGroup(User $user)
    {
        $group = FamilyGroup::create([
            'name' => 'Grupo '.uniqid(),
            'owner_user_id' => $user->id,
            'status' => 'active',
        ]);

        FamilyGroupMember::create([
            'family_group_id' => $group->id,
            'user_id' => $user->id,
            'role_in_group' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $group;
    }

    private function productRequest(User $user, array $data = [])
    {
        $name = $data['name'] ?? 'Producto pedido '.uniqid();

        return ProductRequest::create(array_merge([
            'requested_by_user_id' => $user->id,
            'family_group_id' => null,
            'name' => $name,
            'normalized_name' => strtolower($name),
            'brand' => null,
            'presentation' => null,
            'barcode' => null,
            'unit_id' => null,
            'comment' => null,
            'source' => 'user_request',
            'status' => 'pending',
        ], $data));
    }

    public function test_usuario_crea_solicitud()
    {
        $user = $this->user();
        $group = $this->familyGroup($user);
        $unit = $this->unit(['code' => 'lt', 'name' => 'Litro', 'symbol' => 'l']);

        $response = $this->actingAs($user)->postJson('/api/v1/product-requests', [
            'name' => '  Leche de almendras  ',
            'brand' => 'Marca X',
            'presentation' => '1 litro',
            'barcode' => '7791234567890',
            'unit_id' => $unit->id,
            'family_group_id' => $group->id,
            'comment' => 'La compro seguido',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Leche de almendras')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.barcode', '7791234567890');

        $this->assertDatabaseHas('product_requests', [
            'requested_by_user_id' => $user->id,
            'normalized_name' => 'leche de almendras',
            'status' => 'pending',
        ]);
    }

    public function test_usuario_solo_ve_sus_solicitudes()
    {
        $mine = $this->user();
        $other = $this->user();
        $visible = $this->productRequest($mine, ['name' => 'Mia', 'normalized_name' => 'mia']);
        $this->productRequest($other, ['name' => 'Ajena', 'normalized_name' => 'ajena']);

        $response = $this->actingAs($mine)->getJson('/api/v1/product-requests');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonMissing(['name' => 'Ajena']);
    }

    public function test_admin_lista_pendientes()
    {
        $user = $this->user();
        $pending = $this->productRequest($user, ['status' => 'pending']);
        $this->productRequest($user, ['status' => 'rejected']);

        $response = $this->actingAs($this->admin())->getJson('/api/v1/product-requests?status=pending');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_admin_aprueba_y_crea_producto()
    {
        $admin = $this->admin();
        $user = $this->user();
        $unit = $this->unit(['code' => 'lt', 'name' => 'Litro', 'symbol' => 'l']);
        $brand = $this->brand(['name' => 'Marca X', 'normalized_name' => 'marca x']);
        $request = $this->productRequest($user, [
            'name' => 'Leche de almendras',
            'normalized_name' => 'leche de almendras',
            'barcode' => '7790000001000',
            'unit_id' => $unit->id,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/product-requests/'.$request->id.'/approve', [
            'brand_id' => $brand->id,
            'default_unit_id' => $unit->id,
            'net_quantity' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('products', [
            'name' => 'Leche de almendras',
            'brand_id' => $brand->id,
            'default_unit_id' => $unit->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('product_barcodes', ['barcode' => '7790000001000', 'status' => 'active']);
        $this->assertTrue(AuditLog::where('action', 'product_request_approved')->exists());
    }

    public function test_admin_rechaza()
    {
        $request = $this->productRequest($this->user());

        $this->actingAs($this->admin())->postJson('/api/v1/product-requests/'.$request->id.'/reject', [
            'review_notes' => 'No corresponde',
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.review_notes', 'No corresponde');
    }

    public function test_doble_aprobacion_devuelve_409()
    {
        $admin = $this->admin();
        $unit = $this->unit();
        $request = $this->productRequest($this->user(), [
            'name' => 'Producto unico',
            'normalized_name' => 'producto unico',
            'unit_id' => $unit->id,
        ]);

        $this->actingAs($admin)->postJson('/api/v1/product-requests/'.$request->id.'/approve', [
            'default_unit_id' => $unit->id,
        ])->assertStatus(200);

        $this->actingAs($admin)->postJson('/api/v1/product-requests/'.$request->id.'/approve', [
            'default_unit_id' => $unit->id,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'PRODUCT_REQUEST_ALREADY_REVIEWED');
    }

    public function test_duplicado_por_barcode_rechazado()
    {
        $product = Product::create([
            'nombre' => 'Existente',
            'brand_id' => 0,
            'codigo' => '7790000002000',
            'img' => '',
            'habilitado' => 1,
            'supply_id' => 0,
            'name' => 'Existente',
            'normalized_name' => 'existente',
            'is_active' => true,
            'status' => 'active',
        ]);
        ProductBarcode::create(['product_id' => $product->id, 'barcode' => '7790000002000', 'type' => null, 'status' => 'active']);

        $this->actingAs($this->user())->postJson('/api/v1/product-requests', [
            'name' => 'Otro producto',
            'barcode' => '7790000002000',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'PRODUCT_BARCODE_ALREADY_EXISTS');
    }

    public function test_duplicado_pendiente_rechazado()
    {
        $user = $this->user();
        $this->productRequest($user, ['normalized_name' => 'leche vegetal', 'name' => 'Leche vegetal']);

        $this->actingAs($user)->postJson('/api/v1/product-requests', [
            'name' => 'Leche vegetal',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'PRODUCT_REQUEST_ALREADY_PENDING');
    }

    public function test_barcode_invalido()
    {
        $this->actingAs($this->user())->postJson('/api/v1/product-requests', [
            'name' => 'Producto',
            'barcode' => 'codigo con espacios',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_usuario_sin_permiso_no_aprueba()
    {
        $request = $this->productRequest($this->user());

        $this->actingAs($this->user())->postJson('/api/v1/product-requests/'.$request->id.'/approve', [
            'default_unit_id' => $this->unit()->id,
        ])->assertStatus(403);
    }

    public function test_auditoria_creacion()
    {
        $user = $this->user();

        $this->actingAs($user)->postJson('/api/v1/product-requests', [
            'name' => 'Producto auditado',
        ])->assertStatus(201);

        $this->assertTrue(AuditLog::where('entity_name', 'product_requests')->where('action', 'product_request_created')->exists());
    }
}
