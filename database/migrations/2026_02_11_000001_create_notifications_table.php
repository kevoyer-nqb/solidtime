<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->uuidMorphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['notifiable_id', 'notifiable_type', 'read_at']);
            });
        } else {
            // Table may already exist (e.g. from previous notifications:table artisan command)
            // Ensure the composite index exists for unread count performance
            $indexExists = DB::select("
                SELECT 1 FROM pg_indexes
                WHERE tablename = 'notifications'
                AND indexname = 'notifications_notifiable_id_notifiable_type_read_at_index'
            ");

            if (empty($indexExists)) {
                Schema::table('notifications', function (Blueprint $table) {
                    $table->index(['notifiable_id', 'notifiable_type', 'read_at']);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
