<?php

declare(strict_types=1);

namespace App\Service;

use App\Enums\Weekday;
use Illuminate\Support\Carbon;

class DateBoundaryService
{
    /**
     * Get the start of the week for a given date, timezone, and week start day.
     * Calculations are performed in local timezone to handle DST correctly.
     */
    public function getWeekStart(Carbon $date, string $timezone, Weekday $weekStartDay): Carbon
    {
        return $date->copy()
            ->setTimezone($timezone)
            ->startOfWeek($weekStartDay->carbonWeekDay())
            ->startOfDay();
    }

    /**
     * Get the end of the week for a given date, timezone, and week start day.
     * Returns the last second of the last day of the week.
     */
    public function getWeekEnd(Carbon $date, string $timezone, Weekday $weekStartDay): Carbon
    {
        return $this->getWeekStart($date, $timezone, $weekStartDay)
            ->addDays(6)
            ->endOfDay();
    }

    /**
     * Get the start and end of a day in the given timezone.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function getDayBoundaries(Carbon $date, string $timezone): array
    {
        $localDate = $date->copy()->setTimezone($timezone);

        return [
            'start' => $localDate->copy()->startOfDay(),
            'end' => $localDate->copy()->endOfDay(),
        ];
    }

    /**
     * Get week boundaries converted to UTC for database queries.
     * Calculates in local timezone first, then converts to UTC.
     *
     * CRITICAL: Never add fixed second offsets to UTC timestamps.
     * Always perform calculations in local timezone FIRST, then convert.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function getWeekBoundariesUtc(Carbon $date, string $timezone, Weekday $weekStartDay): array
    {
        $localStart = $this->getWeekStart($date, $timezone, $weekStartDay);
        $localEnd = $this->getWeekEnd($date, $timezone, $weekStartDay);

        return [
            'start' => $localStart->copy()->utc(),
            'end' => $localEnd->copy()->utc(),
        ];
    }

    /**
     * Get an array of 7 Carbon dates for the week (useful for timesheet grid).
     *
     * @return array<int, Carbon>
     */
    public function getWeekDates(Carbon $date, string $timezone, Weekday $weekStartDay): array
    {
        $weekStart = $this->getWeekStart($date, $timezone, $weekStartDay);
        $dates = [];

        for ($i = 0; $i < 7; $i++) {
            $dates[] = $weekStart->copy()->addDays($i);
        }

        return $dates;
    }
}
