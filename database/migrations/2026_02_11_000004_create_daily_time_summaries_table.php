<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_time_summaries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignUuid('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->date('date');
            $table->integer('total_seconds')->default(0);
            $table->integer('billable_seconds')->default(0);
            $table->integer('billable_cost')->default(0);
            $table->timestamps();

            // Unique constraint for idempotent aggregation
            // Note: PostgreSQL treats NULLs as distinct in unique constraints by default.
            // Application logic handles deduplication for nullable columns.
            $table->unique(
                ['organization_id', 'member_id', 'project_id', 'task_id', 'date'],
                'daily_time_summaries_composite_unique'
            );

            // Index for common reporting queries
            $table->index(['organization_id', 'date'], 'daily_time_summaries_org_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_time_summaries');
    }
};
