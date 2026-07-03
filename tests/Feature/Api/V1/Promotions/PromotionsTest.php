<?php

namespace Tests\Feature\Api\V1\Promotions;

use App\AuditLog;
use App\City;
use App\Promotion;
use App\Role;
use App\SupermarketBranch;
use App\SupermarketChain;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PromotionsTest extends TestCase
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

    private function regularUser()
    {
        return factory(User::class)->create();
    }

    private function chain(array $data = [])
    {
        $name = $data['name'] ?? 'Chain ' . uniqid();
        return SupermarketChain::create(array_merge([
            'name'   => $name,
            'code'   => Str::slug($name, '_') . '_' . Str::random(4),
            'status' => 'active',
        ], $data));
    }

    private function city()
    {
        return City::create([
            'name'     => 'City ' . uniqid(),
            'province' => 'Prov Test',
            'country'  => 'Argentina',
            'status'   => 'active',
        ]);
    }

    private function branch($chain, $city, array $data = [])
    {
        return SupermarketBranch::create(array_merge([
            'supermarket_chain_id' => $chain->id,
            'city_id'              => $city->id,
            'name'                 => 'Sucursal ' . uniqid(),
            'address'              => 'Calle 123',
            'status'               => 'active',
        ], $data));
    }

    private function promotion(SupermarketChain $chain, array $data = [])
    {
        return Promotion::create(array_merge([
            'supermarket_chain_id' => $chain->id,
            'name'                 => 'Promo ' . uniqid(),
            'discount_type'        => 'percentage',
            'discount_value'       => 10.00,
            'status'               => 'active',
        ], $data));
    }

    public function test_admin_sin_autenticacion_retorna_401()
    {
        $response = $this->getJson('/api/v1/admin/promotions');
        $response->assertStatus(401);
    }

    public function test_admin_sin_permiso_retorna_403()
    {
        $user     = $this->regularUser();
        $response = $this->actingAs($user)->getJson('/api/v1/admin/promotions');
        $response->assertStatus(403);
    }

    public function test_alta_exitosa_retorna_201_y_auditoria()
    {
        $admin = $this->admin();
        $chain = $this->chain();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/promotions', [
            'supermarket_chain_id' => $chain->id,
            'name'                 => 'Descuento Fin de Semana',
            'discount_type'        => 'percentage',
            'discount_value'       => 25,
            'valid_from'           => Carbon::now()->toDateString(),
            'valid_to'             => Carbon::now()->addDays(30)->toDateString(),
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals('Descuento Fin de Semana', $data['name']);
        $this->assertEquals('percentage', $data['discount_type']);
        $this->assertEquals($chain->id, $data['supermarket_chain_id']);

        $log = AuditLog::where('action', 'promotion.created')
            ->where('entity_name', 'promotions')
            ->first();
        $this->assertNotNull($log);
        $this->assertEquals($admin->id, $log->user_id);
    }

    public function test_porcentaje_invalido_retorna_422()
    {
        $admin = $this->admin();
        $chain = $this->chain();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/promotions', [
            'supermarket_chain_id' => $chain->id,
            'name'                 => 'Promo mala',
            'discount_type'        => 'percentage',
            'discount_value'       => 150,
        ]);

        $response->assertStatus(422);
    }

    public function test_fechas_invalidas_retorna_422()
    {
        $admin = $this->admin();
        $chain = $this->chain();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/promotions', [
            'supermarket_chain_id' => $chain->id,
            'name'                 => 'Promo fechas mal',
            'valid_from'           => '2026-12-31',
            'valid_to'             => '2026-01-01',
        ]);

        $response->assertStatus(422);
    }

    public function test_2x1_valida_retorna_201()
    {
        $admin = $this->admin();
        $chain = $this->chain();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/promotions', [
            'supermarket_chain_id' => $chain->id,
            'name'                 => 'Promo 2x1',
            'discount_type'        => 'buy_x_pay_y',
        ]);

        $response->assertStatus(201);
        $this->assertEquals('buy_x_pay_y', $response->json('data.discount_type'));
    }

    public function test_actualizacion_parcial_retorna_200()
    {
        $admin = $this->admin();
        $chain = $this->chain();
        $promo = $this->promotion($chain, ['name' => 'Nombre original']);

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/promotions/{$promo->id}", [
            'name' => 'Nombre actualizado',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Nombre actualizado', $response->json('data.name'));
        $this->assertEquals('percentage', $response->json('data.discount_type'));
    }

    public function test_baja_logica_retorna_200()
    {
        $admin = $this->admin();
        $chain = $this->chain();
        $promo = $this->promotion($chain);

        $response = $this->actingAs($admin)->deleteJson("/api/v1/admin/promotions/{$promo->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('promotions', ['id' => $promo->id]);
        $this->assertEquals('inactive', $response->json('data.status'));
    }

    public function test_restore_retorna_200()
    {
        $admin = $this->admin();
        $chain = $this->chain();
        $promo = $this->promotion($chain);

        $this->actingAs($admin)->deleteJson("/api/v1/admin/promotions/{$promo->id}");

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/promotions/{$promo->id}/restore");

        $response->assertStatus(200);
        $this->assertEquals('active', $response->json('data.status'));
        $this->assertDatabaseHas('promotions', ['id' => $promo->id, 'status' => 'active', 'deleted_at' => null]);
    }

    public function test_sucursal_devuelve_solo_vigentes()
    {
        $admin = $this->admin();
        $chain = $this->chain();
        $city  = $this->city();
        $br    = $this->branch($chain, $city);

        $vigente = $this->promotion($chain, [
            'supermarket_branch_id' => $br->id,
            'name'                  => 'Vigente',
            'valid_from'            => Carbon::now()->subDay()->toDateString(),
            'valid_to'              => Carbon::now()->addDays(30)->toDateString(),
        ]);

        $this->promotion($chain, [
            'supermarket_branch_id' => $br->id,
            'name'                  => 'Vencida',
            'valid_to'              => Carbon::now()->subDay()->toDateString(),
        ]);

        $inactiva = $this->promotion($chain, [
            'supermarket_branch_id' => $br->id,
            'name'                  => 'Inactiva',
            'status'                => 'inactive',
        ]);

        $response = $this->actingAs($admin)->getJson("/api/v1/supermarket-branches/{$br->id}/promotions");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($vigente->id, $ids);
        $this->assertNotContains($inactiva->id, $ids);
    }

    public function test_sucursal_excluye_otras_sucursales()
    {
        $admin  = $this->admin();
        $chainA = $this->chain();
        $chainB = $this->chain();
        $city   = $this->city();
        $brA    = $this->branch($chainA, $city);
        $brB    = $this->branch($chainB, $city);

        $promoB = $this->promotion($chainB, [
            'supermarket_branch_id' => $brB->id,
            'name'                  => 'Promo exclusiva B',
        ]);

        $response = $this->actingAs($admin)->getJson("/api/v1/supermarket-branches/{$brA->id}/promotions");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($promoB->id, $ids);
    }

    public function test_cadena_incluida_cuando_corresponde()
    {
        $admin = $this->admin();
        $chain = $this->chain();
        $city  = $this->city();
        $br    = $this->branch($chain, $city);

        $cadenaPromo = $this->promotion($chain, [
            'supermarket_branch_id' => null,
            'name'                  => 'Promo de cadena',
        ]);

        $otroBranch = $this->branch($chain, $city);
        $sucursalPromo = $this->promotion($chain, [
            'supermarket_branch_id' => $otroBranch->id,
            'name'                  => 'Promo solo de otra sucursal',
        ]);

        $response = $this->actingAs($admin)->getJson("/api/v1/supermarket-branches/{$br->id}/promotions");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($cadenaPromo->id, $ids);
        $this->assertNotContains($sucursalPromo->id, $ids);
    }

    public function test_catalogo_global_sin_autenticacion_retorna_401()
    {
        $this->getJson('/api/v1/promotions')->assertStatus(401);
    }

    public function test_catalogo_global_devuelve_solo_vigentes_por_defecto()
    {
        $user  = $this->regularUser();
        $chain = $this->chain();

        $vigente = $this->promotion($chain, [
            'name'       => 'Vigente',
            'valid_from' => Carbon::now()->subDay()->toDateString(),
            'valid_to'   => Carbon::now()->addDays(30)->toDateString(),
        ]);

        $vencida = $this->promotion($chain, [
            'name'     => 'Vencida',
            'valid_to' => Carbon::now()->subDay()->toDateString(),
        ]);

        $inactiva = $this->promotion($chain, ['name' => 'Inactiva', 'status' => 'inactive']);

        $response = $this->actingAs($user)->getJson('/api/v1/promotions');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta', 'links', 'trace_id']);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($vigente->id, $ids);
        $this->assertNotContains($vencida->id, $ids);
        $this->assertNotContains($inactiva->id, $ids);
    }

    public function test_catalogo_global_filtra_por_cadena()
    {
        $user   = $this->regularUser();
        $chainA = $this->chain();
        $chainB = $this->chain();

        $promoA = $this->promotion($chainA, ['name' => 'Promo A']);
        $this->promotion($chainB, ['name' => 'Promo B']);

        $response = $this->actingAs($user)->getJson('/api/v1/promotions?chain_id=' . $chainA->id);

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEquals([$promoA->id], $ids);
    }

    public function test_catalogo_global_filtra_por_dia()
    {
        $user  = $this->regularUser();
        $chain = $this->chain();

        $lunes = $this->promotion($chain, ['name' => 'Solo lunes', 'day_of_week' => 1]);
        $this->promotion($chain, ['name' => 'Solo martes', 'day_of_week' => 2]);

        $response = $this->actingAs($user)->getJson('/api/v1/promotions?day=1');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($lunes->id, $ids);
    }

    public function test_catalogo_global_active_false_incluye_vencidas()
    {
        $user  = $this->regularUser();
        $chain = $this->chain();

        $vencida = $this->promotion($chain, [
            'name'     => 'Vencida',
            'valid_to' => Carbon::now()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/promotions?active=false');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($vencida->id, $ids);
    }
}
