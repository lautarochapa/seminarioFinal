<?php

namespace Tests\Feature\Api\V1\Supermarkets;

use App\AuditLog;
use App\Role;
use App\SupermarketChain;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupermarketsTest extends TestCase
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
            'name'        => $name,
            'code'        => Str::slug($name, '_') . '_' . Str::random(4),
            'website_url' => $data['website_url'] ?? null,
            'status'      => $data['status'] ?? 'active',
        ]);
    }

    // admin sin autenticación → 401
    public function test_admin_sin_autenticacion_retorna_401()
    {
        $this->getJson('/api/v1/admin/supermarket-chains')->assertStatus(401);
    }

    //  admin sin permiso → 403
    public function test_admin_sin_permiso_retorna_403()
    {
        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/supermarket-chains')
            ->assertStatus(403);
    }

    //  alta exitosa → 201
    public function test_alta_exitosa_retorna_201()
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/supermarket-chains', [
                'name'        => 'Carrefour',
                'website_url' => 'https://www.carrefour.com.ar',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Carrefour')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonStructure(['data', 'trace_id']);

        $this->assertDatabaseHas('supermarket_chains', [
            'name'   => 'Carrefour',
            'status' => 'active',
        ]);
    }

    //  duplicado rechazado → 409
    public function test_duplicado_rechazado_retorna_409()
    {
        $admin = $this->admin();
        $this->chain(['name' => 'Dia']);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/supermarket-chains', [
                'name' => 'Dia',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CHAIN_NAME_ALREADY_EXISTS');
    }

    //  actualización parcial → 200
    public function test_actualizacion_parcial_retorna_200()
    {
        $admin = $this->admin();
        $chain = $this->chain(['name' => 'La Anónima']);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/supermarket-chains/' . $chain->id, [
                'website_url' => 'https://www.laanonima.com.ar',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'La Anónima')
            ->assertJsonPath('data.website_url', 'https://www.laanonima.com.ar');
    }

    //  baja lógica → 200, status=inactive
    public function test_baja_logica_retorna_200()
    {
        $admin = $this->admin();
        $chain = $this->chain(['name' => 'Jumbo']);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/admin/supermarket-chains/' . $chain->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('supermarket_chains', [
            'id'     => $chain->id,
            'status' => 'inactive',
        ]);
    }

    //  restore → 200, status=active
    public function test_restore_retorna_200()
    {
        $admin = $this->admin();
        $chain = $this->chain(['name' => 'Coto']);
        $chain->status = 'inactive';
        $chain->save();
        $chain->delete();

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/supermarket-chains/' . $chain->id . '/restore')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('supermarket_chains', [
            'id'     => $chain->id,
            'status' => 'active',
        ]);
    }

    //  catálogo solo activas, ordenadas por nombre
    public function test_catalogo_solo_activas_y_ordenadas()
    {
        $this->chain(['name' => 'Walmart',  'status' => 'active']);
        $this->chain(['name' => 'Atomo',    'status' => 'active']);
        $inactiva = $this->chain(['name' => 'Inactiva', 'status' => 'inactive']);
        $inactiva->delete();

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/supermarkets');

        $response->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name')->values()->all();

        $this->assertContains('Walmart', $names);
        $this->assertContains('Atomo', $names);
        $this->assertNotContains('Inactiva', $names);
        $this->assertEquals($names, collect($names)->sort()->values()->all());
    }

    //  detalle público → 200
    public function test_detalle_publico_retorna_200()
    {
        $chain = $this->chain(['name' => 'ChangoMás', 'website_url' => 'https://www.changomas.com.ar']);

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/supermarkets/' . $chain->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'ChangoMás')
            ->assertJsonPath('data.website_url', 'https://www.changomas.com.ar');
    }

    //  catálogo expone branches_count y logo_url (null hasta que exista columna)
    public function test_catalogo_expone_branches_count_y_logo_url()
    {
        $chain = $this->chain(['name' => 'Vea']);
        $city  = \App\City::create([
            'name'     => 'City ' . uniqid(),
            'province' => 'Prov Test',
            'country'  => 'Argentina',
            'status'   => 'active',
        ]);

        \App\SupermarketBranch::create([
            'supermarket_chain_id' => $chain->id,
            'city_id'              => $city->id,
            'name'                 => 'Sucursal 1',
            'address'              => 'Calle 1',
            'status'               => 'active',
        ]);
        \App\SupermarketBranch::create([
            'supermarket_chain_id' => $chain->id,
            'city_id'              => $city->id,
            'name'                 => 'Sucursal 2',
            'address'              => 'Calle 2',
            'status'               => 'active',
        ]);

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/supermarkets/' . $chain->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.branches_count', 2)
            ->assertJsonPath('data.logo_url', null);
    }

    // auditoría registrada
    public function test_auditoria_registrada()
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/supermarket-chains', [
                'name' => 'Cadena Auditada',
            ])
            ->assertStatus(201);

        $this->assertTrue(
            AuditLog::where('entity_name', 'supermarket_chains')
                ->where('action', 'chain.created')
                ->exists()
        );
    }
}
