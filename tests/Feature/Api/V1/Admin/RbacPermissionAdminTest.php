<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Permission;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RbacPermissionAdminTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser()
    {
        $user = factory(User::class)->create();
        $role = Role::where('code', 'super_admin')->first();
        if ($role) {
            DB::table('user_roles')->insert([
                'user_id'    => $user->id,
                'role_id'    => $role->id,
                'created_at' => now(),
            ]);
        }
        return $user;
    }

    public function test_listar_permisos_exitoso()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/permissions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'code', 'module', 'action', 'status']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links',
                'trace_id',
            ]);
    }

    public function test_listar_permisos_sin_autenticacion()
    {
        $response = $this->getJson('/api/v1/admin/permissions');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_UNAUTHENTICATED');
    }

    public function test_listar_permisos_sin_permiso()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/admin/permissions');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');
    }

    public function test_listar_permisos_paginacion()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/permissions?per_page=5&page=1');

        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('meta.per_page'));
    }

    public function test_listar_permisos_filtro_modulo()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/permissions?module=security');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $perm) {
            $this->assertEquals('security', $perm['module']);
        }
    }

    public function test_listar_permisos_filtro_status()
    {
        $admin = $this->createAdminUser();
        factory(Permission::class)->create(['status' => 'inactive']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/permissions?status=inactive');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $perm) {
            $this->assertEquals('inactive', $perm['status']);
        }
    }

    public function test_trace_id_presente()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/permissions');

        $traceId = $response->json('trace_id');
        $this->assertNotNull($traceId);
        $this->assertRegExp(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $traceId
        );
    }
}