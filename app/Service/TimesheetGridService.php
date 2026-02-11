<?php

declare(strict_types=1);

namespace App\Service;

use App\Models\Member;
use App\Models\Organization;
use App\Models\TimeEntry;
use Illuminate\Support\Carbon;

class TimesheetGridService
{
    /**
     * Build grid data for a member's week, grouping time entries by project+task
     * with 7 day cells, daily totals, and a weekly total.
     *
     * @return array{
     *     week_start: string,
     *     week_end: string,
     *     days: array<string>,
     *     rows: array<int, array{
     *         project_id: string|null,
     *         project_name: string,
     *         project_color: string,
     *         task_id: string|null,
     *         task_name: string|null,
     *         cells: array<int, array{date: string, total_seconds: int, time_entry_ids: array<string>}>,
     *         row_total: int
     *     }>,
     *     daily_totals: array<int>,
     *     weekly_total: int
     * }
     */
    public function buildGridData(
        Organization $organization,
        Member $member,
        Carbon $date,
        DateBoundaryService $dateBoundaryService
    ): array {
        $timezone = $organization->getEffectiveTimezone();
        $weekStartDay = $organization->week_start_day;

        // Get the 7 local dates for the week
        $weekDates = $dateBoundaryService->getWeekDates($date, $timezone, $weekStartDay);

        // Get UTC boundaries for the database query
        $weekBoundariesUtc = $dateBoundaryService->getWeekBoundariesUtc($date, $timezone, $weekStartDay);

        // Build day strings array for the response
        $days = array_map(
            fn (Carbon $d): string => $d->format('Y-m-d'),
            $weekDates
        );

        // Query time entries for this member's week
        // Only completed entries (end is not null), excluding running entries
        $entries = TimeEntry::query()
            ->where('organization_id', $organization->getKey())
            ->where('member_id', $member->getKey())
            ->where('start', '>=', $weekBoundariesUtc['start'])
            ->where('end', '<=', $weekBoundariesUtc['end'])
            ->whereNotNull('end')
            ->with(['project:id,name,color,is_billable', 'task:id,name'])
            ->get();

        // Group entries by project_id|task_id composite key
        $grouped = $entries->groupBy(
            fn (TimeEntry $entry): string => ($entry->project_id ?? 'none') . '|' . ($entry->task_id ?? 'none')
        );

        // Build rows
        $rows = [];
        foreach ($grouped as $key => $groupEntries) {
            /** @var TimeEntry $firstEntry */
            $firstEntry = $groupEntries->first();

            // Initialize 7 cells, one per day
            $cells = [];
            foreach ($days as $dayString) {
                $cells[$dayString] = [
                    'date' => $dayString,
                    'total_seconds' => 0,
                    'time_entry_ids' => [],
                ];
            }

            // Assign entries to their local date
            foreach ($groupEntries as $entry) {
                /** @var TimeEntry $entry */
                $localDate = Carbon::parse($entry->start)
                    ->setTimezone($timezone)
                    ->format('Y-m-d');

                // Only include if the local date falls within our week
                if (isset($cells[$localDate])) {
                    $duration = $entry->getDuration();
                    $seconds = $duration !== null ? (int) $duration->totalSeconds : 0;
                    $cells[$localDate]['total_seconds'] += $seconds;
                    $cells[$localDate]['time_entry_ids'][] = $entry->id;
                }
            }

            // Convert cells to indexed array (ordered by days)
            $cellsArray = array_values($cells);

            // Calculate row total
            $rowTotal = 0;
            foreach ($cellsArray as $cell) {
                $rowTotal += $cell['total_seconds'];
            }

            $rows[] = [
                'project_id' => $firstEntry->project_id,
                'project_name' => $firstEntry->project?->name ?? '(No Project)',
                'project_color' => $firstEntry->project?->color ?? '#808080',
                'task_id' => $firstEntry->task_id,
                'task_name' => $firstEntry->task?->name,
                'cells' => $cellsArray,
                'row_total' => $rowTotal,
            ];
        }

        // Calculate daily totals
        $dailyTotals = array_fill(0, 7, 0);
        foreach ($rows as $row) {
            foreach ($row['cells'] as $i => $cell) {
                $dailyTotals[$i] += $cell['total_seconds'];
            }
        }

        // Calculate weekly total
        $weeklyTotal = array_sum($dailyTotals);

        return [
            'week_start' => $days[0],
            'week_end' => $days[6],
            'days' => $days,
            'rows' => $rows,
            'daily_totals' => $dailyTotals,
            'weekly_total' => $weeklyTotal,
        ];
    }
}
