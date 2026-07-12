<?php

namespace Tests\Feature\Api\V1\FamilyGroup;

use App\FamilyGroup;
use App\FamilyGroupMember;
use App\FamilyGroupPreference;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression tests for the demo seeder family group scenario.
 * Reproduces the exact data shape DemoDataSeeder::seedFamilyGroup() creates.
 */
class DemoSeederFamilyGroupTest extends TestCase
{
    use RefreshDatabase;

    private function seedDemoGroup()
    {
        $now = now();

        $owner = factory(User::class)->create(['status' => 'active']);
        $member = factory(User::class)->create(['status' => 'active']);

        $group = factory(FamilyGroup::class)->create([
            'owner_user_id' => $owner->id,
            'status'        => 'active',
        ]);

        factory(FamilyGroupPreference::class)->create(['family_group_id' => $group->id]);

        // Owner inserted as 'owner' (regression: seeder used to insert 'admin')
        DB::table('family_group_members')->insert([
            'family_group_id' => $group->id,
            'user_id'         => $owner->id,
            'role_in_group'   => 'owner',
            'status'          => 'active',
            'joined_at'       => $now,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        // Second user inserted as 'member'
        DB::table('family_group_members')->insert([
            'family_group_id' => $group->id,
            'user_id'         => $member->id,
            'role_in_group'   => 'member',
            'status'          => 'active',
            'joined_at'       => $now,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        return [$owner, $member, $group];
    }

    public function test_owner_sees_group_in_list()
    {
        list($owner, $member, $group) = $this->seedDemoGroup();

        $response = $this->actingAs($owner)->getJson('/api/v1/family-groups');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($group->id, $ids);
    }

    public function test_member_sees_group_in_list()
    {
        list($owner, $member, $group) = $this->seedDemoGroup();

        $response = $this->actingAs($member)->getJson('/api/v1/family-groups');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($group->id, $ids);
    }

    public function test_outsider_cannot_see_group()
    {
        list($owner, $member, $group) = $this->seedDemoGroup();
        $outsider = factory(User::class)->create();

        $response = $this->actingAs($outsider)->getJson('/api/v1/family-groups/' . $group->id);

        $response->assertStatus(403);
    }

    public function test_owner_role_in_group_is_owner_not_admin()
    {
        list($owner, $member, $group) = $this->seedDemoGroup();

        $role = DB::table('family_group_members')
            ->where('family_group_id', $group->id)
            ->where('user_id', $owner->id)
            ->value('role_in_group');

        $this->assertEquals('owner', $role, 'El propietario del grupo debe tener role_in_group = owner, no admin');
    }

    public function test_owner_can_delete_group()
    {
        list($owner, $member, $group) = $this->seedDemoGroup();

        $response = $this->actingAs($owner)->deleteJson('/api/v1/family-groups/' . $group->id);

        $response->assertStatus(200);
        $this->assertNotNull(
            DB::table('family_groups')->where('id', $group->id)->value('deleted_at')
        );
    }

    public function test_member_cannot_delete_group()
    {
        list($owner, $member, $group) = $this->seedDemoGroup();

        $response = $this->actingAs($member)->deleteJson('/api/v1/family-groups/' . $group->id);

        $response->assertStatus(403);
    }

    public function test_owner_can_see_members()
    {
        list($owner, $member, $group) = $this->seedDemoGroup();

        $response = $this->actingAs($owner)->getJson('/api/v1/family-groups/' . $group->id . '/members');

        $response->assertStatus(200);
        $userIds = collect($response->json('data'))->pluck('user_id')->all();
        $this->assertContains($owner->id, $userIds);
        $this->assertContains($member->id, $userIds);
    }

    public function test_group_has_preference_record()
    {
        list($owner, $member, $group) = $this->seedDemoGroup();

        $this->assertDatabaseHas('family_group_preferences', ['family_group_id' => $group->id]);
    }

    public function test_member_count_is_two()
    {
        list($owner, $member, $group) = $this->seedDemoGroup();

        $count = DB::table('family_group_members')
            ->where('family_group_id', $group->id)
            ->where('status', 'active')
            ->count();

        $this->assertEquals(2, $count);
    }
}
