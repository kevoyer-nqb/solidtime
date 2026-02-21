<?php

declare(strict_types=1);

namespace Tests\Unit\Endpoint\Api\V1;

use App\Enums\NotificationType;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Models\NotificationPreference;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(NotificationController::class)]
#[UsesClass(NotificationPreferenceController::class)]
class NotificationEndpointTest extends ApiEndpointTestAbstract
{
    /**
     * Create a test notification directly in the database.
     */
    private function createTestNotification(object $data, bool $read = false): string
    {
        $id = (string) Str::uuid();
        $data->user->notifications()->create([
            'id' => $id,
            'type' => 'App\\Notifications\\TestNotification',
            'data' => [
                'organization_id' => $data->organization->getKey(),
                'type' => 'test',
                'title' => 'Test Notification',
                'body' => 'This is a test notification.',
                'action_url' => null,
            ],
            'read_at' => $read ? now() : null,
        ]);

        return $id;
    }

    public function test_user_can_list_notifications_for_organization(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([
            'notifications:view:own',
            'notification-preferences:manage:own',
        ]);
        Passport::actingAs($data->user);
        $this->createTestNotification($data);

        // Act
        $response = $this->getJson(route('api.v1.notifications.index', [$data->organization->getKey()]));

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'type',
                    'data',
                    'read_at',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
        $response->assertJsonCount(1, 'data');
    }

    public function test_user_can_get_unread_count(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([
            'notifications:view:own',
        ]);
        Passport::actingAs($data->user);
        $this->createTestNotification($data);
        $this->createTestNotification($data);

        // Act
        $response = $this->getJson(route('api.v1.notifications.unread-count', [$data->organization->getKey()]));

        // Assert
        $response->assertStatus(200);
        $response->assertJson(['count' => 2]);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([
            'notifications:view:own',
        ]);
        Passport::actingAs($data->user);
        $notificationId = $this->createTestNotification($data);

        $dbNotification = $data->user->notifications()->find($notificationId);
        $this->assertNull($dbNotification->read_at);

        // Act
        $response = $this->postJson(route('api.v1.notifications.mark-as-read', [
            $data->organization->getKey(),
            $notificationId,
        ]));

        // Assert
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $dbNotification->refresh();
        $this->assertNotNull($dbNotification->read_at);
    }

    public function test_user_can_mark_all_as_read(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([
            'notifications:view:own',
        ]);
        Passport::actingAs($data->user);
        $this->createTestNotification($data);
        $this->createTestNotification($data);
        $this->createTestNotification($data);

        $this->assertEquals(3, $data->user->unreadNotifications()->count());

        // Act
        $response = $this->postJson(route('api.v1.notifications.mark-all-as-read', [
            $data->organization->getKey(),
        ]));

        // Assert
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals(0, $data->user->fresh()->unreadNotifications()->count());
    }

    public function test_notification_preferences_default_to_type_setting(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([
            'notification-preferences:manage:own',
        ]);
        Passport::actingAs($data->user);

        // Act - No preference records exist, should return defaults
        $response = $this->getJson(route('api.v1.notification-preferences.index', [
            $data->organization->getKey(),
        ]));

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'type',
                    'label',
                    'category',
                    'email_enabled',
                ],
            ],
        ]);

        // Test type has isDefaultEnabled() = false
        $response->assertJsonFragment([
            'type' => 'test',
            'email_enabled' => false,
        ]);
    }

    public function test_user_can_update_notification_preference(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([
            'notification-preferences:manage:own',
        ]);
        Passport::actingAs($data->user);

        // Act - Enable email for test type
        $response = $this->putJson(route('api.v1.notification-preferences.update', [
            $data->organization->getKey(),
        ]), [
            'notification_type' => NotificationType::Test->value,
            'email_enabled' => true,
        ]);

        // Assert
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $preference = NotificationPreference::query()
            ->where('member_id', $data->member->getKey())
            ->where('notification_type', NotificationType::Test->value)
            ->first();

        $this->assertNotNull($preference);
        $this->assertTrue($preference->email_enabled);

        // Act - Disable it again
        $response = $this->putJson(route('api.v1.notification-preferences.update', [
            $data->organization->getKey(),
        ]), [
            'notification_type' => NotificationType::Test->value,
            'email_enabled' => false,
        ]);

        // Assert
        $response->assertStatus(200);
        $preference->refresh();
        $this->assertFalse($preference->email_enabled);
    }

    public function test_user_cannot_list_notifications_without_permission(): void
    {
        // Arrange
        $data = $this->createUserWithPermission();
        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(route('api.v1.notifications.index', [$data->organization->getKey()]));

        // Assert
        $response->assertForbidden();
    }

    public function test_user_cannot_update_preferences_without_permission(): void
    {
        // Arrange
        $data = $this->createUserWithPermission();
        Passport::actingAs($data->user);

        // Act
        $response = $this->putJson(route('api.v1.notification-preferences.update', [
            $data->organization->getKey(),
        ]), [
            'notification_type' => NotificationType::Test->value,
            'email_enabled' => true,
        ]);

        // Assert
        $response->assertForbidden();
    }

    public function test_update_preference_validates_notification_type(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([
            'notification-preferences:manage:own',
        ]);
        Passport::actingAs($data->user);

        // Act
        $response = $this->putJson(route('api.v1.notification-preferences.update', [
            $data->organization->getKey(),
        ]), [
            'notification_type' => 'invalid_type',
            'email_enabled' => true,
        ]);

        // Assert
        $response->assertStatus(422);
    }
}
