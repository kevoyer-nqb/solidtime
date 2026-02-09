<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->unsignedInteger('weekly_capacity')
                ->default(144000)
                ->after('billable_rate')
                ->comment('Weekly work capacity in seconds. Default 40h = 144000s.');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropColumn('weekly_capacity');
        });
    }
};
