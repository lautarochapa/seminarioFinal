<?php

namespace Tests\Feature\Api\V1\DemoScenarios;

use App\DemoScenario;
use App\Permission;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoScenariosTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(string $code): User
    {
        $user = factory(User::class)->create();
        $perm = Permission::firstOrCreate(
            ['code' => $code],
            ['module' => 'demo', 'action' => 'manage', 'status' => 'active']
        );
        $role = Role::create(['code' => 'role_' . uniqid(), 'name' => 'Role', 'status' => 'active']);
        DB::table('role_permissions')->insert(['role_id' => $role->id, 'permission_id' => $perm->id]);
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'created_at' => now()]);
        return $user;
    }

    private function scenario(array $overrides = []): DemoScenario
    {
        return DemoScenario::create(array_merge([
            'name'   => 'Escenario ' . uniqid(),
            'status' => 'active',
        ], $overrides));
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/v1/admin/demo-scenarios')->assertStatus(401);
        $this->getJson('/api/v1/demo-scenarios')->assertStatus(401);
    }

    public function test_user_without_permission_is_forbidden()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user)->getJson('/api/v1/admin/demo-scenarios')->assertStatus(403);
        $this->actingAs($user)->getJson('/api/v1/demo-scenarios')->assertStatus(403);
    }

    public function test_admin_can_create_scenario_and_it_is_audited()
    {
        $admin = $this->userWithPermission('catalog.manage');

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/demo-scenarios', [
                'name'        => 'Flujo de compras',
                'description' => 'Muestra el flujo de compras en familia',
                'status'      => 'active',
            ])
            ->assertStatus(201);

        $this->assertEquals('Flujo de compras', $response->json('data.name'));
        $this->assertEquals('active', $response->json('data.status'));
        $this->assertArrayHasKey('trace_id', $response->json());

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $admin->id,
            'action'      => 'demo_scenario_created',
            'entity_name' => 'demo_scenarios',
        ]);
    }

    public function test_duplicate_name_is_rejected()
    {
        $admin = $this->userWithPermission('catalog.manage');
        $this->scenario(['name' => 'Escenario unico']);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/demo-scenarios', ['name' => 'Escenario unico'])
            ->assertStatus(422);
    }

    public function test_admin_can_update_scenario_partially()
    {
        $admin    = $this->userWithPermission('catalog.manage');
        $scenario = $this->scenario(['name' => 'Original', 'status' => 'active']);

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/demo-scenarios/{$scenario->id}", ['status' => 'inactive'])
            ->assertStatus(200);

        $this->assertEquals('inactive', $response->json('data.status'));
        $this->assertEquals('Original', $response->json('data.name'));
    }

    public function test_admin_can_soft_delete_scenario()
    {
        $admin    = $this->userWithPermission('catalog.manage');
        $scenario = $this->scenario();

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/demo-scenarios/{$scenario->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('demo_scenarios', ['id' => $scenario->id]);
    }

    public function test_public_list_returns_only_active_scenarios()
    {
        $user = $this->userWithPermission('demo_scenarios.read');
        $this->scenario(['status' => 'active']);
        $this->scenario(['status' => 'active']);
        $this->scenario(['status' => 'inactive']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/demo-scenarios')
            ->assertStatus(200);

        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_public_detail_returns_active_scenario()
    {
        $user     = $this->userWithPermission('demo_scenarios.read');
        $scenario = $this->scenario(['name' => 'Demo familias', 'description' => 'Ejemplo']);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/demo-scenarios/{$scenario->id}")
            ->assertStatus(200);

        $this->assertEquals('Demo familias', $response->json('data.name'));
        $this->assertArrayNotHasKey('demo_user_id', $response->json('data'));
    }

    public function test_inactive_scenario_not_visible_in_public_detail()
    {
        $user     = $this->userWithPermission('demo_scenarios.read');
        $scenario = $this->scenario(['status' => 'inactive']);

        $this->actingAs($user)
            ->getJson("/api/v1/demo-scenarios/{$scenario->id}")
            ->assertStatus(404);
    }

    public function test_admin_list_includes_inactive_scenarios()
    {
        $admin = $this->userWithPermission('catalog.manage');
        $this->scenario(['status' => 'active']);
        $this->scenario(['status' => 'inactive']);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/demo-scenarios')
            ->assertStatus(200);

        $this->assertEquals(2, $response->json('meta.total'));
    }
}
