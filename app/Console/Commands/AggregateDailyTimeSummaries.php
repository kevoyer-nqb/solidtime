<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use App\Service\DailyTimeSummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AggregateDailyTimeSummaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'summaries:aggregate
        {--date= : Specific date to aggregate (Y-m-d format)}
        {--days=1 : Number of past days to aggregate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregate time entries into daily time summaries for reporting';

    public function handle(DailyTimeSummaryService $service): int
    {
        $dateOption = $this->option('date');
        $daysOption = (int) $this->option('days');

        if ($dateOption !== null) {
            $date = Carbon::parse($dateOption);
            $this->info("Aggregating summaries for date: {$date->format('Y-m-d')}");
        } else {
            $date = Carbon::yesterday();
            $this->info("Aggregating summaries for the last {$daysOption} day(s) ending at yesterday");
        }

        $organizations = Organization::all();
        $orgCount = $organizations->count();
        $this->info("Processing {$orgCount} organization(s)...");

        foreach ($organizations as $organization) {
            /** @var Organization $organization */
            if ($dateOption !== null) {
                $service->aggregateForDate($organization, $date);
            } else {
                $endDate = Carbon::yesterday();
                $startDate = $endDate->copy()->subDays($daysOption - 1);
                $service->aggregateForDateRange($organization, $startDate, $endDate);
            }
        }

        $this->info('Daily time summary aggregation complete.');

        return Command::SUCCESS;
    }
}
