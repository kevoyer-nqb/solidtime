<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Models\DailyTimeSummary;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Service\DailyTimeSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(DailyTimeSummaryService::class)]
class DailyTimeSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    private DailyTimeSummaryService $service;

    private Organization $organization;

    private Member $member;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DailyTimeSummaryService::class);

        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create([
            'user_id' => $this->user->getKey(),
        ]);
        $this->member = Member::factory()->create([
            'user_id' => $this->user->getKey(),
            'organization_id' => $this->organization->getKey(),
        ]);
    }

    public function test_aggregates_time_entries_for_date(): void
    {
        // Arrange - Create time entries for 2026-02-10
        TimeEntry::factory()->create([
            'organization_id' => $this->organization->getKey(),
            'member_id' => $this->member->getKey(),
            'user_id' => $this->user->getKey(),
            'start' => Carbon::parse('2026-02-10 09:00:00', 'UTC'),
            'end' => Carbon::parse('2026-02-10 12:00:00', 'UTC'),
            'billable' => false,
        ]);

        TimeEntry::factory()->create([
            'organization_id' => $this->organization->getKey(),
            'member_id' => $this->member->getKey(),
            'user_id' => $this->user->getKey(),
            'start' => Carbon::parse('2026-02-10 13:00:00', 'UTC'),
            'end' => Carbon::parse('2026-02-10 17:00:00', 'UTC'),
            'billable' => false,
        ]);

        // Act
        $this->service->aggregateForDate(
            $this->organization,
            Carbon::parse('2026-02-10')
        );

        // Assert
        $summaries = DailyTimeSummary::where('organization_id', $this->organization->getKey())
            ->where('date', '2026-02-10')
            ->get();

        $this->assertCount(1, $summaries);
        // 3 hours + 4 hours = 7 hours = 25200 seconds
        $this->assertEquals(25200, $summaries->first()->total_seconds);
    }

    public function test_idempotent_re_aggregation(): void
    {
        // Arrange
        TimeEntry::factory()->create([
            'organization_id' => $this->organization->getKey(),
            'member_id' => $this->member->getKey(),
            'user_id' => $this->user->getKey(),
            'start' => Carbon::parse('2026-02-10 09:00:00', 'UTC'),
            'end' => Carbon::parse('2026-02-10 12:00:00', 'UTC'),
            'billable' => false,
        ]);

        // Act - Run twice
        $this->service->aggregateForDate(
            $this->organization,
            Carbon::parse('2026-02-10')
        );
        $this->service->aggregateForDate(
            $this->organization,
            Carbon::parse('2026-02-10')
        );

        // Assert - Should have only one summary row, not two
        $count = DailyTimeSummary::where('organization_id', $this->organization->getKey())
            ->where('date', '2026-02-10')
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_clips_entries_spanning_midnight(): void
    {
        // Arrange - Entry from 23:00 to 01:00 (spans midnight)
        TimeEntry::factory()->create([
            'organization_id' => $this->organization->getKey(),
            'member_id' => $this->member->getKey(),
            'user_id' => $this->user->getKey(),
            'start' => Carbon::parse('2026-02-10 23:00:00', 'UTC'),
            'end' => Carbon::parse('2026-02-11 01:00:00', 'UTC'),
            'billable' => false,
        ]);

        // Act - Aggregate for Feb 10
        $this->service->aggregateForDate(
            $this->organization,
            Carbon::parse('2026-02-10')
        );

        // Assert - Should clip to 23:00 - 23:59:59 (approximately 1 hour)
        $summary = DailyTimeSummary::where('organization_id', $this->organization->getKey())
            ->where('date', '2026-02-10')
            ->first();

        $this->assertNotNull($summary);
        // Entry starts at 23:00, day ends at 23:59:59 => ~3600 seconds (1 hour)
        $this->assertGreaterThanOrEqual(3599, $summary->total_seconds);
        $this->assertLessThanOrEqual(3600, $summary->total_seconds);

        // Act - Aggregate for Feb 11
        $this->service->aggregateForDate(
            $this->organization,
            Carbon::parse('2026-02-11')
        );

        // Assert - Should clip to 00:00 - 01:00 (1 hour)
        $summary11 = DailyTimeSummary::where('organization_id', $this->organization->getKey())
            ->where('date', '2026-02-11')
            ->first();

        $this->assertNotNull($summary11);
        $this->assertGreaterThanOrEqual(3599, $summary11->total_seconds);
        $this->assertLessThanOrEqual(3600, $summary11->total_seconds);
    }

    public function test_handles_billable_and_non_billable(): void
    {
        // Arrange - One billable, one non-billable entry
        TimeEntry::factory()->create([
            'organization_id' => $this->organization->getKey(),
            'member_id' => $this->member->getKey(),
            'user_id' => $this->user->getKey(),
            'start' => Carbon::parse('2026-02-10 09:00:00', 'UTC'),
            'end' => Carbon::parse('2026-02-10 10:00:00', 'UTC'),
            'billable' => true,
            'billable_rate' => 10000, // $100/hr in cents
        ]);

        TimeEntry::factory()->create([
            'organization_id' => $this->organization->getKey(),
            'member_id' => $this->member->getKey(),
            'user_id' => $this->user->getKey(),
            'start' => Carbon::parse('2026-02-10 10:00:00', 'UTC'),
            'end' => Carbon::parse('2026-02-10 12:00:00', 'UTC'),
            'billable' => false,
        ]);

        // Act
        $this->service->aggregateForDate(
            $this->organization,
            Carbon::parse('2026-02-10')
        );

        // Assert
        $summary = DailyTimeSummary::where('organization_id', $this->organization->getKey())
            ->where('date', '2026-02-10')
            ->first();

        $this->assertNotNull($summary);
        // Total: 1h + 2h = 3h = 10800 seconds
        $this->assertEquals(10800, $summary->total_seconds);
        // Billable: 1h = 3600 seconds
        $this->assertEquals(3600, $summary->billable_seconds);
        // Billable cost: 1h * $100/hr = $100 = 10000 cents
        $this->assertEquals(10000, $summary->billable_cost);
    }

    public function test_groups_by_member_project_task(): void
    {
        // Arrange - Create a project and task
        $project = Project::factory()->create([
            'organization_id' => $this->organization->getKey(),
        ]);
        $task = Task::factory()->create([
            'project_id' => $project->getKey(),
            'organization_id' => $this->organization->getKey(),
        ]);

        // Entry with project + task
        TimeEntry::factory()->create([
            'organization_id' => $this->organization->getKey(),
            'member_id' => $this->member->getKey(),
            'user_id' => $this->user->getKey(),
            'project_id' => $project->getKey(),
            'task_id' => $task->getKey(),
            'start' => Carbon::parse('2026-02-10 09:00:00', 'UTC'),
            'end' => Carbon::parse('2026-02-10 10:00:00', 'UTC'),
            'billable' => false,
        ]);

        // Entry without project or task
        TimeEntry::factory()->create([
            'organization_id' => $this->organization->getKey(),
            'member_id' => $this->member->getKey(),
            'user_id' => $this->user->getKey(),
            'project_id' => null,
            'task_id' => null,
            'start' => Carbon::parse('2026-02-10 10:00:00', 'UTC'),
            'end' => Carbon::parse('2026-02-10 11:00:00', 'UTC'),
            'billable' => false,
        ]);

        // Act
        $this->service->aggregateForDate(
            $this->organization,
            Carbon::parse('2026-02-10')
        );

        // Assert - Should have 2 separate summary rows
        $summaries = DailyTimeSummary::where('organization_id', $this->organization->getKey())
            ->where('date', '2026-02-10')
            ->get();

        $this->assertCount(2, $summaries);

        // One with project/task, one without
        $withProject = $summaries->where('project_id', $project->getKey())->first();
        $withoutProject = $summaries->whereNull('project_id')->first();

        $this->assertNotNull($withProject);
        $this->assertNotNull($withoutProject);
        $this->assertEquals(3600, $withProject->total_seconds);
        $this->assertEquals(3600, $withoutProject->total_seconds);
    }
}
