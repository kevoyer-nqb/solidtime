<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Enums\Weekday;
use App\Service\DateBoundaryService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(DateBoundaryService::class)]
class DateBoundaryServiceTest extends TestCase
{
    private DateBoundaryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DateBoundaryService();
    }

    public function test_get_week_start_monday_in_utc(): void
    {
        // 2026-02-12 is a Thursday
        $date = Carbon::parse('2026-02-12 14:00:00', 'UTC');

        $result = $this->service->getWeekStart($date, 'UTC', Weekday::Monday);

        $this->assertEquals('2026-02-09', $result->format('Y-m-d'));
        $this->assertEquals('00:00:00', $result->format('H:i:s'));
        $this->assertEquals('UTC', $result->timezone->getName());
    }

    public function test_get_week_start_sunday_in_utc(): void
    {
        // 2026-02-12 is a Thursday
        $date = Carbon::parse('2026-02-12 14:00:00', 'UTC');

        $result = $this->service->getWeekStart($date, 'UTC', Weekday::Sunday);

        $this->assertEquals('2026-02-08', $result->format('Y-m-d'));
        $this->assertEquals('00:00:00', $result->format('H:i:s'));
    }

    public function test_get_week_start_saturday_in_utc(): void
    {
        // Retail use case: week starts on Saturday
        // 2026-02-12 is a Thursday
        $date = Carbon::parse('2026-02-12 14:00:00', 'UTC');

        $result = $this->service->getWeekStart($date, 'UTC', Weekday::Saturday);

        $this->assertEquals('2026-02-07', $result->format('Y-m-d'));
        $this->assertEquals('00:00:00', $result->format('H:i:s'));
    }

    public function test_get_week_start_across_dst_spring_forward(): void
    {
        // America/New_York springs forward on 2026-03-08 at 2:00 AM
        // 2026-03-09 is a Monday (day after spring forward)
        $date = Carbon::parse('2026-03-09 12:00:00', 'America/New_York');

        $result = $this->service->getWeekStart($date, 'America/New_York', Weekday::Monday);

        // Week start should be Monday 2026-03-09 itself
        $this->assertEquals('2026-03-09', $result->format('Y-m-d'));
        $this->assertEquals('00:00:00', $result->format('H:i:s'));
        $this->assertEquals('America/New_York', $result->timezone->getName());
    }

    public function test_get_week_start_across_dst_fall_back(): void
    {
        // America/New_York falls back on 2026-11-01 at 2:00 AM
        // 2026-11-02 is a Monday (day after fall back)
        $date = Carbon::parse('2026-11-02 12:00:00', 'America/New_York');

        $result = $this->service->getWeekStart($date, 'America/New_York', Weekday::Monday);

        // Week start should be Monday 2026-11-02 itself
        $this->assertEquals('2026-11-02', $result->format('Y-m-d'));
        $this->assertEquals('00:00:00', $result->format('H:i:s'));
        $this->assertEquals('America/New_York', $result->timezone->getName());
    }

    public function test_get_week_boundaries_utc_span_correct_duration(): void
    {
        // During DST spring forward, a week in local time is 167 hours in UTC (not 168)
        // Week containing spring forward in America/New_York (March 8, 2026)
        $date = Carbon::parse('2026-03-10 12:00:00', 'America/New_York');

        $boundaries = $this->service->getWeekBoundariesUtc($date, 'America/New_York', Weekday::Monday);

        // Start and end should be exactly 7 days apart in local time
        $localStart = $boundaries['start']->copy()->setTimezone('America/New_York');
        $localEnd = $boundaries['end']->copy()->setTimezone('America/New_York');

        // The local dates should span exactly 7 days
        $this->assertEquals(7, $localStart->startOfDay()->diffInDays($localEnd->startOfDay()));
    }

    public function test_get_day_boundaries_across_dst(): void
    {
        // Day of spring forward in America/New_York (2026-03-08)
        $date = Carbon::parse('2026-03-08 12:00:00', 'America/New_York');

        $boundaries = $this->service->getDayBoundaries($date, 'America/New_York');

        $this->assertEquals('2026-03-08', $boundaries['start']->format('Y-m-d'));
        $this->assertEquals('00:00:00', $boundaries['start']->format('H:i:s'));
        $this->assertEquals('2026-03-08', $boundaries['end']->format('Y-m-d'));
        $this->assertEquals('23:59:59', $boundaries['end']->format('H:i:s'));
    }

    public function test_get_day_boundaries_utc_conversion(): void
    {
        // 2026-02-10 in Asia/Tokyo (UTC+9)
        $date = Carbon::parse('2026-02-10 12:00:00', 'Asia/Tokyo');

        $boundaries = $this->service->getDayBoundaries($date, 'Asia/Tokyo');

        // Start of day in Tokyo = 00:00 JST = 15:00 UTC previous day
        $utcStart = $boundaries['start']->copy()->utc();
        $this->assertEquals('2026-02-09', $utcStart->format('Y-m-d'));
        $this->assertEquals('15:00:00', $utcStart->format('H:i:s'));

        // End of day in Tokyo = 23:59:59 JST = 14:59:59 UTC same day
        $utcEnd = $boundaries['end']->copy()->utc();
        $this->assertEquals('2026-02-10', $utcEnd->format('Y-m-d'));
        $this->assertEquals('14:59:59', $utcEnd->format('H:i:s'));
    }

    public function test_week_start_with_various_iana_timezones(): void
    {
        $date = Carbon::parse('2026-02-12 14:00:00', 'UTC');

        $timezones = ['Europe/London', 'Asia/Tokyo', 'Australia/Sydney', 'Pacific/Auckland'];

        foreach ($timezones as $timezone) {
            $result = $this->service->getWeekStart($date, $timezone, Weekday::Monday);

            // The result should be a Monday at start of day in the given timezone
            $this->assertEquals(Carbon::MONDAY, $result->dayOfWeek, "Week start should be Monday in $timezone");
            $this->assertEquals('00:00:00', $result->format('H:i:s'), "Week start should be start of day in $timezone");
            $this->assertEquals($timezone, $result->timezone->getName(), "Timezone should be $timezone");
        }
    }

    public function test_week_start_configurable_per_organization(): void
    {
        $date = Carbon::parse('2026-02-12 14:00:00', 'UTC');

        // Test with each weekday as start
        $expectedDates = [
            Weekday::Monday => '2026-02-09',
            Weekday::Tuesday => '2026-02-10',
            Weekday::Wednesday => '2026-02-11',
            Weekday::Thursday => '2026-02-12',
            Weekday::Friday => '2026-02-06',
            Weekday::Saturday => '2026-02-07',
            Weekday::Sunday => '2026-02-08',
        ];

        foreach ($expectedDates as $weekday => $expectedDate) {
            $result = $this->service->getWeekStart($date, 'UTC', $weekday);
            $this->assertEquals($expectedDate, $result->format('Y-m-d'), "Week start with {$weekday->value} should be $expectedDate");
        }
    }

    public function test_get_week_end(): void
    {
        $date = Carbon::parse('2026-02-12 14:00:00', 'UTC');

        $result = $this->service->getWeekEnd($date, 'UTC', Weekday::Monday);

        // Week starts Monday 2026-02-09, ends Sunday 2026-02-15
        $this->assertEquals('2026-02-15', $result->format('Y-m-d'));
        $this->assertEquals('23:59:59', $result->format('H:i:s'));
    }

    public function test_get_week_dates_returns_seven_dates(): void
    {
        $date = Carbon::parse('2026-02-12 14:00:00', 'UTC');

        $dates = $this->service->getWeekDates($date, 'UTC', Weekday::Monday);

        $this->assertCount(7, $dates);
        $this->assertEquals('2026-02-09', $dates[0]->format('Y-m-d'));
        $this->assertEquals('2026-02-10', $dates[1]->format('Y-m-d'));
        $this->assertEquals('2026-02-11', $dates[2]->format('Y-m-d'));
        $this->assertEquals('2026-02-12', $dates[3]->format('Y-m-d'));
        $this->assertEquals('2026-02-13', $dates[4]->format('Y-m-d'));
        $this->assertEquals('2026-02-14', $dates[5]->format('Y-m-d'));
        $this->assertEquals('2026-02-15', $dates[6]->format('Y-m-d'));
    }

    public function test_get_week_boundaries_utc_returns_utc_timestamps(): void
    {
        // Test with a non-UTC timezone
        $date = Carbon::parse('2026-02-12 14:00:00', 'America/New_York');

        $boundaries = $this->service->getWeekBoundariesUtc($date, 'America/New_York', Weekday::Monday);

        $this->assertEquals('UTC', $boundaries['start']->timezone->getName());
        $this->assertEquals('UTC', $boundaries['end']->timezone->getName());

        // Week start in New York = Monday 2026-02-09 00:00 EST = 2026-02-09 05:00 UTC
        $this->assertEquals('2026-02-09', $boundaries['start']->format('Y-m-d'));
        $this->assertEquals('05:00:00', $boundaries['start']->format('H:i:s'));
    }
}
