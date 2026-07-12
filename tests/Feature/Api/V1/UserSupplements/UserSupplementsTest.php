<?php

namespace Tests\Feature\Api\V1\UserSupplements;

use App\AuditLog;
use App\SupplementType;
use App\User;
use App\UserSupplement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSupplementsTest extends TestCase
{
    use RefreshDatabase;

    private function supplementType(): SupplementType
    {
        return SupplementType::create([
            'code'   => 'TYPE_' . uniqid(),
            'name'   => 'Vitamina C',
            'status' => 'active',
        ]);
    }

    private function supplement(User $user, SupplementType $type, array $overrides = []): UserSupplement
    {
        return UserSupplement::create(array_merge([
            'user_id'            => $user->id,
            'supplement_type_id' => $type->id,
            'frequency'          => 'daily',
            'status'             => 'active',
        ], $overrides));
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/v1/users/me/supplements')->assertStatus(401);
        $this->postJson('/api/v1/users/me/supplements')->assertStatus(401);
    }

    public function test_list_returns_only_own_active_supplements()
    {
        $userA = factory(User::class)->create();
        $userB = factory(User::class)->create();
        $type  = $this->supplementType();

        $this->supplement($userA, $type);
        $this->supplement($userB, $type);

        $response = $this->actingAs($userA)
            ->getJson('/api/v1/users/me/supplements')
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertArrayHasKey('meta', $response->json());
        $this->assertArrayHasKey('trace_id', $response->json());
    }

    public function test_create_stores_supplement_and_audits()
    {
        $user = factory(User::class)->create();
        $type = $this->supplementType();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/users/me/supplements', [
                'supplement_type_id' => $type->id,
                'frequency'          => 'daily',
                'dose_quantity'      => 500,
                'notes'              => 'con el desayuno',
            ])
            ->assertStatus(201);

        $this->assertEquals($type->id, $response->json('data.supplement_type_id'));
        $this->assertEquals('daily',   $response->json('data.frequency'));
        $this->assertDatabaseHas('user_supplements', ['user_id' => $user->id, 'supplement_type_id' => $type->id, 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_supplement.create']);
    }

    public function test_create_requires_supplement_type()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user)
            ->postJson('/api/v1/users/me/supplements', ['frequency' => 'daily'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_update_applies_partial_changes()
    {
        $user = factory(User::class)->create();
        $type = $this->supplementType();
        $s    = $this->supplement($user, $type, ['frequency' => 'daily']);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/users/me/supplements/{$s->id}", [
                'frequency' => 'weekly',
                'notes'     => 'ajustado',
            ])
            ->assertStatus(200);

        $this->assertEquals('weekly',  $response->json('data.frequency'));
        $this->assertEquals('ajustado', $response->json('data.notes'));
    }

    public function test_update_on_another_users_supplement_returns_404()
    {
        $userA = factory(User::class)->create();
        $userB = factory(User::class)->create();
        $type  = $this->supplementType();
        $s     = $this->supplement($userB, $type);

        $this->actingAs($userA)
            ->patchJson("/api/v1/users/me/supplements/{$s->id}", ['frequency' => 'daily'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'USER_SUPPLEMENT_NOT_FOUND');
    }

    public function test_delete_deactivates_and_soft_deletes_and_audits()
    {
        $user = factory(User::class)->create();
        $type = $this->supplementType();
        $s    = $this->supplement($user, $type);

        $this->actingAs($user)
            ->deleteJson("/api/v1/users/me/supplements/{$s->id}")
            ->assertStatus(204);

        $this->assertDatabaseHas('user_supplements', ['id' => $s->id, 'status' => 'inactive']);
        $this->assertSoftDeleted('user_supplements', ['id' => $s->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_supplement.delete']);
    }

    public function test_deleted_supplement_no_longer_appears_in_list()
    {
        $user = factory(User::class)->create();
        $type = $this->supplementType();
        $s    = $this->supplement($user, $type);
        $s->update(['status' => 'inactive']);
        $s->delete();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/users/me/supplements')
            ->assertStatus(200);

        $this->assertCount(0, $response->json('data'));
    }
}
