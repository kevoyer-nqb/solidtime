<?php

declare(strict_types=1);

namespace App\Service;

use App\Models\DailyTimeSummary;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DailyTimeSummaryService
{
    public function __construct(
        private readonly DateBoundaryService $dateBoundaryService,
    ) {}

    /**
     * Aggregate time entries for a specific date and organization into daily summaries.
     * This operation is idempotent -- existing summaries for the date are deleted and recreated.
     */
    public function aggregateForDate(Organization $organization, Carbon $date): void
    {
        $timezone = $organization->getEffectiveTimezone();
        $dayBoundaries = $this->dateBoundaryService->getDayBoundaries($date, $timezone);

        // Convert local day boundaries to UTC for database queries
        $utcStart = $dayBoundaries['start']->copy()->utc();
        $utcEnd = $dayBoundaries['end']->copy()->utc();

        $localDate = $dayBoundaries['start']->format('Y-m-d');

        // Delete existing summaries for this org + date (idempotent re-aggregation)
        DailyTimeSummary::where('organization_id', $organization->getKey())
            ->where('date', $localDate)
            ->delete();

        // Query time entries that overlap with this day, clipping to day boundaries
        // Uses LEAST/GREATEST to handle entries spanning midnight
        $summaries = DB::table('time_entries')
            ->select([
                'member_id',
                'project_id',
                'task_id',
                DB::raw("SUM(
                    EXTRACT(EPOCH FROM (
                        LEAST(COALESCE(\"end\", '{$utcEnd->toDateTimeString()}'::timestamp), '{$utcEnd->toDateTimeString()}'::timestamp)
                        - GREATEST(\"start\", '{$utcStart->toDateTimeString()}'::timestamp)
                    ))
                ) as total_seconds"),
                DB::raw("SUM(
                    CASE WHEN billable = true THEN
                        EXTRACT(EPOCH FROM (
                            LEAST(COALESCE(\"end\", '{$utcEnd->toDateTimeString()}'::timestamp), '{$utcEnd->toDateTimeString()}'::timestamp)
                            - GREATEST(\"start\", '{$utcStart->toDateTimeString()}'::timestamp)
                        ))
                    ELSE 0 END
                ) as billable_seconds"),
                DB::raw("SUM(
                    CASE WHEN billable = true AND billable_rate IS NOT NULL THEN
                        ROUND(
                            EXTRACT(EPOCH FROM (
                                LEAST(COALESCE(\"end\", '{$utcEnd->toDateTimeString()}'::timestamp), '{$utcEnd->toDateTimeString()}'::timestamp)
                                - GREATEST(\"start\", '{$utcStart->toDateTimeString()}'::timestamp)
                            )) * billable_rate / 3600.0
                        )
                    ELSE 0 END
                ) as billable_cost"),
            ])
            ->where('organization_id', $organization->getKey())
            ->where('start', '<', $utcEnd->toDateTimeString())
            ->where(function ($query) use ($utcStart): void {
                $query->where('end', '>', $utcStart->toDateTimeString())
                    ->orWhereNull('end');
            })
            ->groupBy(['member_id', 'project_id', 'task_id'])
            ->get();

        // Insert new summary rows
        foreach ($summaries as $summary) {
            $totalSeconds = max(0, (int) round((float) $summary->total_seconds));
            $billableSeconds = max(0, (int) round((float) $summary->billable_seconds));
            $billableCost = max(0, (int) round((float) $summary->billable_cost));

            if ($totalSeconds === 0) {
                continue;
            }

            DailyTimeSummary::create([
                'organization_id' => $organization->getKey(),
                'member_id' => $summary->member_id,
                'project_id' => $summary->project_id,
                'task_id' => $summary->task_id,
                'date' => $localDate,
                'total_seconds' => $totalSeconds,
                'billable_seconds' => $billableSeconds,
                'billable_cost' => $billableCost,
            ]);
        }
    }

    /**
     * Aggregate time entries for a date range and organization.
     * Loops through each date in the range and calls aggregateForDate.
     */
    public function aggregateForDateRange(Organization $organization, Carbon $startDate, Carbon $endDate): void
    {
        $currentDate = $startDate->copy()->startOfDay();
        $endDate = $endDate->copy()->startOfDay();

        while ($currentDate->lte($endDate)) {
            $this->aggregateForDate($organization, $currentDate);
            $currentDate->addDay();
        }
    }
}
