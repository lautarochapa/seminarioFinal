<?php

namespace Tests\Feature\Api\V1\Cities;

use App\AuditLog;
use App\City;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CitiesTest extends TestCase
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

    private function city(array $data = [])
    {
        return City::create(array_merge([
            'name'     => 'Ciudad ' . uniqid(),
            'province' => 'Provincia Test',
            'country'  => 'Argentina',
            'status'   => 'active',
        ], $data));
    }

    // Test 1: admin sin autenticación → 401
    public function test_admin_sin_autenticacion_retorna_401()
    {
        $this->getJson('/api/v1/admin/cities')->assertStatus(401);
    }

    // Test 2: admin sin permiso → 403
    public function test_admin_sin_permiso_retorna_403()
    {
        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/cities')
            ->assertStatus(403);
    }

    // Test 3: alta exitosa → 201
    public function test_alta_exitosa_retorna_201()
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/cities', [
                'name'      => 'San Carlos de Bariloche',
                'province'  => 'Río Negro',
                'country'   => 'Argentina',
                'latitude'  => -41.1334,
                'longitude' => -71.3103,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'San Carlos de Bariloche')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonStructure(['data', 'trace_id']);

        $this->assertDatabaseHas('cities', [
            'name'     => 'San Carlos de Bariloche',
            'province' => 'Río Negro',
            'status'   => 'active',
        ]);
    }

    // Test 4: duplicado rechazado → 409
    public function test_duplicado_rechazado_retorna_409()
    {
        $admin = $this->admin();

        $this->city(['name' => 'Neuquén', 'province' => 'Neuquén', 'country' => 'Argentina']);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/cities', [
                'name'     => 'Neuquén',
                'province' => 'Neuquén',
                'country'  => 'Argentina',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CITY_NAME_ALREADY_EXISTS');
    }

    // Test 5: actualización parcial → 200
    public function test_actualizacion_parcial_retorna_200()
    {
        $admin = $this->admin();
        $city  = $this->city(['name' => 'Mendoza', 'province' => 'Mendoza']);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/cities/' . $city->id, [
                'latitude' => -32.8895,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Mendoza')
            ->assertJsonPath('data.province', 'Mendoza');
    }

    // Test 6: baja lógica → 200, status=inactive
    public function test_baja_logica_retorna_200()
    {
        $admin = $this->admin();
        $city  = $this->city(['name' => 'Rosario']);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/admin/cities/' . $city->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('cities', ['id' => $city->id, 'status' => 'inactive']);
    }

    // Test 7: restore → 200, status=active
    public function test_restore_retorna_200()
    {
        $admin = $this->admin();
        $city  = $this->city(['name' => 'Córdoba', 'status' => 'inactive']);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/cities/' . $city->id . '/restore')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('cities', ['id' => $city->id, 'status' => 'active']);
    }

    // Test 8: catálogo devuelve solo activas y ordenadas por nombre
    public function test_catalogo_solo_activas_y_ordenadas()
    {
        $this->city(['name' => 'Zapala',   'status' => 'active']);
        $this->city(['name' => 'Andorra',  'status' => 'active']);
        $this->city(['name' => 'Inactiva', 'status' => 'inactive']);

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/cities');

        $response->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name')->values()->all();

        $this->assertContains('Zapala', $names);
        $this->assertContains('Andorra', $names);
        $this->assertNotContains('Inactiva', $names);
        $this->assertEquals(array_values(sort_strings($names)), $names);
    }

    // Test 9: relaciones históricas preservadas tras baja lógica
    public function test_relaciones_historicas_preservadas()
    {
        $admin = $this->admin();
        $city  = $this->city(['name' => 'Ciudad con sucursal']);

        $chainId = DB::table('supermarket_chains')->insertGetId([
            'name'       => 'Cadena Test',
            'code'       => 'test_' . uniqid(),
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('supermarket_branches')->insertGetId([
            'supermarket_chain_id' => $chainId,
            'city_id'              => $city->id,
            'name'                 => 'Sucursal Test',
            'address'              => 'Calle 123',
            'status'               => 'active',
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/admin/cities/' . $city->id)
            ->assertStatus(200);

        $this->assertDatabaseHas('cities', ['id' => $city->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('supermarket_branches', ['id' => $branchId, 'city_id' => $city->id]);
    }

    // Test 10: auditoría registrada
    public function test_auditoria_registrada()
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/cities', [
                'name'     => 'Ciudad Auditada',
                'province' => 'Provincia',
            ])
            ->assertStatus(201);

        $this->assertTrue(
            AuditLog::where('entity_name', 'cities')
                ->where('action', 'city.created')
                ->exists()
        );
    }
}

function sort_strings(array $arr): array
{
    sort($arr);
    return $arr;
}
