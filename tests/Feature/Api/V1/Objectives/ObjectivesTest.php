<?php

namespace Tests\Feature\Api\V1\Objectives;

use App\AuditLog;
use App\Objective;
use App\Role;
use App\User;
use App\UserObjective;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ObjectivesTest extends TestCase
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

    public function test_admin_sin_autenticar()
    {
        $this->getJson('/api/v1/admin/objectives')->assertStatus(401);
    }

    public function test_admin_sin_permiso()
    {
        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/objectives')
            ->assertStatus(403);
    }

    public function test_admin_alta()
    {
        $response = $this->actingAs($this->admin())->postJson('/api/v1/admin/objectives', [
            'code' => ' Improve_Health ',
            'name' => 'Improve health',
            'description' => 'Better habits',
            'status' => 'active',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'improve_health');

        $this->assertDatabaseHas('objectives', ['code' => 'improve_health']);
    }

    public function test_admin_codigo_duplicado()
    {
        factory(Objective::class)->create(['code' => 'save_time']);

        $this->actingAs($this->admin())->postJson('/api/v1/admin/objectives', [
            'code' => 'SAVE_TIME',
            'name' => 'Save time',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'OBJECTIVE_CODE_ALREADY_EXISTS');
    }

    public function test_admin_actualizacion()
    {
        $objective = factory(Objective::class)->create(['name' => 'Old']);

        $this->actingAs($this->admin())->patchJson('/api/v1/admin/objectives/'.$objective->id, [
            'name' => 'New',
            'status' => 'inactive',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'New')
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_admin_baja_y_restore()
    {
        $admin = $this->admin();
        $objective = factory(Objective::class)->create();

        $deleteResponse = $this->actingAs($admin)->deleteJson('/api/v1/admin/objectives/'.$objective->id);

        $deleteResponse->assertStatus(200);
        $this->assertNotNull($deleteResponse->json('data.deleted_at'));

        $this->actingAs($admin)->patchJson('/api/v1/admin/objectives/'.$objective->id.'/restore')
            ->assertStatus(200)
            ->assertJsonPath('data.deleted_at', null);
    }

    public function test_catalogo_solo_activos()
    {
        factory(Objective::class)->create(['code' => 'active_one', 'status' => 'active']);
        factory(Objective::class)->create(['code' => 'inactive_one', 'status' => 'inactive']);

        $response = $this->actingAs(factory(User::class)->create())->getJson('/api/v1/catalog/objectives');

        $response->assertStatus(200);
        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains('active_one', $codes);
        $this->assertNotContains('inactive_one', $codes);
    }

    public function test_listado_propio()
    {
        $user = factory(User::class)->create();
        $other = factory(User::class)->create();
        $objective = factory(Objective::class)->create();
        $otherObjective = factory(Objective::class)->create();
        UserObjective::create(['user_id' => $user->id, 'objective_id' => $objective->id, 'is_active' => true]);
        UserObjective::create(['user_id' => $other->id, 'objective_id' => $otherObjective->id, 'is_active' => true]);

        $response = $this->actingAs($user)->getJson('/api/v1/users/me/objectives');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($objective->id, $response->json('data.0.objective.id'));
    }

    public function test_asignacion_exitosa()
    {
        $user = factory(User::class)->create();
        $objective = factory(Objective::class)->create(['status' => 'active']);

        $this->actingAs($user)->postJson('/api/v1/users/me/objectives', [
            'objective_id' => $objective->id,
            'priority' => 1,
            'target_value' => 10.5,
            'target_date' => now()->addDay()->toDateString(),
            'notes' => ' personal ',
        ])->assertStatus(201)
            ->assertJsonPath('data.objective.id', $objective->id)
            ->assertJsonPath('data.notes', 'personal');
    }

    public function test_duplicado_rechazado()
    {
        $user = factory(User::class)->create();
        $objective = factory(Objective::class)->create();
        UserObjective::create(['user_id' => $user->id, 'objective_id' => $objective->id, 'is_active' => true]);

        $this->actingAs($user)->postJson('/api/v1/users/me/objectives', [
            'objective_id' => $objective->id,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'USER_OBJECTIVE_ALREADY_EXISTS');
    }

    public function test_objetivo_inactivo_rechazado()
    {
        $user = factory(User::class)->create();
        $objective = factory(Objective::class)->create(['status' => 'inactive']);

        $this->actingAs($user)->postJson('/api/v1/users/me/objectives', [
            'objective_id' => $objective->id,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_USER_OBJECTIVE');
    }

    public function test_actualizacion_propia()
    {
        $user = factory(User::class)->create();
        $objective = factory(Objective::class)->create();
        $assignment = UserObjective::create(['user_id' => $user->id, 'objective_id' => $objective->id, 'is_active' => true]);

        $this->actingAs($user)->patchJson('/api/v1/users/me/objectives/'.$assignment->id, [
            'priority' => 2,
            'notes' => 'updated',
        ])->assertStatus(200)
            ->assertJsonPath('data.priority', 2)
            ->assertJsonPath('data.notes', 'updated');
    }

    public function test_actualizacion_ajena_rechazada()
    {
        $user = factory(User::class)->create();
        $other = factory(User::class)->create();
        $objective = factory(Objective::class)->create();
        $assignment = UserObjective::create(['user_id' => $other->id, 'objective_id' => $objective->id, 'is_active' => true]);

        $this->actingAs($user)->patchJson('/api/v1/users/me/objectives/'.$assignment->id, [
            'priority' => 2,
        ])->assertStatus(404)
            ->assertJsonPath('error.code', 'USER_OBJECTIVE_NOT_FOUND');
    }

    public function test_eliminacion_propia()
    {
        $user = factory(User::class)->create();
        $objective = factory(Objective::class)->create();
        $assignment = UserObjective::create(['user_id' => $user->id, 'objective_id' => $objective->id, 'is_active' => true]);

        $this->actingAs($user)->deleteJson('/api/v1/users/me/objectives/'.$assignment->id)
            ->assertStatus(204);

        $this->assertFalse((bool) $assignment->fresh()->is_active);
        $this->assertDatabaseHas('objectives', ['id' => $objective->id]);
    }

    public function test_eliminacion_ajena_rechazada()
    {
        $user = factory(User::class)->create();
        $other = factory(User::class)->create();
        $objective = factory(Objective::class)->create();
        $assignment = UserObjective::create(['user_id' => $other->id, 'objective_id' => $objective->id, 'is_active' => true]);

        $this->actingAs($user)->deleteJson('/api/v1/users/me/objectives/'.$assignment->id)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'USER_OBJECTIVE_NOT_FOUND');
    }

    public function test_auditoria()
    {
        $admin = $this->admin();
        $objective = factory(Objective::class)->create();

        $this->actingAs($admin)->patchJson('/api/v1/admin/objectives/'.$objective->id, [
            'name' => 'Audited objective',
        ])->assertStatus(200);

        $user = factory(User::class)->create();
        $this->actingAs($user)->postJson('/api/v1/users/me/objectives', [
            'objective_id' => $objective->id,
        ])->assertStatus(201);

        $this->assertTrue(AuditLog::where('action', 'objective.admin.update')->exists());
        $this->assertTrue(AuditLog::where('action', 'user.objective.created')->exists());
    }
}
