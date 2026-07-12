<?php

namespace Tests\Feature\Api\V1\Brands;

use App\AuditLog;
use App\Brand;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BrandsTest extends TestCase
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

    private function brand(array $data = [])
    {
        $name = $data['name'] ?? 'Marca '.uniqid();

        return Brand::create(array_merge([
            'nombre' => $name,
            'name' => $name,
            'normalized_name' => $data['normalized_name'] ?? strtolower(str_replace(' ', ' ', trim($name))),
            'status' => 'active',
            'padre' => 0,
        ], $data));
    }

    public function test_admin_autorizacion()
    {
        $this->getJson('/api/v1/admin/brands')->assertStatus(401);

        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/brands')
            ->assertStatus(403);
    }

    public function test_alta_normalizacion_detalle_y_actualizacion()
    {
        $admin = $this->admin();

        $create = $this->actingAs($admin)->postJson('/api/v1/admin/brands', [
            'name' => '  La Serenisima  ',
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.name', 'La Serenisima')
            ->assertJsonPath('data.normalized_name', 'la serenisima');

        $id = $create->json('data.id');

        $this->actingAs($admin)->getJson('/api/v1/admin/brands/'.$id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $id);

        $this->actingAs($admin)->patchJson('/api/v1/admin/brands/'.$id, [
            'name' => 'Gallo',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Gallo')
            ->assertJsonPath('data.normalized_name', 'gallo');
    }

    public function test_nombre_duplicado_y_detalle_inexistente()
    {
        $this->brand(['name' => 'Pureza', 'nombre' => 'Pureza', 'normalized_name' => 'pureza']);
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/v1/admin/brands', [
            'name' => ' PUREZA ',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'BRAND_NAME_ALREADY_EXISTS');

        $this->actingAs($admin)->getJson('/api/v1/admin/brands/999999')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'BRAND_NOT_FOUND');
    }

    public function test_baja_restore_y_auditoria()
    {
        $admin = $this->admin();
        $brand = $this->brand(['name' => 'Gallo', 'nombre' => 'Gallo', 'normalized_name' => 'gallo']);

        $this->actingAs($admin)->deleteJson('/api/v1/admin/brands/'.$brand->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertSoftDeleted('brands', ['id' => $brand->id]);

        $this->actingAs($admin)->patchJson('/api/v1/admin/brands/'.$brand->id.'/restore')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $this->assertTrue(AuditLog::where('entity_name', 'brands')->where('action', 'brand.deleted')->exists());
        $this->assertTrue(AuditLog::where('entity_name', 'brands')->where('action', 'brand.restored')->exists());
    }

    public function test_catalogo_solo_activas_y_busqueda()
    {
        $active = $this->brand(['name' => 'Gallo', 'nombre' => 'Gallo', 'normalized_name' => 'gallo']);
        $this->brand(['name' => 'Oculta', 'nombre' => 'Oculta', 'normalized_name' => 'oculta', 'status' => 'inactive']);
        $deleted = $this->brand(['name' => 'Eliminada', 'nombre' => 'Eliminada', 'normalized_name' => 'eliminada']);
        $deleted->delete();

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/brands?search=gall');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonMissing(['normalized_name' => 'oculta'])
            ->assertJsonMissing(['normalized_name' => 'eliminada']);
    }

    public function test_rutas_registradas_en_api()
    {
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/admin/brands', 'GET')));
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/brands', 'GET')));
    }
}
