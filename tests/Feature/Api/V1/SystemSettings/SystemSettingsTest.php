<?php

namespace Tests\Feature\Api\V1\SystemSettings;

use App\AuditLog;
use App\Role;
use App\SystemSetting;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
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

    private function setting(array $data = [])
    {
        return SystemSetting::create(array_merge([
            'key' => 'setting_'.uniqid(),
            'value' => 'value',
            'type' => 'string',
            'description' => null,
            'is_public' => false,
        ], $data));
    }

    public function test_requiere_autenticacion()
    {
        $this->getJson('/api/v1/admin/settings')->assertStatus(401);
    }

    public function test_requiere_permiso()
    {
        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/settings')
            ->assertStatus(403);
    }

    public function test_listado()
    {
        $admin = $this->admin();
        $this->setting(['key' => 'app.mode', 'value' => 'demo', 'type' => 'string']);

        $this->actingAs($admin)->getJson('/api/v1/admin/settings')
            ->assertStatus(200)
            ->assertJsonPath('data.0.key', 'app.mode')
            ->assertJsonStructure(['data', 'meta', 'links', 'trace_id']);
    }

    public function test_clave_inexistente()
    {
        $this->actingAs($this->admin())
            ->patchJson('/api/v1/admin/settings/missing.key', ['value' => 'x'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'SYSTEM_SETTING_NOT_FOUND');
    }

    public function test_actualizacion_valida()
    {
        $admin = $this->admin();
        $this->setting(['key' => 'limits.max_items', 'value' => '10', 'type' => 'integer']);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/settings/limits.max_items', ['value' => 25])
            ->assertStatus(200)
            ->assertJsonPath('data.value', 25);

        $this->assertDatabaseHas('system_settings', [
            'key' => 'limits.max_items',
            'value' => '25',
        ]);
    }

    public function test_tipo_invalido()
    {
        $admin = $this->admin();
        $this->setting(['key' => 'feature.enabled', 'value' => 'true', 'type' => 'boolean']);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/settings/feature.enabled', ['value' => 'maybe'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SYSTEM_SETTING_INVALID_VALUE');
    }

    public function test_secreto_oculto()
    {
        $admin = $this->admin();
        $this->setting(['key' => 'oauth.client_secret', 'value' => 'plain-secret', 'type' => 'string']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/settings');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.value', '[REDACTED]')
            ->assertJsonMissing(['plain-secret']);
    }

    public function test_auditoria()
    {
        $admin = $this->admin();
        $this->setting(['key' => 'app.title', 'value' => 'Old', 'type' => 'string']);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/settings/app.title', ['value' => 'New'])
            ->assertStatus(200);

        $this->assertTrue(AuditLog::where('entity_name', 'system_settings')
            ->where('action', 'system-setting.updated')
            ->exists());
    }
}
