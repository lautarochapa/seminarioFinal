<?php

namespace Tests\Feature\Api\V1\FamilyGroup;

use App\FamilyGroup;
use App\FamilyGroupMember;
use App\FamilyGroupPreference;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyGroupTest extends TestCase
{
    use RefreshDatabase;

    private function createGroupWithOwner()
    {
        $owner = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $owner->id]);
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id'         => $owner->id,
            'role_in_group'   => 'owner',
            'status'          => 'active',
            'joined_at'       => now(),
        ]);
        factory(FamilyGroupPreference::class)->create(['family_group_id' => $group->id]);
        return [$owner, $group];
    }

    // ---- AUTH ----

    public function test_no_autenticado()
    {
        $response = $this->getJson('/api/v1/family-groups');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_UNAUTHENTICATED');
    }

    // ---- CREAR ----

    public function test_crear_grupo_exitoso()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->postJson('/api/v1/family-groups', [
            'name' => 'Mi Familia',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Mi Familia')
            ->assertJsonStructure(['data' => ['id', 'name', 'status'], 'trace_id']);

        $this->assertDatabaseHas('family_groups', ['name' => 'Mi Familia', 'owner_user_id' => $user->id]);
    }

    public function test_creador_queda_como_propietario()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->postJson('/api/v1/family-groups', [
            'name' => 'Familia López',
        ]);

        $response->assertStatus(201);
        $groupId = $response->json('data.id');

        $this->assertDatabaseHas('family_group_members', [
            'family_group_id' => $groupId,
            'user_id'         => $user->id,
            'role_in_group'   => 'owner',
            'status'          => 'active',
        ]);
    }

    public function test_creacion_atomica()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->postJson('/api/v1/family-groups', [
            'name' => 'Grupo Atomico',
        ]);

        $response->assertStatus(201);
        $groupId = $response->json('data.id');

        // Both group and membership created together
        $this->assertDatabaseHas('family_groups', ['id' => $groupId]);
        $this->assertDatabaseHas('family_group_members', [
            'family_group_id' => $groupId,
            'user_id'         => $user->id,
        ]);
        $this->assertDatabaseHas('family_group_preferences', [
            'family_group_id' => $groupId,
        ]);
    }

    public function test_usuario_con_grupo_no_puede_crear_otro()
    {
        [$owner] = $this->createGroupWithOwner();

        $response = $this->actingAs($owner)->postJson('/api/v1/family-groups', [
            'name' => 'Otro Grupo',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'USER_ALREADY_HAS_FAMILY_GROUP');
    }

    public function test_nombre_obligatorio()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->postJson('/api/v1/family-groups', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    // ---- LISTADO ----

    public function test_listado_restringido_al_usuario()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        [$other, $otherGroup] = $this->createGroupWithOwner();

        $response = $this->actingAs($owner)->getJson('/api/v1/family-groups');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($group->id, $ids);
        $this->assertNotContains($otherGroup->id, $ids);
    }

    // ---- DETALLE ----

    public function test_detalle_propio()
    {
        [$owner, $group] = $this->createGroupWithOwner();

        $response = $this->actingAs($owner)->getJson("/api/v1/family-groups/{$group->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $group->id)
            ->assertJsonPath('data.name', $group->name)
            ->assertJsonStructure(['data' => ['id', 'name', 'status'], 'trace_id']);
    }

    public function test_acceso_a_grupo_ajeno_rechazado()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $other = factory(User::class)->create();

        $response = $this->actingAs($other)->getJson("/api/v1/family-groups/{$group->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FAMILY_GROUP_ACCESS_DENIED');
    }

    // ---- ACTUALIZAR ----

    public function test_actualizacion_autorizada()
    {
        [$owner, $group] = $this->createGroupWithOwner();

        $response = $this->actingAs($owner)->patchJson("/api/v1/family-groups/{$group->id}", [
            'name' => 'Familia Actualizada',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Familia Actualizada');
    }

    public function test_actualizacion_por_administrador_permitida()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $admin = factory(User::class)->create();
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id'         => $admin->id,
            'role_in_group'   => 'admin',
            'status'          => 'active',
        ]);

        $response = $this->actingAs($admin)->patchJson("/api/v1/family-groups/{$group->id}", [
            'name' => 'Actualizado por admin',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Actualizado por admin');
    }

    public function test_actualizacion_por_miembro_comun_rechazada()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $member = factory(User::class)->create();
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id'         => $member->id,
            'role_in_group'   => 'member',
            'status'          => 'active',
        ]);

        $response = $this->actingAs($member)->patchJson("/api/v1/family-groups/{$group->id}", [
            'name' => 'No autorizado',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FAMILY_GROUP_ACCESS_DENIED');
    }

    // ---- ELIMINAR ----

    public function test_baja_logica()
    {
        [$owner, $group] = $this->createGroupWithOwner();

        $response = $this->actingAs($owner)->deleteJson("/api/v1/family-groups/{$group->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('family_groups', ['id' => $group->id]);
    }

    public function test_eliminacion_por_no_propietario_rechazada()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $admin = factory(User::class)->create();
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id'         => $admin->id,
            'role_in_group'   => 'admin',
            'status'          => 'active',
        ]);

        $response = $this->actingAs($admin)->deleteJson("/api/v1/family-groups/{$group->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FAMILY_GROUP_ACCESS_DENIED');
    }

    // ---- CAMPOS SENSIBLES ----

    public function test_ausencia_de_campos_sensibles()
    {
        [$owner, $group] = $this->createGroupWithOwner();

        $response = $this->actingAs($owner)->getJson("/api/v1/family-groups/{$group->id}");

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('remember_token', $content);
    }

    // ---- TRACE ID ----

    public function test_trace_id_presente()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/family-groups');

        $traceId = $response->json('trace_id');
        $this->assertNotNull($traceId);
        $this->assertRegExp(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $traceId
        );
    }
}
