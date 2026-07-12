<?php

namespace Tests\Feature\Api\V1\FamilyGroup;

use App\FamilyGroup;
use App\FamilyGroupMember;
use App\FamilyGroupPreference;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyGroupMemberTest extends TestCase
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

    // ---- LISTADO ----

    public function test_listado_de_miembros()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $member = factory(User::class)->create();
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id'         => $member->id,
            'role_in_group'   => 'member',
            'status'          => 'active',
        ]);

        $response = $this->actingAs($owner)->getJson("/api/v1/family-groups/{$group->id}/members");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'     => [['id', 'user_id', 'role', 'status', 'joined_at']],
                'trace_id',
            ]);
        $this->assertCount(2, $response->json('data'));
    }

    // ---- AGREGAR MIEMBRO ----

    public function test_agregar_miembro()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $newUser = factory(User::class)->create();

        $response = $this->actingAs($owner)->postJson("/api/v1/family-groups/{$group->id}/members", [
            'user_id' => $newUser->id,
            'role'    => 'member',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user_id', $newUser->id)
            ->assertJsonPath('data.role', 'member');

        $this->assertDatabaseHas('family_group_members', [
            'family_group_id' => $group->id,
            'user_id'         => $newUser->id,
            'role_in_group'   => 'member',
        ]);
    }

    public function test_usuario_inexistente()
    {
        [$owner, $group] = $this->createGroupWithOwner();

        $response = $this->actingAs($owner)->postJson("/api/v1/family-groups/{$group->id}/members", [
            'user_id' => 99999,
            'role'    => 'member',
        ]);

        $response->assertStatus(404);
    }

    public function test_usuario_ya_miembro()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $existingMember = factory(User::class)->create();
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id'         => $existingMember->id,
            'role_in_group'   => 'member',
            'status'          => 'active',
        ]);

        $response = $this->actingAs($owner)->postJson("/api/v1/family-groups/{$group->id}/members", [
            'user_id' => $existingMember->id,
            'role'    => 'member',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'USER_ALREADY_FAMILY_MEMBER');
    }

    public function test_usuario_pertenece_a_otro_grupo()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        [$owner2] = $this->createGroupWithOwner();

        $response = $this->actingAs($owner)->postJson("/api/v1/family-groups/{$group->id}/members", [
            'user_id' => $owner2->id,
            'role'    => 'member',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'USER_BELONGS_TO_ANOTHER_FAMILY_GROUP');
    }

    public function test_rol_invalido()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $newUser = factory(User::class)->create();

        $response = $this->actingAs($owner)->postJson("/api/v1/family-groups/{$group->id}/members", [
            'user_id' => $newUser->id,
            'role'    => 'superadmin',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_solo_propietario_o_admin_puede_agregar()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $member = factory(User::class)->create();
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id'         => $member->id,
            'role_in_group'   => 'member',
            'status'          => 'active',
        ]);
        $newUser = factory(User::class)->create();

        $response = $this->actingAs($member)->postJson("/api/v1/family-groups/{$group->id}/members", [
            'user_id' => $newUser->id,
            'role'    => 'member',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FAMILY_GROUP_ACCESS_DENIED');
    }

    // ---- MODIFICAR MIEMBRO ----

    public function test_modificacion_de_rol()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $member = factory(User::class)->create();
        $membership = factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id'         => $member->id,
            'role_in_group'   => 'member',
            'status'          => 'active',
        ]);

        $response = $this->actingAs($owner)->patchJson(
            "/api/v1/family-groups/{$group->id}/members/{$membership->id}",
            ['role' => 'admin']
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.role', 'admin');

        $this->assertDatabaseHas('family_group_members', [
            'id'            => $membership->id,
            'role_in_group' => 'admin',
        ]);
    }

    public function test_modificacion_desde_otro_grupo_rechazada()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        [$owner2, $group2] = $this->createGroupWithOwner();
        $member2 = factory(User::class)->create();
        $membership2 = factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group2->id,
            'user_id'         => $member2->id,
            'role_in_group'   => 'member',
            'status'          => 'active',
        ]);

        $response = $this->actingAs($owner)->patchJson(
            "/api/v1/family-groups/{$group->id}/members/{$membership2->id}",
            ['role' => 'admin']
        );

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'FAMILY_MEMBER_NOT_FOUND');
    }

    public function test_autoescalamiento_rechazado()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $admin = factory(User::class)->create();
        $adminMembership = factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id'         => $admin->id,
            'role_in_group'   => 'admin',
            'status'          => 'active',
        ]);

        $response = $this->actingAs($admin)->patchJson(
            "/api/v1/family-groups/{$group->id}/members/{$adminMembership->id}",
            ['role' => 'owner']
        );

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'OWNER_MODIFICATION_FORBIDDEN');
    }

    // ---- QUITAR MIEMBRO ----

    public function test_quitar_miembro()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $member = factory(User::class)->create();
        $membership = factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id'         => $member->id,
            'role_in_group'   => 'member',
            'status'          => 'active',
        ]);

        $response = $this->actingAs($owner)->deleteJson(
            "/api/v1/family-groups/{$group->id}/members/{$membership->id}"
        );

        $response->assertStatus(204);
        $this->assertDatabaseMissing('family_group_members', ['id' => $membership->id]);
    }

    public function test_administrador_no_puede_quitar_propietario()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $admin = factory(User::class)->create();
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id'         => $admin->id,
            'role_in_group'   => 'admin',
            'status'          => 'active',
        ]);
        $ownerMembership = FamilyGroupMember::where('family_group_id', $group->id)
            ->where('user_id', $owner->id)
            ->first();

        $response = $this->actingAs($admin)->deleteJson(
            "/api/v1/family-groups/{$group->id}/members/{$ownerMembership->id}"
        );

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'OWNER_MODIFICATION_FORBIDDEN');
    }

    public function test_miembro_puede_retirarse()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $member = factory(User::class)->create();
        $membership = factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id'         => $member->id,
            'role_in_group'   => 'member',
            'status'          => 'active',
        ]);

        $response = $this->actingAs($member)->deleteJson(
            "/api/v1/family-groups/{$group->id}/members/{$membership->id}"
        );

        $response->assertStatus(204);
        $this->assertDatabaseMissing('family_group_members', ['id' => $membership->id]);
    }

    public function test_unico_propietario_no_puede_retirarse()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $ownerMembership = FamilyGroupMember::where('family_group_id', $group->id)
            ->where('user_id', $owner->id)
            ->first();

        $response = $this->actingAs($owner)->deleteJson(
            "/api/v1/family-groups/{$group->id}/members/{$ownerMembership->id}"
        );

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'LAST_OWNER_REMOVAL_FORBIDDEN');
    }
}
