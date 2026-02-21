<?php

declare(strict_types=1);

namespace App\Service;

use App\Models\Member;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TimesheetService
{
    /**
     * Get a list of weeks with total tracked seconds for each week.
     *
     * @return array<int, array{week_start: string, week_end: string, label: string, total_seconds: int}>
     */
    public function getWeekList(Organization $organization, Member $member, string $timezone, int $weekStartDay, int $limit = 8, int $offset = 0): array
    {
        $userTz = $timezone;
        $now = Carbon::now($userTz);

        $weeks = [];
        for ($i = $offset; $i < $offset + $limit; $i++) {
            $weekStart = $now->copy()->subWeeks($i)->startOfWeek($weekStartDay);
            $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();

            $totalSeconds = $this->getWeekTotalSeconds($organization, $member, $weekStart, $weekEnd, $userTz);

            $label = $this->getWeekLabel($weekStart, $now);

            $weeks[] = [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'label' => $label,
                'total_seconds' => $totalSeconds,
            ];
        }

        return $weeks;
    }

    /**
     * Get grid data for a specific week.
     *
     * Returns rows grouped by project+task with 7 day columns containing hours
     * and time entry IDs.
     *
     * @return array{week_start: string, week_end: string, rows: array<int, array{id: string, project: array|null, task: array|null, cells: array<int, array{date: string, hours: float, time_entry_ids: array<string>}>, total_hours: float}>, day_totals: array<int, float>, week_total: float}
     */
    public function getWeekGrid(Organization $organization, Member $member, Carbon $weekStart, Carbon $weekEnd, string $timezone): array
    {
        $days = [];
        $current = $weekStart->copy();
        for ($i = 0; $i < 7; $i++) {
            $days[] = $current->copy();
            $current->addDay();
        }

        // Fetch all time entries for this member in this week
        $timeEntries = TimeEntry::query()
            ->whereBelongsTo($organization, 'organization')
            ->where('user_id', $member->user_id)
            ->whereNotNull('end')
            ->where(function (Builder $query) use ($weekStart, $weekEnd, $timezone): void {
                // Convert start/end to user timezone for date comparison
                $startUtc = $weekStart->copy()->startOfDay()->timezone('UTC');
                $endUtc = $weekEnd->copy()->endOfDay()->timezone('UTC');
                $query->where('start', '>=', $startUtc)
                    ->where('start', '<=', $endUtc);
            })
            ->with(['project', 'task'])
            ->orderBy('start')
            ->get();

        // Group entries by project_id + task_id combination
        $grouped = $timeEntries->groupBy(function (TimeEntry $entry): string {
            return ($entry->project_id ?? 'none').':'.($entry->task_id ?? 'none');
        });

        $rows = [];
        foreach ($grouped as $key => $entries) {
            /** @var TimeEntry $firstEntry */
            $firstEntry = $entries->first();
            $project = $firstEntry->project;
            $task = $firstEntry->task;

            $cells = [];
            $rowTotal = 0.0;

            foreach ($days as $day) {
                $dayStart = $day->copy()->startOfDay()->timezone('UTC');
                $dayEnd = $day->copy()->endOfDay()->timezone('UTC');

                $dayEntries = $entries->filter(function (TimeEntry $entry) use ($dayStart, $dayEnd): bool {
                    return $entry->start >= $dayStart && $entry->start <= $dayEnd;
                });

                $seconds = $dayEntries->sum(function (TimeEntry $entry): int {
                    if ($entry->end === null) {
                        return 0;
                    }

                    return (int) $entry->end->diffInSeconds($entry->start);
                });

                $hours = round($seconds / 3600, 2);
                $rowTotal += $hours;

                $cells[] = [
                    'date' => $day->toDateString(),
                    'hours' => $hours,
                    'time_entry_ids' => $dayEntries->pluck('id')->values()->toArray(),
                ];
            }

            $rows[] = [
                'id' => $key,
                'project' => $project !== null ? [
                    'id' => $project->getKey(),
                    'name' => $project->name,
                    'color' => $project->color,
                ] : null,
                'task' => $task !== null ? [
                    'id' => $task->getKey(),
                    'name' => $task->name,
                ] : null,
                'cells' => $cells,
                'total_hours' => round($rowTotal, 2),
            ];
        }

        // Calculate day totals
        $dayTotals = array_fill(0, 7, 0.0);
        foreach ($rows as $row) {
            foreach ($row['cells'] as $i => $cell) {
                $dayTotals[$i] += $cell['hours'];
            }
        }
        $dayTotals = array_map(fn (float $total): float => round($total, 2), $dayTotals);

        $weekTotal = round(array_sum($dayTotals), 2);

        return [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'rows' => $rows,
            'day_totals' => $dayTotals,
            'week_total' => $weekTotal,
        ];
    }

    /**
     * Update a timesheet cell — create/update/delete time entries.
     *
     * When hours > 0: If entries exist for the cell, update the first and delete
     * the rest. If no entries exist, create a new one spanning the given hours.
     * When hours = 0: Delete all entries for the cell.
     *
     * @return array{date: string, hours: float, time_entry_ids: array<string>}
     */
    public function updateCell(
        Organization $organization,
        Member $member,
        string $date,
        ?string $projectId,
        ?string $taskId,
        float $hours,
        string $timezone
    ): array {
        $dateCarbon = Carbon::parse($date, $timezone);
        $dayStartUtc = $dateCarbon->copy()->startOfDay()->timezone('UTC');
        $dayEndUtc = $dateCarbon->copy()->endOfDay()->timezone('UTC');

        // Find existing entries for this cell
        $existingEntries = TimeEntry::query()
            ->whereBelongsTo($organization, 'organization')
            ->where('user_id', $member->user_id)
            ->whereNotNull('end')
            ->where('start', '>=', $dayStartUtc)
            ->where('start', '<=', $dayEndUtc)
            ->when($projectId !== null, function (Builder $query) use ($projectId): void {
                $query->where('project_id', $projectId);
            }, function (Builder $query): void {
                $query->whereNull('project_id');
            })
            ->when($taskId !== null, function (Builder $query) use ($taskId): void {
                $query->where('task_id', $taskId);
            }, function (Builder $query): void {
                $query->whereNull('task_id');
            })
            ->orderBy('start')
            ->get();

        if ($hours <= 0) {
            // Delete all entries for this cell
            foreach ($existingEntries as $entry) {
                $entry->delete();
            }

            return [
                'date' => $date,
                'hours' => 0.0,
                'time_entry_ids' => [],
            ];
        }

        $project = $projectId !== null ? Project::find($projectId) : null;
        $task = $taskId !== null ? Task::find($taskId) : null;

        $totalSeconds = (int) round($hours * 3600);
        $entryStart = $dateCarbon->copy()->startOfDay()->timezone('UTC')->addHours(9); // Default 9 AM start
        $entryEnd = $entryStart->copy()->addSeconds($totalSeconds);

        if ($existingEntries->isNotEmpty()) {
            /** @var TimeEntry $primaryEntry */
            $primaryEntry = $existingEntries->first();
            $entryStart = $primaryEntry->start;
            $entryEnd = $entryStart->copy()->addSeconds($totalSeconds);

            // Update the first entry
            $primaryEntry->end = $entryEnd;
            $primaryEntry->save();

            // Delete any additional entries for this cell (consolidate)
            foreach ($existingEntries->skip(1) as $extraEntry) {
                $extraEntry->delete();
            }

            return [
                'date' => $date,
                'hours' => $hours,
                'time_entry_ids' => [$primaryEntry->getKey()],
            ];
        }

        // Create a new entry
        $timeEntry = new TimeEntry;
        $timeEntry->description = '';
        $timeEntry->start = $entryStart;
        $timeEntry->end = $entryEnd;
        $timeEntry->billable = $project?->is_billable ?? false;
        $timeEntry->user_id = $member->user_id;
        $timeEntry->member_id = $member->getKey();
        $timeEntry->organization_id = $organization->getKey();
        $timeEntry->project_id = $projectId;
        $timeEntry->task_id = $taskId;
        $timeEntry->client_id = $project?->client_id;
        $timeEntry->tags = [];
        $timeEntry->is_imported = false;
        $timeEntry->setComputedAttributeValue('billable_rate');
        $timeEntry->save();

        return [
            'date' => $date,
            'hours' => $hours,
            'time_entry_ids' => [$timeEntry->getKey()],
        ];
    }

    /**
     * Get recently used project+task combinations for the current user.
     *
     * @return array<int, array{project: array|null, task: array|null}>
     */
    public function getRecentTasks(Organization $organization, Member $member, int $limit = 10): array
    {
        $recentEntries = TimeEntry::query()
            ->select(['project_id', 'task_id', DB::raw('MAX(start) as latest_start')])
            ->whereBelongsTo($organization, 'organization')
            ->where('user_id', $member->user_id)
            ->whereNotNull('end')
            ->groupBy('project_id', 'task_id')
            ->orderByDesc('latest_start')
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($recentEntries as $entry) {
            $project = $entry->project_id !== null ? Project::find($entry->project_id) : null;
            $task = $entry->task_id !== null ? Task::find($entry->task_id) : null;

            $result[] = [
                'project' => $project !== null ? [
                    'id' => $project->getKey(),
                    'name' => $project->name,
                    'color' => $project->color,
                ] : null,
                'task' => $task !== null ? [
                    'id' => $task->getKey(),
                    'name' => $task->name,
                ] : null,
            ];
        }

        return $result;
    }

    /**
     * Get total tracked seconds for a given week.
     */
    private function getWeekTotalSeconds(Organization $organization, Member $member, Carbon $weekStart, Carbon $weekEnd, string $timezone): int
    {
        $startUtc = $weekStart->copy()->startOfDay()->timezone('UTC');
        $endUtc = $weekEnd->copy()->endOfDay()->timezone('UTC');

        $result = TimeEntry::query()
            ->selectRaw('COALESCE(SUM(EXTRACT(EPOCH FROM ("end" - start))), 0) as total_seconds')
            ->whereBelongsTo($organization, 'organization')
            ->where('user_id', $member->user_id)
            ->whereNotNull('end')
            ->where('start', '>=', $startUtc)
            ->where('start', '<=', $endUtc)
            ->first();

        return (int) ($result->total_seconds ?? 0);
    }

    /**
     * Get a human-readable label for the week.
     */
    private function getWeekLabel(Carbon $weekStart, Carbon $now): string
    {
        $currentWeekStart = $now->copy()->startOfWeek($weekStart->dayOfWeek);

        if ($weekStart->isSameDay($currentWeekStart)) {
            return 'This Week';
        }

        if ($weekStart->isSameDay($currentWeekStart->copy()->subWeek())) {
            return 'Last Week';
        }

        return $weekStart->format('M d').' - '.$weekStart->copy()->addDays(6)->format('M d, Y');
    }
}
