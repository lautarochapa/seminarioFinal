<?php

namespace Tests\Feature\Api\V1\FamilyGroup;

use App\FamilyGroup;
use App\FamilyGroupInvitation;
use App\FamilyGroupMember;
use App\FamilyGroupPreference;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyGroupInvitationTest extends TestCase
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

    // ---- CREAR INVITACIÓN ----

    public function test_creacion_exitosa()
    {
        [$owner, $group] = $this->createGroupWithOwner();

        $response = $this->actingAs($owner)->postJson(
            "/api/v1/family-groups/{$group->id}/invitations",
            ['email' => 'invitado@example.com', 'role' => 'member']
        );

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'invited_email', 'status'], 'trace_id'])
            ->assertJsonPath('data.invited_email', 'invitado@example.com')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('family_group_invitations', [
            'family_group_id' => $group->id,
            'invited_email'   => 'invitado@example.com',
        ]);
    }

    public function test_email_normalizado()
    {
        [$owner, $group] = $this->createGroupWithOwner();

        $response = $this->actingAs($owner)->postJson(
            "/api/v1/family-groups/{$group->id}/invitations",
            ['email' => 'INVITADO@EXAMPLE.COM', 'role' => 'member']
        );

        $response->assertStatus(201)
            ->assertJsonPath('data.invited_email', 'invitado@example.com');

        $this->assertDatabaseHas('family_group_invitations', [
            'invited_email' => 'invitado@example.com',
        ]);
    }

    public function test_invitacion_duplicada()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        factory(FamilyGroupInvitation::class)->create([
            'family_group_id' => $group->id,
            'invited_email'   => 'duplicado@example.com',
            'invited_by'      => $owner->id,
            'status'          => 'pending',
        ]);

        $response = $this->actingAs($owner)->postJson(
            "/api/v1/family-groups/{$group->id}/invitations",
            ['email' => 'duplicado@example.com', 'role' => 'member']
        );

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'FAMILY_INVITATION_ALREADY_EXISTS');
    }

    public function test_usuario_ya_miembro()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $member = factory(User::class)->create(['email' => 'miembro@example.com']);
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id'         => $member->id,
            'role_in_group'   => 'member',
            'status'          => 'active',
        ]);

        $response = $this->actingAs($owner)->postJson(
            "/api/v1/family-groups/{$group->id}/invitations",
            ['email' => 'miembro@example.com', 'role' => 'member']
        );

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'USER_ALREADY_FAMILY_MEMBER');
    }

    public function test_acceso_sin_autorizacion()
    {
        $response = $this->postJson('/api/v1/family-groups/1/invitations', [
            'email' => 'test@example.com',
            'role'  => 'member',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_UNAUTHENTICATED');
    }

    public function test_no_exposicion_de_secretos()
    {
        [$owner, $group] = $this->createGroupWithOwner();

        $response = $this->actingAs($owner)->postJson(
            "/api/v1/family-groups/{$group->id}/invitations",
            ['email' => 'secreto@example.com', 'role' => 'member']
        );

        $response->assertStatus(201);
        $content = $response->getContent();
        $invitation = FamilyGroupInvitation::where('invited_email', 'secreto@example.com')->first();
        $this->assertNotNull($invitation);
        $this->assertStringNotContainsString($invitation->token, $content);
    }

    // ---- ACEPTAR INVITACIÓN ----

    public function test_aceptacion_exitosa()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $invited = factory(User::class)->create(['email' => 'aceptante@example.com']);
        $invitation = factory(FamilyGroupInvitation::class)->create([
            'family_group_id' => $group->id,
            'invited_email'   => 'aceptante@example.com',
            'invited_user_id' => $invited->id,
            'invited_by'      => $owner->id,
            'status'          => 'pending',
            'expires_at'      => now()->addDays(7),
        ]);

        $response = $this->actingAs($invited)->postJson(
            "/api/v1/family-groups/invitations/{$invitation->id}/accept"
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('family_group_invitations', [
            'id'     => $invitation->id,
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('family_group_members', [
            'family_group_id' => $group->id,
            'user_id'         => $invited->id,
            'status'          => 'active',
        ]);
    }

    public function test_aceptacion_atomica()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $invited = factory(User::class)->create(['email' => 'atomico@example.com']);
        $invitation = factory(FamilyGroupInvitation::class)->create([
            'family_group_id' => $group->id,
            'invited_email'   => 'atomico@example.com',
            'invited_user_id' => $invited->id,
            'invited_by'      => $owner->id,
            'status'          => 'pending',
            'expires_at'      => now()->addDays(7),
        ]);

        $this->actingAs($invited)->postJson(
            "/api/v1/family-groups/invitations/{$invitation->id}/accept"
        );

        // Both invitation updated and membership created
        $this->assertDatabaseHas('family_group_invitations', ['id' => $invitation->id, 'status' => 'accepted']);
        $this->assertDatabaseHas('family_group_members', ['user_id' => $invited->id, 'family_group_id' => $group->id]);
    }

    public function test_destinatario_incorrecto()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $invited = factory(User::class)->create(['email' => 'aceptante@example.com']);
        $other = factory(User::class)->create(['email' => 'otro@example.com']);
        $invitation = factory(FamilyGroupInvitation::class)->create([
            'family_group_id' => $group->id,
            'invited_email'   => 'aceptante@example.com',
            'invited_user_id' => $invited->id,
            'invited_by'      => $owner->id,
            'status'          => 'pending',
            'expires_at'      => now()->addDays(7),
        ]);

        $response = $this->actingAs($other)->postJson(
            "/api/v1/family-groups/invitations/{$invitation->id}/accept"
        );

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FAMILY_INVITATION_RECIPIENT_MISMATCH');
    }

    public function test_invitacion_vencida()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $invited = factory(User::class)->create(['email' => 'vencido@example.com']);
        $invitation = factory(FamilyGroupInvitation::class)->create([
            'family_group_id' => $group->id,
            'invited_email'   => 'vencido@example.com',
            'invited_user_id' => $invited->id,
            'invited_by'      => $owner->id,
            'status'          => 'pending',
            'expires_at'      => now()->subDays(1),
        ]);

        $response = $this->actingAs($invited)->postJson(
            "/api/v1/family-groups/invitations/{$invitation->id}/accept"
        );

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'FAMILY_INVITATION_EXPIRED');
    }

    public function test_ya_aceptada()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $invited = factory(User::class)->create(['email' => 'aceptado@example.com']);
        $invitation = factory(FamilyGroupInvitation::class)->create([
            'family_group_id' => $group->id,
            'invited_email'   => 'aceptado@example.com',
            'invited_user_id' => $invited->id,
            'invited_by'      => $owner->id,
            'status'          => 'accepted',
            'expires_at'      => now()->addDays(7),
        ]);

        $response = $this->actingAs($invited)->postJson(
            "/api/v1/family-groups/invitations/{$invitation->id}/accept"
        );

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'FAMILY_INVITATION_ALREADY_ACCEPTED');
    }

    public function test_cancelada()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $invited = factory(User::class)->create(['email' => 'cancelado@example.com']);
        $invitation = factory(FamilyGroupInvitation::class)->create([
            'family_group_id' => $group->id,
            'invited_email'   => 'cancelado@example.com',
            'invited_user_id' => $invited->id,
            'invited_by'      => $owner->id,
            'status'          => 'cancelled',
        ]);

        $response = $this->actingAs($invited)->postJson(
            "/api/v1/family-groups/invitations/{$invitation->id}/accept"
        );

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'FAMILY_INVITATION_CANCELLED');
    }

    public function test_usuario_con_otro_grupo()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        [$owner2, $group2] = $this->createGroupWithOwner();
        $invitation = factory(FamilyGroupInvitation::class)->create([
            'family_group_id' => $group->id,
            'invited_email'   => $owner2->email,
            'invited_user_id' => $owner2->id,
            'invited_by'      => $owner->id,
            'status'          => 'pending',
            'expires_at'      => now()->addDays(7),
        ]);

        $response = $this->actingAs($owner2)->postJson(
            "/api/v1/family-groups/invitations/{$invitation->id}/accept"
        );

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'USER_BELONGS_TO_ANOTHER_FAMILY_GROUP');
    }

    public function test_aceptacion_repetida()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $invited = factory(User::class)->create(['email' => 'repetido@example.com']);
        $invitation = factory(FamilyGroupInvitation::class)->create([
            'family_group_id' => $group->id,
            'invited_email'   => 'repetido@example.com',
            'invited_user_id' => $invited->id,
            'invited_by'      => $owner->id,
            'status'          => 'pending',
            'expires_at'      => now()->addDays(7),
        ]);

        $this->actingAs($invited)->postJson(
            "/api/v1/family-groups/invitations/{$invitation->id}/accept"
        );

        $response = $this->actingAs($invited)->postJson(
            "/api/v1/family-groups/invitations/{$invitation->id}/accept"
        );

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'FAMILY_INVITATION_ALREADY_ACCEPTED');
    }
}
