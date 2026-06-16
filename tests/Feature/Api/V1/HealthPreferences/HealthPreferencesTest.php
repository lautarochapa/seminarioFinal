<?php

namespace Tests\Feature\Api\V1\HealthPreferences;

use App\Allergy;
use App\AuditLog;
use App\DietaryRestriction;
use App\HealthCondition;
use App\Role;
use App\User;
use App\UserAllergy;
use App\UserDietaryRestriction;
use App\UserHealthCondition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthPreferencesTest extends TestCase
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

    public function test_admin_sin_auth()
    {
        $this->getJson('/api/v1/admin/dietary-restrictions')->assertStatus(401);
    }

    public function test_admin_sin_permiso()
    {
        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/dietary-restrictions')
            ->assertStatus(403);
    }

    public function test_admin_alta()
    {
        $this->actingAs($this->admin())->postJson('/api/v1/admin/dietary-restrictions', [
            'code' => ' Gluten Free ',
            'name' => 'Gluten free',
            'description' => 'No gluten',
            'status' => 'active',
        ])->assertStatus(201)
            ->assertJsonPath('data.code', 'gluten_free');

        $this->assertDatabaseHas('dietary_restrictions', ['code' => 'gluten_free']);
    }

    public function test_admin_duplicado()
    {
        DietaryRestriction::create(['code' => 'vegan', 'name' => 'Vegan', 'status' => 'active']);

        $this->actingAs($this->admin())->postJson('/api/v1/admin/dietary-restrictions', [
            'code' => 'VEGAN',
            'name' => 'Vegan 2',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'HEALTH_PREFERENCE_CODE_ALREADY_EXISTS');
    }

    public function test_admin_update()
    {
        $item = HealthCondition::create(['code' => 'diabetes', 'name' => 'Diabetes', 'status' => 'active']);

        $this->actingAs($this->admin())->patchJson('/api/v1/admin/health-conditions/'.$item->id, [
            'name' => 'Diabetes updated',
            'status' => 'inactive',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Diabetes updated')
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_admin_baja_y_restore()
    {
        $admin = $this->admin();
        $item = Allergy::create(['code' => 'peanut', 'name' => 'Peanut', 'status' => 'active']);

        $delete = $this->actingAs($admin)->deleteJson('/api/v1/admin/allergies/'.$item->id);
        $delete->assertStatus(200);
        $this->assertNotNull($delete->json('data.deleted_at'));

        $this->actingAs($admin)->patchJson('/api/v1/admin/allergies/'.$item->id.'/restore')
            ->assertStatus(200)
            ->assertJsonPath('data.deleted_at', null);
    }

    public function test_catalogo_solo_activos()
    {
        DietaryRestriction::create(['code' => 'active_cat', 'name' => 'Active', 'status' => 'active']);
        DietaryRestriction::create(['code' => 'inactive_cat', 'name' => 'Inactive', 'status' => 'inactive']);

        $response = $this->actingAs(factory(User::class)->create())->getJson('/api/v1/catalog/dietary-restrictions');

        $response->assertStatus(200);
        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains('active_cat', $codes);
        $this->assertNotContains('inactive_cat', $codes);
    }

    public function test_agregar_y_listar_restriccion()
    {
        $user = factory(User::class)->create();
        $item = DietaryRestriction::create(['code' => 'low_sodium', 'name' => 'Low sodium', 'status' => 'active']);

        $this->actingAs($user)->postJson('/api/v1/users/me/dietary-restrictions', [
            'dietary_restriction_id' => $item->id,
            'notes' => ' personal ',
        ])->assertStatus(201)
            ->assertJsonPath('data.item.id', $item->id)
            ->assertJsonPath('data.notes', 'personal');

        $this->actingAs($user)->getJson('/api/v1/users/me/dietary-restrictions')
            ->assertStatus(200)
            ->assertJsonPath('data.0.item.id', $item->id);
    }

    public function test_agregar_condicion()
    {
        $user = factory(User::class)->create();
        $item = HealthCondition::create(['code' => 'hypertension', 'name' => 'Hypertension', 'status' => 'active']);

        $this->actingAs($user)->postJson('/api/v1/users/me/health-conditions', [
            'health_condition_id' => $item->id,
        ])->assertStatus(201)
            ->assertJsonPath('data.item.id', $item->id);
    }

    public function test_agregar_alergia()
    {
        $user = factory(User::class)->create();
        $item = Allergy::create(['code' => 'milk', 'name' => 'Milk', 'status' => 'active']);

        $this->actingAs($user)->postJson('/api/v1/users/me/allergies', [
            'allergy_id' => $item->id,
            'severity' => 'mild',
        ])->assertStatus(201)
            ->assertJsonPath('data.item.id', $item->id)
            ->assertJsonPath('data.severity', 'mild');
    }

    public function test_duplicado_usuario()
    {
        $user = factory(User::class)->create();
        $item = DietaryRestriction::create(['code' => 'keto', 'name' => 'Keto', 'status' => 'active']);
        UserDietaryRestriction::create(['user_id' => $user->id, 'dietary_restriction_id' => $item->id, 'created_at' => now()]);

        $this->actingAs($user)->postJson('/api/v1/users/me/dietary-restrictions', [
            'dietary_restriction_id' => $item->id,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'USER_HEALTH_PREFERENCE_ALREADY_EXISTS');
    }

    public function test_catalogo_inactivo_rechazado()
    {
        $user = factory(User::class)->create();
        $item = HealthCondition::create(['code' => 'inactive_hc', 'name' => 'Inactive', 'status' => 'inactive']);

        $this->actingAs($user)->postJson('/api/v1/users/me/health-conditions', [
            'health_condition_id' => $item->id,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_HEALTH_PREFERENCE');
    }

    public function test_eliminacion_propia()
    {
        $user = factory(User::class)->create();
        $item = Allergy::create(['code' => 'soy', 'name' => 'Soy', 'status' => 'active']);
        $relation = UserAllergy::create(['user_id' => $user->id, 'allergy_id' => $item->id, 'created_at' => now()]);

        $this->actingAs($user)->deleteJson('/api/v1/users/me/allergies/'.$relation->id)
            ->assertStatus(204);

        $this->assertDatabaseMissing('user_allergies', ['id' => $relation->id]);
    }

    public function test_eliminacion_ajena_rechazada()
    {
        $user = factory(User::class)->create();
        $other = factory(User::class)->create();
        $item = HealthCondition::create(['code' => 'asthma', 'name' => 'Asthma', 'status' => 'active']);
        $relation = UserHealthCondition::create(['user_id' => $other->id, 'health_condition_id' => $item->id, 'created_at' => now()]);

        $this->actingAs($user)->deleteJson('/api/v1/users/me/health-conditions/'.$relation->id)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'USER_HEALTH_PREFERENCE_NOT_FOUND');
    }

    public function test_rechazo_user_id()
    {
        $user = factory(User::class)->create();
        $other = factory(User::class)->create();
        $item = DietaryRestriction::create(['code' => 'organic', 'name' => 'Organic', 'status' => 'active']);

        $this->actingAs($user)->postJson('/api/v1/users/me/dietary-restrictions', [
            'user_id' => $other->id,
            'dietary_restriction_id' => $item->id,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_auditoria()
    {
        $admin = $this->admin();
        $user = factory(User::class)->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/allergies', [
            'code' => 'audit_allergy',
            'name' => 'Audit Allergy',
        ])->assertStatus(201);

        $item = Allergy::where('code', 'audit_allergy')->first();

        $this->actingAs($user)->postJson('/api/v1/users/me/allergies', [
            'allergy_id' => $item->id,
        ])->assertStatus(201);

        $this->assertTrue(AuditLog::where('action', 'health-preference.admin.create')->exists());
        $this->assertTrue(AuditLog::where('action', 'user.health-preference.created')->exists());
    }

    public function test_no_exposicion_de_campos_sensibles()
    {
        $item = Allergy::create(['code' => 'shellfish', 'name' => 'Shellfish', 'status' => 'active']);

        $response = $this->actingAs(factory(User::class)->create())->getJson('/api/v1/catalog/allergies');

        $response->assertStatus(200);
        $json = json_encode($response->json('data'));
        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString('remember_token', $json);
        $this->assertStringNotContainsString('deleted_at', $json);
        $this->assertStringContainsString($item->code, $json);
    }
}
