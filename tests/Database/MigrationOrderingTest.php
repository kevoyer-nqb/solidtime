<?php

declare(strict_types=1);

namespace Tests\Database;

use Tests\TestCase;

class MigrationOrderingTest extends TestCase
{
    /**
     * Get all migration file names sorted alphabetically.
     *
     * @return array<int, string>
     */
    private function getMigrationFiles(): array
    {
        $migrationsPath = database_path('migrations');
        $files = glob($migrationsPath.'/*.php');
        if ($files === false) {
            return [];
        }

        return array_map('basename', $files);
    }

    /**
     * Extract timestamp prefix from a migration filename.
     */
    private function extractTimestamp(string $filename): ?string
    {
        if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_/', $filename, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function test_migration_filenames_are_chronologically_ordered(): void
    {
        $files = $this->getMigrationFiles();
        $this->assertNotEmpty($files, 'No migration files found');

        $timestamps = [];
        foreach ($files as $file) {
            $timestamp = $this->extractTimestamp($file);
            if ($timestamp !== null) {
                $timestamps[] = $timestamp;
            }
        }

        $sorted = $timestamps;
        sort($sorted);

        $this->assertEquals(
            $sorted,
            $timestamps,
            'Migration files are not in chronological order. Found out-of-order timestamps.'
        );
    }

    public function test_no_duplicate_migration_timestamps(): void
    {
        $files = $this->getMigrationFiles();
        $this->assertNotEmpty($files, 'No migration files found');

        $timestamps = [];
        foreach ($files as $file) {
            $timestamp = $this->extractTimestamp($file);
            if ($timestamp !== null) {
                $timestamps[] = $timestamp;
            }
        }

        $duplicates = array_diff_assoc($timestamps, array_unique($timestamps));

        $this->assertEmpty(
            $duplicates,
            'Duplicate migration timestamps found: '.implode(', ', array_unique($duplicates))
        );
    }

    public function test_all_migrations_have_valid_timestamp_prefix(): void
    {
        $files = $this->getMigrationFiles();
        $this->assertNotEmpty($files, 'No migration files found');

        $pattern = '/^\d{4}_\d{2}_\d{2}_\d{6}_/';

        foreach ($files as $file) {
            $this->assertMatchesRegularExpression(
                $pattern,
                $file,
                "Migration file '{$file}' does not have a valid timestamp prefix (expected YYYY_MM_DD_HHMMSS_)"
            );
        }
    }
}
