<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DailyTimeSummary;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyTimeSummary>
 */
class DailyTimeSummaryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'member_id' => Member::factory(),
            'project_id' => null,
            'task_id' => null,
            'date' => $this->faker->date(),
            'total_seconds' => $this->faker->numberBetween(0, 28800),
            'billable_seconds' => $this->faker->numberBetween(0, 28800),
            'billable_cost' => $this->faker->numberBetween(0, 100000),
        ];
    }

    public function forOrganization(Organization $organization): self
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $organization->getKey(),
        ]);
    }

    public function forMember(Member $member): self
    {
        return $this->state(fn (array $attributes) => [
            'member_id' => $member->getKey(),
            'organization_id' => $member->organization_id,
        ]);
    }

    public function forProject(?Project $project): self
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project?->getKey(),
        ]);
    }

    public function forTask(?Task $task): self
    {
        return $this->state(fn (array $attributes) => [
            'task_id' => $task?->getKey(),
        ]);
    }
}
