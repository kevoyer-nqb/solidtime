<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\Member;
use App\Models\NotificationPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'notification_type' => NotificationType::Test->value,
            'email_enabled' => false,
        ];
    }

    public function forMember(Member $member): static
    {
        return $this->state(fn (array $attributes): array => [
            'member_id' => $member->getKey(),
        ]);
    }

    public function forType(NotificationType $type): static
    {
        return $this->state(fn (array $attributes): array => [
            'notification_type' => $type->value,
        ]);
    }

    public function emailEnabled(bool $enabled = true): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_enabled' => $enabled,
        ]);
    }
}
