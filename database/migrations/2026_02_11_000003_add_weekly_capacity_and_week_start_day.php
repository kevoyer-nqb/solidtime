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
        Schema::table('organizations', function (Blueprint $table): void {
            $table->integer('default_weekly_capacity')->unsigned()->default(2400);
            $table->string('week_start_day', 10)->default('monday');
            if (! Schema::hasColumn('organizations', 'timezone')) {
                $table->string('timezone', 50)->default('UTC');
            }
        });

        Schema::table('members', function (Blueprint $table): void {
            $table->integer('weekly_capacity')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn(['default_weekly_capacity', 'week_start_day']);
            if (Schema::hasColumn('organizations', 'timezone')) {
                $table->dropColumn('timezone');
            }
        });

        Schema::table('members', function (Blueprint $table): void {
            $table->dropColumn('weekly_capacity');
        });
    }
};
