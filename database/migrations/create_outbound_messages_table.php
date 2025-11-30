<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

        Schema::create($tableName, function (Blueprint $table) {
            $table->char('id_outboundMessage', 26)->primary();
            $table->enum('channel', ['sms', 'whatsapp', 'email']);
            $table->string('to', 320);
            $table->string('from', 320)->nullable();
            $table->string('subject', 255)->nullable();
            $table->string('templateKey', 128)->nullable();
            $table->json('payload');
            $table->enum(
                'status',
                ['queued', 'sending', 'sent', 'delivered', 'failed', 'undeliverable']
            )->default('queued');
            $table->string('providerMessageId', 128)->nullable();
            $table->string('errorCode', 64)->nullable();
            $table->string('errorMessage', 1024)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('totalAttempts')->default(0);
            $table->string('idempotencyKey', 128)->nullable();
            $table->timestamp('queuedAt')->nullable();
            $table->timestamp('sentAt')->nullable();
            $table->timestamp('deliveredAt')->nullable();
            $table->timestamp('failedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();

            $table->unique('idempotencyKey', 'uniq_idempotencyKey');
            $table->index(['providerMessageId', 'status'], 'idx_provider_status');
            $table->index(['channel', 'status'], 'idx_channel_status');

            // Performance optimization indexes
            $table->index('createdAt', 'idx_created_at');
            $table->index(['status', 'attempts', 'createdAt'], 'idx_status_attempts_created');
            $table->index('providerMessageId', 'idx_provider_message_id');
            $table->index(['status', 'queuedAt'], 'idx_status_queued_at');
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

        Schema::dropIfExists($tableName);
    }
};
