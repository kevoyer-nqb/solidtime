<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds composite indexes to optimize timesheet queries that filter
     * time entries by organization, user, and date range.
     */
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            // Composite index for timesheet grid/week queries:
            // WHERE organization_id = ? AND user_id = ? AND start >= ? AND start <= ?
            $table->index(
                ['organization_id', 'user_id', 'start'],
                'time_entries_org_user_start_index'
            );

            // Composite index for recent tasks grouping:
            // WHERE organization_id = ? AND user_id = ? GROUP BY project_id, task_id
            $table->index(
                ['organization_id', 'user_id', 'project_id', 'task_id'],
                'time_entries_org_user_project_task_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropIndex('time_entries_org_user_start_index');
            $table->dropIndex('time_entries_org_user_project_task_index');
        });
    }
};
