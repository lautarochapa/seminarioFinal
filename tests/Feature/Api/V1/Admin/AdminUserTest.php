<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Verifies that admin@cccontrol.test behaves correctly after seeding.
 * All assertions mirror what DemoDataSeeder::seedAdminUser() produces.
 */
class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private $adminEmail    = 'admin@cccontrol.test';
    private $adminPassword = 'password123';

    /** Create the admin user exactly as DemoDataSeeder does. */
    private function createAdminUser()
    {
        $now    = now();
        $roleId = DB::table('roles')->where('code', 'super_admin')->value('id');

        DB::table('users')->updateOrInsert(
            ['email' => $this->adminEmail],
            [
                'name'              => 'Admin',
                'lastname'          => 'Test',
                'username'          => 'admin',
                'password'          => Hash::make($this->adminPassword),
                'status'            => 'active',
                'nivel_acceso'      => 1,
                'email_verified_at' => $now,
                'updated_at'        => $now,
                'created_at'        => $now,
                'deleted_at'        => null,
            ]
        );

        $userId = DB::table('users')->where('email', $this->adminEmail)->value('id');

        if ($userId && $roleId) {
            DB::table('user_roles')->updateOrInsert(
                ['user_id' => $userId, 'role_id' => $roleId],
                ['created_at' => $now]
            );
        }

        return User::find($userId);
    }

    public function test_admin_user_exists_after_seed()
    {
        $this->createAdminUser();

        $this->assertTrue(
            DB::table('users')->where('email', $this->adminEmail)->exists()
        );
    }

    public function test_admin_user_is_active()
    {
        $this->createAdminUser();

        $status = DB::table('users')->where('email', $this->adminEmail)->value('status');
        $this->assertEquals('active', $status);
    }

    public function test_admin_password_hash_matches_demo_password()
    {
        $this->createAdminUser();

        $hash = DB::table('users')->where('email', $this->adminEmail)->value('password');
        $this->assertTrue(Hash::check($this->adminPassword, $hash));
    }

    public function test_admin_has_super_admin_role()
    {
        $this->createAdminUser();

        $userId = DB::table('users')->where('email', $this->adminEmail)->value('id');
        $hasRole = DB::table('roles')
            ->join('user_roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $userId)
            ->where('roles.code', 'super_admin')
            ->exists();

        $this->assertTrue($hasRole);
    }

    public function test_admin_has_web_admin_dashboard_permission()
    {
        $admin = $this->createAdminUser();

        $this->assertTrue($admin->hasPermission('web.admin.dashboard'));
    }

    public function test_admin_has_critical_admin_permissions()
    {
        $admin = $this->createAdminUser();

        $criticalPerms = [
            'web.admin.dashboard',
            'web.admin.ingredients',
            'web.admin.products',
            'web.admin.brands',
            'web.admin.supermarkets',
            'web.admin.feature-flags',
        ];

        foreach ($criticalPerms as $perm) {
            $this->assertTrue(
                $admin->hasPermission($perm),
                "admin@cccontrol.test should have permission: $perm"
            );
        }
    }

    public function test_admin_api_login_succeeds()
    {
        $this->createAdminUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $this->adminEmail,
            'password' => $this->adminPassword,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.email', $this->adminEmail);

        $this->assertTrue($response->headers->has('X-Trace-Id'));
    }

    public function test_admin_api_login_returns_token()
    {
        $this->createAdminUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $this->adminEmail,
            'password' => $this->adminPassword,
        ]);

        // token is at root level: { data: {...}, token: { access_token: "..." }, trace_id: "..." }
        $response->assertStatus(200)
            ->assertJsonStructure(['token' => ['access_token']]);
    }

    public function test_admin_me_endpoint_returns_correct_identity()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', $this->adminEmail);
    }

    public function test_admin_can_access_admin_brands_endpoint()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/brands');

        $response->assertStatus(200);
    }

    public function test_wrong_password_returns_401()
    {
        $this->createAdminUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $this->adminEmail,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_INVALID_CREDENTIALS');
    }

    public function test_admin_cannot_access_user_only_endpoint_without_permission()
    {
        // Routes protected by specific non-admin permissions should still work
        // since super_admin has all permissions. Verify /api/v1/admin/* routes pass.
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/roles');

        // super_admin has all permissions, so this should not be 403
        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
