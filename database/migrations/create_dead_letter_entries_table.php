<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $tableName = config(
            'bird-flock.dead_letter.table',
            env('BIRD_FLOCK_TABLE_PREFIX', 'bird_flock_') . 'dead_letters'
        );

        Schema::create($tableName, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('message_id', 26);
            $table->string('channel', 32);
            $table->json('payload');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('error_code', 64)->nullable();
            $table->string('error_message', 1024)->nullable();
            $table->longText('last_exception')->nullable();
            $table->timestamps();

            $table->index('message_id', 'dlq_message_id');
            $table->index('channel', 'dlq_channel');

            // Performance optimization indexes
            $table->index('created_at', 'idx_dlq_created_at');
            $table->index(['channel', 'created_at'], 'idx_dlq_channel_created');
        });
    }

    public function down(): void
    {
        $tableName = config(
            'bird-flock.dead_letter.table',
            env('BIRD_FLOCK_TABLE_PREFIX', 'bird_flock_') . 'dead_letters'
        );

        Schema::dropIfExists($tableName);
    }
};
