<?php

namespace Tests\Feature\Api\V1\Notifications;

use App\AuditLog;
use App\Notification;
use App\NotificationPreference;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function notification(User $user, array $overrides = []): Notification
    {
        return Notification::create(array_merge([
            'user_id'    => $user->id,
            'type'       => 'presupuesto',
            'title'      => 'Alerta presupuesto',
            'channel'    => 'app',
            'status'     => 'pending',
            'created_at' => now(),
        ], $overrides));
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/v1/notifications')->assertStatus(401);
        $this->getJson('/api/v1/users/me/notification-preferences')->assertStatus(401);
    }

    public function test_list_returns_only_own_notifications_paginated()
    {
        $userA = factory(User::class)->create();
        $userB = factory(User::class)->create();
        $this->notification($userA);
        $this->notification($userA);
        $this->notification($userB);

        $response = $this->actingAs($userA)
            ->getJson('/api/v1/notifications')
            ->assertStatus(200);

        $this->assertCount(2, $response->json('data'));
        $this->assertArrayHasKey('meta', $response->json());
        $this->assertArrayHasKey('trace_id', $response->json());
    }

    public function test_list_filters_by_type_and_status()
    {
        $user = factory(User::class)->create();
        $this->notification($user, ['type' => 'presupuesto', 'status' => 'pending']);
        $this->notification($user, ['type' => 'compras',    'status' => 'read', 'read_at' => now()]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/notifications?type=presupuesto&status=pending')
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('presupuesto', $response->json('data.0.type'));
    }

    public function test_unread_count_returns_own_count_only()
    {
        $userA = factory(User::class)->create();
        $userB = factory(User::class)->create();
        $this->notification($userA);
        $this->notification($userA);
        $this->notification($userA, ['read_at' => now(), 'status' => 'read']);
        $this->notification($userB);

        $response = $this->actingAs($userA)
            ->getJson('/api/v1/notifications/unread-count')
            ->assertStatus(200);

        $this->assertEquals(2, $response->json('data.unread_count'));
    }

    public function test_mark_as_read_updates_notification_and_audits()
    {
        $user = factory(User::class)->create();
        $n    = $this->notification($user);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/notifications/{$n->id}/read")
            ->assertStatus(200);

        $this->assertEquals('read',   $response->json('data.status'));
        $this->assertNotNull($response->json('data.read_at'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification.read']);
    }

    public function test_mark_as_read_on_another_users_notification_returns_404()
    {
        $userA = factory(User::class)->create();
        $userB = factory(User::class)->create();
        $n     = $this->notification($userB);

        $this->actingAs($userA)
            ->patchJson("/api/v1/notifications/{$n->id}/read")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOTIFICATION_NOT_FOUND');
    }

    public function test_mark_as_read_again_returns_conflict()
    {
        $user = factory(User::class)->create();
        $n    = $this->notification($user, ['read_at' => now(), 'status' => 'read']);

        $this->actingAs($user)
            ->patchJson("/api/v1/notifications/{$n->id}/read")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'NOTIFICATION_ALREADY_READ');
    }

    public function test_read_all_marks_all_unread_and_audits()
    {
        $user = factory(User::class)->create();
        $this->notification($user);
        $this->notification($user);
        $this->notification($user, ['read_at' => now(), 'status' => 'read']);

        $response = $this->actingAs($user)
            ->patchJson('/api/v1/notifications/read-all')
            ->assertStatus(200);

        $this->assertEquals(2, $response->json('data.marked_count'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification.read_all']);
    }

    public function test_get_preferences_returns_all_types_with_defaults()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/users/me/notification-preferences')
            ->assertStatus(200);

        $types = array_column($response->json('data'), 'notification_type');
        $this->assertContains('presupuesto', $types);
        $this->assertContains('compras',     $types);
        $this->assertContains('bajo_stock',  $types);
    }

    public function test_update_preferences_partial_update_and_audits()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)
            ->patchJson('/api/v1/users/me/notification-preferences', [
                'preferences' => [
                    ['notification_type' => 'presupuesto', 'app_enabled' => true, 'email_enabled' => true],
                    ['notification_type' => 'compras',     'push_enabled' => false],
                ],
            ])
            ->assertStatus(200);

        $prefs = collect($response->json('data'))->keyBy('notification_type');
        $this->assertTrue($prefs['presupuesto']['email_enabled']);
        $this->assertDatabaseHas('notification_preferences', ['user_id' => $user->id, 'notification_type' => 'presupuesto', 'email_enabled' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification_preference.update']);
    }

    public function test_list_rejects_invalid_type_filter()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user)
            ->getJson('/api/v1/notifications?type=fake_type')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'NOTIFICATION_TYPE_INVALID');
    }
}
