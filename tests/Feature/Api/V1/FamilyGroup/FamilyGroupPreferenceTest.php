<?php

namespace Tests\Feature\Api\V1\FamilyGroup;

use App\FamilyGroup;
use App\FamilyGroupMember;
use App\FamilyGroupPreference;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyGroupPreferenceTest extends TestCase
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
        $pref = factory(FamilyGroupPreference::class)->create(['family_group_id' => $group->id]);
        return [$owner, $group, $pref];
    }

    // ---- LECTURA ----

    public function test_lectura_por_miembro()
    {
        [$owner, $group] = $this->createGroupWithOwner();

        $response = $this->actingAs($owner)->getJson("/api/v1/family-groups/{$group->id}/preferences");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['family_group_id', 'allow_auto_stock_discount'],
                'trace_id',
            ]);
    }

    public function test_lectura_por_miembro_regular()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $member = factory(User::class)->create();
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id'         => $member->id,
            'role_in_group'   => 'member',
            'status'          => 'active',
        ]);

        $response = $this->actingAs($member)->getJson("/api/v1/family-groups/{$group->id}/preferences");

        $response->assertStatus(200);
    }

    public function test_acceso_de_usuario_ajeno_rechazado()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $other = factory(User::class)->create();

        $response = $this->actingAs($other)->getJson("/api/v1/family-groups/{$group->id}/preferences");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FAMILY_GROUP_ACCESS_DENIED');
    }

    // ---- ACTUALIZACIÓN ----

    public function test_actualizacion_por_propietario()
    {
        [$owner, $group] = $this->createGroupWithOwner();

        $response = $this->actingAs($owner)->patchJson(
            "/api/v1/family-groups/{$group->id}/preferences",
            ['allow_auto_stock_discount' => false]
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.allow_auto_stock_discount', false);

        $this->assertDatabaseHas('family_group_preferences', [
            'family_group_id'           => $group->id,
            'allow_auto_stock_discount' => false,
        ]);
    }

    public function test_actualizacion_por_administrador()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $admin = factory(User::class)->create();
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id'         => $admin->id,
            'role_in_group'   => 'admin',
            'status'          => 'active',
        ]);

        $response = $this->actingAs($admin)->patchJson(
            "/api/v1/family-groups/{$group->id}/preferences",
            ['allow_auto_stock_discount' => false]
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.allow_auto_stock_discount', false);
    }

    public function test_actualizacion_por_miembro_rechazada()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        $member = factory(User::class)->create();
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id'         => $member->id,
            'role_in_group'   => 'member',
            'status'          => 'active',
        ]);

        $response = $this->actingAs($member)->patchJson(
            "/api/v1/family-groups/{$group->id}/preferences",
            ['allow_auto_stock_discount' => false]
        );

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FAMILY_GROUP_ACCESS_DENIED');
    }

    public function test_validacion()
    {
        [$owner, $group] = $this->createGroupWithOwner();

        $response = $this->actingAs($owner)->patchJson(
            "/api/v1/family-groups/{$group->id}/preferences",
            ['allow_auto_stock_discount' => 'not_a_boolean']
        );

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_actualizacion_parcial()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        FamilyGroupPreference::where('family_group_id', $group->id)
            ->update(['default_budget_mode' => 'weekly', 'allow_auto_stock_discount' => true]);

        $response = $this->actingAs($owner)->patchJson(
            "/api/v1/family-groups/{$group->id}/preferences",
            ['allow_auto_stock_discount' => false]
        );

        $response->assertStatus(200);
        $pref = FamilyGroupPreference::where('family_group_id', $group->id)->first();
        $this->assertEquals('weekly', $pref->default_budget_mode);
        $this->assertEquals(false, (bool) $pref->allow_auto_stock_discount);
    }

    public function test_conservacion_de_campos_no_enviados()
    {
        [$owner, $group] = $this->createGroupWithOwner();
        FamilyGroupPreference::where('family_group_id', $group->id)
            ->update(['default_budget_mode' => 'monthly', 'default_shopping_mode' => 'weekly']);

        $response = $this->actingAs($owner)->patchJson(
            "/api/v1/family-groups/{$group->id}/preferences",
            ['allow_auto_stock_discount' => true]
        );

        $response->assertStatus(200);
        $pref = FamilyGroupPreference::where('family_group_id', $group->id)->first();
        $this->assertEquals('monthly', $pref->default_budget_mode);
        $this->assertEquals('weekly', $pref->default_shopping_mode);
    }
}
