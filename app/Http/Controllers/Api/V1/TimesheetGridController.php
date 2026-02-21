<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\V1\TimesheetGrid\TimesheetGridIndexRequest;
use App\Service\DateBoundaryService;
use App\Service\TimesheetGridService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class TimesheetGridController extends Controller
{
    /**
     * Get timesheet grid data for the authenticated member's week.
     *
     * Returns time entries grouped by project/task in a 7-day grid structure
     * with daily totals and a weekly total.
     *
     * @throws AuthorizationException
     *
     * @operationId getTimesheetGrid
     */
    public function index(
        \App\Models\Organization $organization,
        TimesheetGridIndexRequest $request,
        DateBoundaryService $dateBoundaryService,
        TimesheetGridService $timesheetGridService
    ): JsonResponse {
        $this->checkPermission($organization, 'time-entries:view:own');

        $member = $this->member($organization);

        // Parse the requested date or default to today
        $date = $request->has('date') && $request->input('date') !== null
            ? Carbon::parse($request->input('date'))
            : Carbon::now();

        $gridData = $timesheetGridService->buildGridData(
            $organization,
            $member,
            $date,
            $dateBoundaryService
        );

        return response()->json([
            'data' => $gridData,
        ]);
    }
}
