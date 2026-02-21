<?php

declare(strict_types=1);

namespace Tests\Unit\Endpoint\Api\V1;

use App\Http\Controllers\Api\V1\TimesheetController;
use App\Models\Member;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(TimesheetController::class)]
class TimesheetEndpointTest extends ApiEndpointTestAbstract
{
    // ========== /weeks endpoint tests ==========

    public function test_weeks_endpoint_fails_if_user_has_no_permission(): void
    {
        // Arrange
        $data = $this->createUserWithPermission();
        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(route('api.v1.timesheet.weeks', [$data->organization->getKey()]));

        // Assert
        $response->assertForbidden();
    }

    public function test_weeks_endpoint_returns_week_list_with_totals(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([
            'time-entries:view:own',
        ]);

        // Create time entries for current week
        $now = Carbon::now();
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
        TimeEntry::factory()
            ->forOrganization($data->organization)
            ->forMember($data->member)
            ->startWithDuration($weekStart->copy()->addHours(9), 3600) // 1 hour
            ->create();
        TimeEntry::factory()
            ->forOrganization($data->organization)
            ->forMember($data->member)
            ->startWithDuration($weekStart->copy()->addDay()->addHours(9), 7200) // 2 hours
            ->create();

        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(route('api.v1.timesheet.weeks', [
            $data->organization->getKey(),
            'limit' => 4,
        ]));

        // Assert
        $this->assertResponseCode($response, 200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['week_start', 'week_end', 'label', 'total_seconds'],
            ],
        ]);

        $weeks = $response->json('data');
        $this->assertCount(4, $weeks);
        $this->assertEquals('This Week', $weeks[0]['label']);
        // Current week should have 10800 seconds (3 hours = 1 + 2)
        $this->assertEquals(10800, $weeks[0]['total_seconds']);
    }

    public function test_weeks_endpoint_supports_pagination(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([
            'time-entries:view:own',
        ]);
        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(route('api.v1.timesheet.weeks', [
            $data->organization->getKey(),
            'limit' => 4,
            'offset' => 4,
        ]));

        // Assert
        $this->assertResponseCode($response, 200);
        $weeks = $response->json('data');
        $this->assertCount(4, $weeks);
        // These should be older weeks
        foreach ($weeks as $week) {
            $this->assertNotEquals('This Week', $week['label']);
            $this->assertNotEquals('Last Week', $week['label']);
        }
    }

    // ========== /timesheet (index/grid) endpoint tests ==========

    public function test_index_endpoint_fails_if_user_has_no_permission(): void
    {
        // Arrange
        $data = $this->createUserWithPermission();
        Passport::actingAs($data->user);

        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);

        // Act
        $response = $this->getJson(route('api.v1.timesheet.index', [
            $data->organization->getKey(),
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekStart->copy()->addDays(6)->toDateString(),
        ]));

        // Assert
        $response->assertForbidden();
    }

    public function test_index_endpoint_returns_grid_data_for_week(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([
            'time-entries:view:own',
        ]);

        $project = Project::factory()->forOrganization($data->organization)->create();
        $task = Task::factory()->forProject($project)->forOrganization($data->organization)->create();

        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);

        // Create entries for Monday and Tuesday
        TimeEntry::factory()
            ->forOrganization($data->organization)
            ->forMember($data->member)
            ->forProject($project)
            ->forTask($task)
            ->startWithDuration($weekStart->copy()->addHours(9), 7200) // 2 hours
            ->create();
        TimeEntry::factory()
            ->forOrganization($data->organization)
            ->forMember($data->member)
            ->forProject($project)
            ->forTask($task)
            ->startWithDuration($weekStart->copy()->addDay()->addHours(9), 3600) // 1 hour
            ->create();

        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(route('api.v1.timesheet.index', [
            $data->organization->getKey(),
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekStart->copy()->addDays(6)->toDateString(),
        ]));

        // Assert
        $this->assertResponseCode($response, 200);
        $response->assertJsonStructure([
            'data' => [
                'week_start',
                'week_end',
                'rows' => [
                    '*' => [
                        'id',
                        'project',
                        'task',
                        'cells' => [
                            '*' => ['date', 'hours', 'time_entry_ids'],
                        ],
                        'total_hours',
                    ],
                ],
                'day_totals',
                'week_total',
            ],
        ]);

        $gridData = $response->json('data');
        $this->assertCount(1, $gridData['rows']); // One project+task combo
        $this->assertCount(7, $gridData['rows'][0]['cells']); // 7 days
        $this->assertCount(7, $gridData['day_totals']);
        $this->assertEquals(3.0, $gridData['week_total']); // 2h + 1h = 3h
    }

    public function test_index_endpoint_returns_empty_grid_for_week_with_no_entries(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([
            'time-entries:view:own',
        ]);

        $weekStart = Carbon::now()->subWeeks(10)->startOfWeek(Carbon::MONDAY);
        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(route('api.v1.timesheet.index', [
            $data->organization->getKey(),
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekStart->copy()->addDays(6)->toDateString(),
        ]));

        // Assert
        $this->assertResponseCode($response, 200);
        $gridData = $response->json('data');
        $this->assertCount(0, $gridData['rows']);
        $this->assertEquals(0, $gridData['week_total']);
    }

    public function test_index_endpoint_requires_week_start_and_week_end(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([
            'time-entries:view:own',
        ]);
        Passport::actingAs($data->user);

        // Act - missing week_start and week_end
        $response = $this->getJson(route('api.v1.timesheet.index', [
            $data->organization->getKey(),
        ]));

        // Assert
        $response->assertStatus(422);
    }

    // ========== /timesheet/cell (updateCell) endpoint tests ==========

    public function test_update_cell_endpoint_fails_if_user_has_no_permission(): void
    {
        // Arrange
        $data = $this->createUserWithPermission();
        Passport::actingAs($data->user);

        // Act
        $response = $this->putJson(route('api.v1.timesheet.update-cell', [$data->organization->getKey()]), [
            'member_id' => $data->member->getKey(),
            'date' => Carbon::now()->toDateString(),
            'project_id' => null,
            'task_id' => null,
            'hours' => 2.0,
        ]);

        // Assert
        $response->assertForbidden();
    }

    public function test_update_cell_creates_new_time_entry(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([
            'time-entries:create:own',
        ]);
        $project = Project::factory()->forOrganization($data->organization)->create();
        Passport::actingAs($data->user);

        $date = Carbon::now()->toDateString();

        // Act
        $response = $this->putJson(route('api.v1.timesheet.update-cell', [$data->organization->getKey()]), [
            'member_id' => $data->member->getKey(),
            'date' => $date,
            'project_id' => $project->getKey(),
            'task_id' => null,
            'hours' => 2.5,
        ]);

        // Assert
        $this->assertResponseCode($response, 200);
        $cellData = $response->json('data');
        $this->assertEquals($date, $cellData['date']);
        $this->assertEquals(2.5, $cellData['hours']);
        $this->assertCount(1, $cellData['time_entry_ids']);

        // Verify time entry was created in DB
        $this->assertDatabaseHas('time_entries', [
            'id' => $cellData['time_entry_ids'][0],
            'organization_id' => $data->organization->getKey(),
            'user_id' => $data->user->getKey(),
            'project_id' => $project->getKey(),
        ]);
    }

    public function test_update_cell_deletes_entries_when_hours_is_zero(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([
            'time-entries:create:own',
        ]);

        $date = Carbon::now()->startOfDay();
        $timeEntry = TimeEntry::factory()
            ->forOrganization($data->organization)
            ->forMember($data->member)
            ->startWithDuration($date->copy()->addHours(9), 3600)
            ->create([
                'project_id' => null,
                'task_id' => null,
            ]);

        Passport::actingAs($data->user);

        // Act
        $response = $this->putJson(route('api.v1.timesheet.update-cell', [$data->organization->getKey()]), [
            'member_id' => $data->member->getKey(),
            'date' => $date->toDateString(),
            'project_id' => null,
            'task_id' => null,
            'hours' => 0,
        ]);

        // Assert
        $this->assertResponseCode($response, 200);
        $cellData = $response->json('data');
        $this->assertEquals(0.0, $cellData['hours']);
        $this->assertCount(0, $cellData['time_entry_ids']);

        // Verify time entry was deleted
        $this->assertDatabaseMissing('time_entries', [
            'id' => $timeEntry->getKey(),
        ]);
    }

    public function test_update_cell_validates_hours_range(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([
            'time-entries:create:own',
        ]);
        Passport::actingAs($data->user);

        // Act - hours > 24
        $response = $this->putJson(route('api.v1.timesheet.update-cell', [$data->organization->getKey()]), [
            'member_id' => $data->member->getKey(),
            'date' => Carbon::now()->toDateString(),
            'project_id' => null,
            'task_id' => null,
            'hours' => 25,
        ]);

        // Assert
        $response->assertStatus(422);
    }

    public function test_update_cell_validates_negative_hours(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([
            'time-entries:create:own',
        ]);
        Passport::actingAs($data->user);

        // Act
        $response = $this->putJson(route('api.v1.timesheet.update-cell', [$data->organization->getKey()]), [
            'member_id' => $data->member->getKey(),
            'date' => Carbon::now()->toDateString(),
            'project_id' => null,
            'task_id' => null,
            'hours' => -1,
        ]);

        // Assert
        $response->assertStatus(422);
    }

    public function test_update_cell_for_other_user_requires_create_all_permission(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([
            'time-entries:create:own',
        ]);
        $otherUser = User::factory()->create();
        $otherMember = Member::factory()->forOrganization($data->organization)->forUser($otherUser)->create();
        Passport::actingAs($data->user);

        // Act
        $response = $this->putJson(route('api.v1.timesheet.update-cell', [$data->organization->getKey()]), [
            'member_id' => $otherMember->getKey(),
            'date' => Carbon::now()->toDateString(),
            'project_id' => null,
            'task_id' => null,
            'hours' => 1.0,
        ]);

        // Assert
        $response->assertForbidden();
    }

    // ========== /timesheet/recent-tasks endpoint tests ==========

    public function test_recent_tasks_endpoint_fails_if_user_has_no_permission(): void
    {
        // Arrange
        $data = $this->createUserWithPermission();
        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(route('api.v1.timesheet.recent-tasks', [$data->organization->getKey()]));

        // Assert
        $response->assertForbidden();
    }

    public function test_recent_tasks_endpoint_returns_recent_project_task_combinations(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([
            'time-entries:view:own',
        ]);

        $project1 = Project::factory()->forOrganization($data->organization)->create();
        $project2 = Project::factory()->forOrganization($data->organization)->create();
        $task1 = Task::factory()->forProject($project1)->forOrganization($data->organization)->create();

        TimeEntry::factory()
            ->forOrganization($data->organization)
            ->forMember($data->member)
            ->forProject($project1)
            ->forTask($task1)
            ->create(['start' => Carbon::now()->subHours(1), 'end' => Carbon::now()]);
        TimeEntry::factory()
            ->forOrganization($data->organization)
            ->forMember($data->member)
            ->forProject($project2)
            ->create(['start' => Carbon::now()->subHours(2), 'end' => Carbon::now()->subHour()]);

        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(route('api.v1.timesheet.recent-tasks', [
            $data->organization->getKey(),
            'limit' => 5,
        ]));

        // Assert
        $this->assertResponseCode($response, 200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['project', 'task'],
            ],
        ]);

        $tasks = $response->json('data');
        $this->assertCount(2, $tasks);
        // Most recent first
        $this->assertEquals($project1->getKey(), $tasks[0]['project']['id']);
        $this->assertEquals($task1->getKey(), $tasks[0]['task']['id']);
        $this->assertEquals($project2->getKey(), $tasks[1]['project']['id']);
    }

    public function test_recent_tasks_endpoint_returns_empty_when_no_entries(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([
            'time-entries:view:own',
        ]);
        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(route('api.v1.timesheet.recent-tasks', [$data->organization->getKey()]));

        // Assert
        $this->assertResponseCode($response, 200);
        $this->assertCount(0, $response->json('data'));
    }
}
