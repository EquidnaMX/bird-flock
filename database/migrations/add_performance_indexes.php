<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add performance optimization indexes for Bird Flock outbound messages.
 *
 * This migration adds additional indexes to improve query performance for:
 * - Time-based queries (archival, reporting)
 * - Failed message analysis
 * - Provider-specific queries
 */
return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        $tableName = config(
            'bird-flock.tables.outbound_messages',
            config('bird-flock.tables.prefix', 'bird_flock_') . 'outbound_messages'
        );

        Schema::table($tableName, function (Blueprint $table) {
            // Index for time-based queries (archival, cleanup, reporting)
            $table->index('createdAt', 'idx_created_at');

            // Index for failed message analysis and retry queries
            $table->index(['status', 'attempts', 'createdAt'], 'idx_status_attempts_created');

            // Index for webhook updates by provider message ID
            $table->index('providerMessageId', 'idx_provider_message_id');

            // Index for finding messages by sendAt for scheduled dispatch
            $table->index(['status', 'queuedAt'], 'idx_status_queued_at');
        });

        $dlqTable = config(
            'bird-flock.dead_letter.table',
            config('bird-flock.tables.prefix', 'bird_flock_') . 'dead_letters'
        );

        // Add indexes for DLQ performance
        Schema::table($dlqTable, function (Blueprint $table) {
            $table->index('created_at', 'idx_dlq_created_at');
            $table->index(['channel', 'created_at'], 'idx_dlq_channel_created');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        $tableName = config(
            'bird-flock.tables.outbound_messages',
            config('bird-flock.tables.prefix', 'bird_flock_') . 'outbound_messages'
        );

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropIndex('idx_created_at');
            $table->dropIndex('idx_status_attempts_created');
            $table->dropIndex('idx_provider_message_id');
            $table->dropIndex('idx_status_queued_at');
        });

        $dlqTable = config(
            'bird-flock.dead_letter.table',
            config('bird-flock.tables.prefix', 'bird_flock_') . 'dead_letters'
        );

        Schema::table($dlqTable, function (Blueprint $table) {
            $table->dropIndex('idx_dlq_created_at');
            $table->dropIndex('idx_dlq_channel_created');
        });
    }
};
