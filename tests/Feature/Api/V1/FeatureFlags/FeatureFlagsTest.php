<?php

namespace Tests\Feature\Api\V1\FeatureFlags;

use App\AuditLog;
use App\FeatureFlag;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeatureFlagsTest extends TestCase
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

    private function flag(array $data = [])
    {
        return FeatureFlag::create(array_merge([
            'key' => 'feature_'.uniqid(),
            'name' => 'Feature '.uniqid(),
            'description' => null,
            'enabled' => false,
        ], $data));
    }

    public function test_requiere_autenticacion()
    {
        $this->getJson('/api/v1/admin/feature-flags')->assertStatus(401);
    }

    public function test_requiere_permiso()
    {
        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/feature-flags')
            ->assertStatus(403);
    }

    public function test_listado()
    {
        $admin = $this->admin();
        $this->flag(['key' => 'module.ai', 'name' => 'IA', 'enabled' => true]);

        $this->actingAs($admin)->getJson('/api/v1/admin/feature-flags')
            ->assertStatus(200)
            ->assertJsonPath('data.0.key', 'module.ai')
            ->assertJsonPath('data.0.enabled', true)
            ->assertJsonStructure(['data', 'trace_id']);
    }

    public function test_actualizacion_valida()
    {
        $admin = $this->admin();
        $this->flag(['key' => 'module.scraping', 'enabled' => false]);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/feature-flags/module.scraping', ['enabled' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.enabled', true);

        $this->assertDatabaseHas('feature_flags', [
            'key' => 'module.scraping',
            'enabled' => true,
        ]);
    }

    public function test_valor_invalido()
    {
        $admin = $this->admin();
        $this->flag(['key' => 'auth.google']);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/feature-flags/auth.google', ['enabled' => 'yes'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'FEATURE_FLAG_INVALID_VALUE');
    }

    public function test_clave_inexistente()
    {
        $this->actingAs($this->admin())
            ->patchJson('/api/v1/admin/feature-flags/missing.flag', ['enabled' => true])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'FEATURE_FLAG_NOT_FOUND');
    }

    public function test_cache_se_limpia_al_actualizar()
    {
        $admin = $this->admin();
        $this->flag(['key' => 'module.ai', 'enabled' => false]);
        Cache::forever('feature_flags.module.ai', false);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/feature-flags/module.ai', ['enabled' => true])
            ->assertStatus(200);

        $this->assertFalse(Cache::has('feature_flags.module.ai'));
    }

    public function test_auditoria()
    {
        $admin = $this->admin();
        $this->flag(['key' => 'module.scraping', 'enabled' => false]);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/feature-flags/module.scraping', ['enabled' => true])
            ->assertStatus(200);

        $this->assertTrue(AuditLog::where('entity_name', 'feature_flags')
            ->where('action', 'feature-flag.updated')
            ->exists());
    }
}
