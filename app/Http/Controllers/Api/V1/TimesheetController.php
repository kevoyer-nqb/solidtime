<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\V1\Timesheet\TimesheetCellUpdateRequest;
use App\Http\Requests\V1\Timesheet\TimesheetIndexRequest;
use App\Http\Requests\V1\Timesheet\TimesheetRecentTasksRequest;
use App\Http\Requests\V1\Timesheet\TimesheetWeeksRequest;
use App\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class TimesheetController extends Controller
{
    /**
     * Get week list with totals for the accordion layout
     *
     * Returns a list of weeks (most recent first) with total tracked seconds
     * per week. Used to render the week accordion headers.
     *
     * @operationId getTimesheetWeeks
     *
     * @throws AuthorizationException
     */
    public function weeks(Organization $organization, TimesheetWeeksRequest $request): JsonResponse
    {
        $this->checkAnyPermission($organization, ['time-entries:view:own', 'time-entries:view:all']);

        // TODO: TASK-02 — wire up TimesheetService
        return response()->json([
            'data' => [],
        ]);
    }

    /**
     * Get week grid data for a specific week
     *
     * Returns rows grouped by project+task with 7 day columns.
     * Each cell contains total hours and related time entry IDs.
     *
     * @operationId getTimesheetGrid
     *
     * @throws AuthorizationException
     */
    public function index(Organization $organization, TimesheetIndexRequest $request): JsonResponse
    {
        $this->checkAnyPermission($organization, ['time-entries:view:own', 'time-entries:view:all']);

        // TODO: TASK-02 — wire up TimesheetService
        return response()->json([
            'data' => [
                'week_start' => $request->input('week_start'),
                'week_end' => $request->input('week_end'),
                'rows' => [],
                'day_totals' => [0, 0, 0, 0, 0, 0, 0],
                'week_total' => 0,
            ],
        ]);
    }

    /**
     * Update a timesheet cell (create/update/delete time entries)
     *
     * When hours > 0: creates or updates time entries for the given
     * project+task+date combination.
     * When hours = 0: deletes all time entries for that cell.
     *
     * @operationId updateTimesheetCell
     *
     * @throws AuthorizationException
     */
    public function updateCell(Organization $organization, TimesheetCellUpdateRequest $request): JsonResponse
    {
        $this->checkAnyPermission($organization, ['time-entries:create:own', 'time-entries:create:all']);

        // TODO: TASK-02 — wire up TimesheetService
        return response()->json([
            'data' => [
                'date' => $request->input('date'),
                'hours' => $request->input('hours'),
                'time_entry_ids' => [],
            ],
        ]);
    }

    /**
     * Get recent tasks for the "Add Task" dropdown
     *
     * Returns recently used project+task combinations for the current user,
     * ordered by most recently used.
     *
     * @operationId getTimesheetRecentTasks
     *
     * @throws AuthorizationException
     */
    public function recentTasks(Organization $organization, TimesheetRecentTasksRequest $request): JsonResponse
    {
        $this->checkAnyPermission($organization, ['time-entries:view:own', 'time-entries:view:all']);

        // TODO: TASK-02 — wire up TimesheetService
        return response()->json([
            'data' => [],
        ]);
    }
}
