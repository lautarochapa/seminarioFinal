<?php

namespace Tests\Feature\Api\V1\AiFoundation;

use App\AuditLog;
use App\FeatureFlag;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AiFoundationTest extends TestCase
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

    private function aiFlag($enabled = false)
    {
        return FeatureFlag::updateOrCreate(
            ['key' => 'module.ai'],
            ['name' => 'IA', 'description' => null, 'enabled' => $enabled]
        );
    }

    public function test_requiere_autenticacion()
    {
        $this->getJson('/api/v1/admin/feature-flags/ai_enabled')->assertStatus(401);
    }

    public function test_requiere_permiso()
    {
        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/feature-flags/ai_enabled')
            ->assertStatus(403);
    }

    public function test_lectura_del_flag()
    {
        $admin = $this->admin();
        $this->aiFlag(true);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/feature-flags/ai_enabled')
            ->assertStatus(200)
            ->assertJsonPath('data.key', 'module.ai')
            ->assertJsonPath('data.enabled', true);
    }

    public function test_flag_desactivado_rechaza_sugerencia()
    {
        $admin = $this->admin();
        $this->aiFlag(false);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/ai/test-suggestion', ['context' => 'stock bajo'])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'AI_FEATURE_DISABLED');
    }

    public function test_sugerencia_fake()
    {
        $admin = $this->admin();
        $this->aiFlag(true);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/ai/test-suggestion', ['context' => 'stock bajo'])
            ->assertStatus(200)
            ->assertJsonPath('data.provider', 'fake')
            ->assertJsonPath('data.suggestion', 'Sugerencia simulada para: stock bajo');
    }

    public function test_validacion()
    {
        $admin = $this->admin();
        $this->aiFlag(true);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/ai/test-suggestion', ['context' => ''])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_auditoria()
    {
        $admin = $this->admin();
        $this->aiFlag(true);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/ai/test-suggestion', ['context' => 'menu semanal'])
            ->assertStatus(200);

        $this->assertTrue(AuditLog::where('entity_name', 'ai_foundation')
            ->where('action', 'ai.test_suggestion')
            ->exists());
    }
}
