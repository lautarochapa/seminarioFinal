<?php

namespace Tests\Feature\Api\V1\SupermarketBranches;

use App\AuditLog;
use App\City;
use App\Role;
use App\SupermarketBranch;
use App\SupermarketChain;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupermarketBranchesTest extends TestCase
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

    private function chain(array $data = [])
    {
        $name = $data['name'] ?? 'Cadena ' . uniqid();
        return SupermarketChain::create([
            'name'   => $name,
            'code'   => Str::slug($name, '_') . '_' . Str::random(4),
            'status' => $data['status'] ?? 'active',
        ]);
    }

    private function city(array $data = [])
    {
        return City::create([
            'name'     => $data['name'] ?? 'Ciudad ' . uniqid(),
            'province' => $data['province'] ?? 'Provincia Test',
            'country'  => 'Argentina',
            'status'   => $data['status'] ?? 'active',
        ]);
    }

    private function branch(SupermarketChain $chain, City $city, array $data = [])
    {
        return SupermarketBranch::create(array_merge([
            'supermarket_chain_id' => $chain->id,
            'city_id'              => $city->id,
            'name'                 => 'Sucursal ' . uniqid(),
            'address'              => 'Calle Falsa 123',
            'status'               => 'active',
        ], $data));
    }

    // GET admin sin autenticacion retorna 401
    public function test_admin_sin_autenticacion_retorna_401()
    {
        $this->getJson('/api/v1/admin/supermarket-branches')->assertStatus(401);
    }

    // GET admin sin permiso retorna 403
    public function test_admin_sin_permiso_retorna_403()
    {
        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/supermarket-branches')
            ->assertStatus(403);
    }

    // alta exitosa con cadena y ciudad activas retorna 201
    public function test_alta_exitosa_retorna_201()
    {
        $admin = $this->admin();
        $chain = $this->chain(['name' => 'Carrefour Alta']);
        $city  = $this->city(['name' => 'Buenos Aires Alta']);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/supermarket-branches', [
                'supermarket_chain_id' => $chain->id,
                'city_id'              => $city->id,
                'name'                 => 'Sucursal Centro',
                'address'              => 'Av. Corrientes 1234',
                'latitude'             => -34.6037,
                'longitude'            => -58.3816,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Sucursal Centro')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonStructure(['data', 'trace_id']);

        $this->assertDatabaseHas('supermarket_branches', [
            'name'   => 'Sucursal Centro',
            'status' => 'active',
        ]);
    }

    // cadena o ciudad inexistente o inactiva retorna 422
    public function test_cadena_o_ciudad_invalida_retorna_422()
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/supermarket-branches', [
                'supermarket_chain_id' => 9999,
                'city_id'              => 9999,
                'name'                 => 'Sucursal Invalida',
                'address'              => 'Calle Falsa 123',
            ])
            ->assertStatus(422);
    }

    // coordenadas fuera de rango retornan 422
    public function test_coordenadas_invalidas_retornan_422()
    {
        $admin = $this->admin();
        $chain = $this->chain(['name' => 'Cadena Coords']);
        $city  = $this->city(['name' => 'Ciudad Coords']);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/supermarket-branches', [
                'supermarket_chain_id' => $chain->id,
                'city_id'              => $city->id,
                'name'                 => 'Sucursal Invalida',
                'address'              => 'Calle Falsa 123',
                'latitude'             => 200.0,
                'longitude'            => 300.0,
            ])
            ->assertStatus(422);
    }

    // actualizacion parcial retorna 200 con datos actualizados
    public function test_actualizacion_parcial_retorna_200()
    {
        $admin  = $this->admin();
        $chain  = $this->chain(['name' => 'Cadena Patch']);
        $city   = $this->city(['name' => 'Ciudad Patch']);
        $branch = $this->branch($chain, $city, ['name' => 'Sucursal Original']);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/supermarket-branches/' . $branch->id, [
                'address' => 'Nueva Direccion 999',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Sucursal Original')
            ->assertJsonPath('data.address', 'Nueva Direccion 999');
    }

    // baja logica y restore funcionan correctamente
    public function test_baja_logica_y_restore()
    {
        $admin  = $this->admin();
        $chain  = $this->chain(['name' => 'Cadena BajaRestore']);
        $city   = $this->city(['name' => 'Ciudad BajaRestore']);
        $branch = $this->branch($chain, $city, ['name' => 'Sucursal BajaRestore']);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/admin/supermarket-branches/' . $branch->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('supermarket_branches', [
            'id' => $branch->id, 'status' => 'inactive',
        ]);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/supermarket-branches/' . $branch->id . '/restore')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('supermarket_branches', [
            'id' => $branch->id, 'status' => 'active',
        ]);
    }

    // catalogo devuelve solo sucursales activas
    public function test_catalogo_solo_activas()
    {
        $chain    = $this->chain(['name' => 'Cadena Cat']);
        $city     = $this->city(['name' => 'Ciudad Cat']);
        $activa   = $this->branch($chain, $city, ['name' => 'Activa']);
        $inactiva = $this->branch($chain, $city, ['name' => 'Inactiva', 'status' => 'inactive']);
        $inactiva->delete();

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/supermarket-branches');

        $response->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name')->values()->all();

        $this->assertContains('Activa', $names);
        $this->assertNotContains('Inactiva', $names);
    }

    // filtros por ciudad y cadena devuelven resultados correctos
    public function test_filtros_por_ciudad_y_cadena()
    {
        $chainA = $this->chain(['name' => 'Cadena A Filtro']);
        $chainB = $this->chain(['name' => 'Cadena B Filtro']);
        $cityX  = $this->city(['name' => 'Ciudad X Filtro']);
        $cityY  = $this->city(['name' => 'Ciudad Y Filtro']);

        $branchAX = $this->branch($chainA, $cityX, ['name' => 'AX']);
        $branchBY = $this->branch($chainB, $cityY, ['name' => 'BY']);

        $user = factory(User::class)->create();

        $responseCity = $this->actingAs($user)
            ->getJson('/api/v1/supermarket-branches?city_id=' . $cityX->id);

        $namesCity = collect($responseCity->json('data'))->pluck('name')->all();
        $this->assertContains('AX', $namesCity);
        $this->assertNotContains('BY', $namesCity);

        $responseChain = $this->actingAs($user)
            ->getJson('/api/v1/supermarket-branches?chain_id=' . $chainB->id);

        $namesChain = collect($responseChain->json('data'))->pluck('name')->all();
        $this->assertContains('BY', $namesChain);
        $this->assertNotContains('AX', $namesChain);
    }

    // nearby filtra por radio y ordena por distancia con distance_km en respuesta
    public function test_nearby_filtra_y_ordena_por_distancia()
    {
        $chain = $this->chain(['name' => 'Cadena Nearby']);
        $city  = $this->city(['name' => 'Ciudad Nearby']);

        // Punto de referencia: Bariloche (-41.1334, -71.3103)
        // Sucursal cercana: ~1.8km al sur
        $this->branch($chain, $city, [
            'name'      => 'Cercana',
            'latitude'  => -41.1500,
            'longitude' => -71.3103,
        ]);

        // Sucursal lejana: ~40km al sur
        $this->branch($chain, $city, [
            'name'      => 'Lejana',
            'latitude'  => -41.5000,
            'longitude' => -71.3103,
        ]);

        // Sin coordenadas, debe excluirse
        $this->branch($chain, $city, ['name' => 'SinCoords']);

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/supermarket-branches/nearby?lat=-41.1334&lng=-71.3103&radius=5');

        $response->assertStatus(200);

        $data = $response->json('data');
        $names = collect($data)->pluck('name')->all();

        $this->assertContains('Cercana', $names);
        $this->assertNotContains('Lejana', $names);
        $this->assertNotContains('SinCoords', $names);

        $this->assertArrayHasKey('distance_km', $data[0]);
        $this->assertLessThanOrEqual(5.0, $data[0]['distance_km']);
    }

    // parametros nearby invalidos retornan 422
    public function test_parametros_nearby_invalidos_retornan_422()
    {
        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/supermarket-branches/nearby?lat=999&lng=999&radius=-1')
            ->assertStatus(422);
    }

    // auditoria registrada en alta
    public function test_auditoria_registrada()
    {
        $admin = $this->admin();
        $chain = $this->chain(['name' => 'Cadena Audit']);
        $city  = $this->city(['name' => 'Ciudad Audit']);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/supermarket-branches', [
                'supermarket_chain_id' => $chain->id,
                'city_id'              => $city->id,
                'name'                 => 'Sucursal Auditada',
                'address'              => 'Calle Auditada 123',
            ])
            ->assertStatus(201);

        $this->assertTrue(
            AuditLog::where('entity_name', 'supermarket_branches')
                ->where('action', 'branch.created')
                ->exists()
        );
    }
}
