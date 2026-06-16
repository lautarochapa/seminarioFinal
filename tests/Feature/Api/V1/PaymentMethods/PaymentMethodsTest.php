<?php

namespace Tests\Feature\Api\V1\PaymentMethods;

use App\AuditLog;
use App\PaymentMethod;
use App\Role;
use App\User;
use App\UserPaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentMethodsTest extends TestCase
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

    private function paymentMethod(array $data = [])
    {
        return PaymentMethod::create(array_merge([
            'name'   => 'Metodo ' . uniqid(),
            'type'   => 'credit_card',
            'status' => 'active',
        ], $data));
    }

    public function test_admin_sin_autenticacion_retorna_401()
    {
        $response = $this->getJson('/api/v1/admin/payment-methods');
        $response->assertStatus(401);
    }

    public function test_admin_sin_permiso_retorna_403()
    {
        $user     = $this->regularUser();
        $response = $this->actingAs($user)->getJson('/api/v1/admin/payment-methods');
        $response->assertStatus(403);
    }

    public function test_alta_catalogo_retorna_201_y_auditoria()
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/payment-methods', [
            'name'   => 'Visa Credito',
            'type'   => 'credit_card',
            'issuer' => 'Banco Nacion',
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals('Visa Credito', $data['name']);
        $this->assertEquals('credit_card', $data['type']);
        $this->assertEquals('Banco Nacion', $data['issuer']);
        $this->assertArrayNotHasKey('deleted_at', $data);

        $log = AuditLog::where('action', 'payment_method.created')
            ->where('entity_name', 'payment_methods')
            ->first();
        $this->assertNotNull($log);
        $this->assertEquals($admin->id, $log->user_id);
    }

    public function test_nombre_duplicado_retorna_409()
    {
        $admin = $this->admin();
        $this->paymentMethod(['name' => 'Visa', 'type' => 'credit_card', 'issuer' => 'Galicia']);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/payment-methods', [
            'name'   => 'Visa',
            'type'   => 'credit_card',
            'issuer' => 'Galicia',
        ]);

        $response->assertStatus(409);
    }

    public function test_actualizacion_parcial_retorna_200()
    {
        $admin = $this->admin();
        $pm    = $this->paymentMethod(['name' => 'Metodo original']);

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/payment-methods/{$pm->id}", [
            'name' => 'Metodo actualizado',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Metodo actualizado', $response->json('data.name'));
        $this->assertEquals('credit_card', $response->json('data.type'));
    }

    public function test_baja_logica_retorna_200()
    {
        $admin = $this->admin();
        $pm    = $this->paymentMethod();

        $response = $this->actingAs($admin)->deleteJson("/api/v1/admin/payment-methods/{$pm->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('payment_methods', ['id' => $pm->id]);
        $this->assertEquals('inactive', $response->json('data.status'));
    }

    public function test_restore_retorna_200()
    {
        $admin = $this->admin();
        $pm    = $this->paymentMethod();

        $this->actingAs($admin)->deleteJson("/api/v1/admin/payment-methods/{$pm->id}");

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/payment-methods/{$pm->id}/restore");

        $response->assertStatus(200);
        $this->assertEquals('active', $response->json('data.status'));
        $this->assertDatabaseHas('payment_methods', ['id' => $pm->id, 'deleted_at' => null]);
    }

    public function test_catalogo_devuelve_solo_activos()
    {
        $user    = $this->regularUser();
        $activo  = $this->paymentMethod(['name' => 'Activo']);
        $inactivo = $this->paymentMethod(['name' => 'Inactivo', 'status' => 'inactive']);

        $admin = $this->admin();
        $this->actingAs($admin)->deleteJson("/api/v1/admin/payment-methods/{$activo->id}");

        $eliminado = $activo;

        $response = $this->actingAs($user)->getJson('/api/v1/payment-methods');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($eliminado->id, $ids);
        $this->assertNotContains($inactivo->id, $ids);
    }

    public function test_usuario_lista_solo_sus_metodos()
    {
        $userA = $this->regularUser();
        $userB = $this->regularUser();
        $pm    = $this->paymentMethod();

        UserPaymentMethod::create([
            'user_id'           => $userA->id,
            'payment_method_id' => $pm->id,
            'alias'             => 'Mi tarjeta',
            'status'            => 'active',
        ]);

        $response = $this->actingAs($userB)->getJson('/api/v1/users/me/payment-methods');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_usuario_agrega_metodo_retorna_201()
    {
        $user = $this->regularUser();
        $pm   = $this->paymentMethod();

        $response = $this->actingAs($user)->postJson('/api/v1/users/me/payment-methods', [
            'payment_method_id' => $pm->id,
            'alias'             => 'Visa personal',
        ]);

        $response->assertStatus(201);
        $this->assertEquals($pm->id, $response->json('data.payment_method_id'));
        $this->assertEquals('Visa personal', $response->json('data.alias'));
        $this->assertArrayNotHasKey('user_id', $response->json('data'));

        $log = AuditLog::where('action', 'user_payment_method.added')->first();
        $this->assertNotNull($log);
    }

    public function test_usuario_duplicado_o_metodo_inactivo_rechazado()
    {
        $user = $this->regularUser();
        $pm   = $this->paymentMethod();

        UserPaymentMethod::create([
            'user_id'           => $user->id,
            'payment_method_id' => $pm->id,
            'alias'             => 'Ya existe',
            'status'            => 'active',
        ]);

        $duplicado = $this->actingAs($user)->postJson('/api/v1/users/me/payment-methods', [
            'payment_method_id' => $pm->id,
            'alias'             => 'Otro alias',
        ]);
        $duplicado->assertStatus(409);

        $pmInactivo = $this->paymentMethod(['status' => 'inactive']);
        $inactivo   = $this->actingAs($user)->postJson('/api/v1/users/me/payment-methods', [
            'payment_method_id' => $pmInactivo->id,
        ]);
        $inactivo->assertStatus(422);
    }

    public function test_eliminacion_propia_y_ajeno_rechazado()
    {
        $userA = $this->regularUser();
        $userB = $this->regularUser();
        $pm    = $this->paymentMethod();

        $upm = UserPaymentMethod::create([
            'user_id'           => $userA->id,
            'payment_method_id' => $pm->id,
            'alias'             => 'Mia',
            'status'            => 'active',
        ]);

        $ajeno = $this->actingAs($userB)->deleteJson("/api/v1/users/me/payment-methods/{$upm->id}");
        $ajeno->assertStatus(404);

        $propio = $this->actingAs($userA)->deleteJson("/api/v1/users/me/payment-methods/{$upm->id}");
        $propio->assertStatus(200);
        $this->assertDatabaseHas('user_payment_methods', ['id' => $upm->id, 'status' => 'inactive']);

        $log = AuditLog::where('action', 'user_payment_method.removed')->first();
        $this->assertNotNull($log);
    }
}
