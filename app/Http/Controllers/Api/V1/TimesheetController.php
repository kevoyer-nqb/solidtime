<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\V1\Timesheet\TimesheetCellUpdateRequest;
use App\Http\Requests\V1\Timesheet\TimesheetIndexRequest;
use App\Http\Requests\V1\Timesheet\TimesheetRecentTasksRequest;
use App\Http\Requests\V1\Timesheet\TimesheetWeeksRequest;
use App\Models\Member;
use App\Models\Organization;
use App\Service\TimesheetService;
use App\Service\TimezoneService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

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
    public function weeks(Organization $organization, TimesheetWeeksRequest $request, TimesheetService $timesheetService, TimezoneService $timezoneService): JsonResponse
    {
        $this->checkAnyPermission($organization, ['time-entries:view:own', 'time-entries:view:all']);

        $user = $this->user();
        $member = $this->member($organization);
        $timezone = $timezoneService->getTimezoneFromUser($user)->getName();
        $weekStartDay = $user->week_start->carbonWeekDay();

        $limit = (int) $request->input('limit', 8);
        $offset = (int) $request->input('offset', 0);

        $data = $timesheetService->getWeekList($organization, $member, $timezone, $weekStartDay, $limit, $offset);

        return response()->json([
            'data' => $data,
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
    public function index(Organization $organization, TimesheetIndexRequest $request, TimesheetService $timesheetService, TimezoneService $timezoneService): JsonResponse
    {
        $this->checkAnyPermission($organization, ['time-entries:view:own', 'time-entries:view:all']);

        $user = $this->user();
        $member = $this->member($organization);
        $timezone = $timezoneService->getTimezoneFromUser($user)->getName();

        $weekStart = Carbon::parse($request->input('week_start'), $timezone);
        $weekEnd = Carbon::parse($request->input('week_end'), $timezone);

        $data = $timesheetService->getWeekGrid($organization, $member, $weekStart, $weekEnd, $timezone);

        return response()->json([
            'data' => $data,
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
    public function updateCell(Organization $organization, TimesheetCellUpdateRequest $request, TimesheetService $timesheetService, TimezoneService $timezoneService): JsonResponse
    {
        /** @var Member $member */
        $member = Member::query()->findOrFail($request->input('member_id'));
        if ($member->user_id === Auth::id()) {
            $this->checkPermission($organization, 'time-entries:create:own');
        } else {
            $this->checkPermission($organization, 'time-entries:create:all');
        }

        $user = $this->user();
        $timezone = $timezoneService->getTimezoneFromUser($user)->getName();

        $data = $timesheetService->updateCell(
            $organization,
            $member,
            $request->input('date'),
            $request->input('project_id'),
            $request->input('task_id'),
            (float) $request->input('hours'),
            $timezone
        );

        return response()->json([
            'data' => $data,
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
    public function recentTasks(Organization $organization, TimesheetRecentTasksRequest $request, TimesheetService $timesheetService): JsonResponse
    {
        $this->checkAnyPermission($organization, ['time-entries:view:own', 'time-entries:view:all']);

        $member = $this->member($organization);
        $limit = (int) $request->input('limit', 10);

        $data = $timesheetService->getRecentTasks($organization, $member, $limit);

        return response()->json([
            'data' => $data,
        ]);
    }
}
