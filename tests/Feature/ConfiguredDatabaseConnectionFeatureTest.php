<?php

namespace Equidna\BirdFlock\Tests\Feature;

use Equidna\BirdFlock\BirdFlock;
use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Support\DeadLetterService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;

final class ConfiguredDatabaseConnectionFeatureTest extends FeatureTestCase
{
    public function testPackageTablesUseConfiguredConnectionInsteadOfDefault(): void
    {
        Event::fake();

        self::$capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ], 'bird_flock');

        config(['bird-flock.database.connection' => 'bird_flock']);

        $defaultSchema = self::$capsule->getConnection()->getSchemaBuilder();
        $defaultSchema->dropIfExists('bird_flock_dead_letters');
        $defaultSchema->dropIfExists('bird_flock_outbound_messages');

        $birdFlockConnection = self::$capsule->getConnection('bird_flock');
        $this->createOutboundMessagesTable($birdFlockConnection->getSchemaBuilder());
        $this->createDeadLettersTable($birdFlockConnection->getSchemaBuilder());

        $singleId = BirdFlock::dispatch(new FlightPlan(
            channel: 'sms',
            to: '+15005550001',
            text: 'Single message',
            idempotencyKey: 'configured-connection-single'
        ));

        $batchIds = BirdFlock::dispatchBatch([
            new FlightPlan(
                channel: 'email',
                to: 'user@example.com',
                subject: 'Configured connection',
                text: 'Batch message',
                idempotencyKey: 'configured-connection-batch'
            ),
        ]);

        app(DeadLetterService::class)->record(
            messageId: $singleId,
            channel: 'sms',
            payload: new FlightPlan(channel: 'sms', to: '+15005550001', text: 'Failed message'),
            attempts: 3,
            errorCode: 'FAILED',
            errorMessage: 'Provider failure'
        );

        $this->assertFalse($defaultSchema->hasTable('bird_flock_outbound_messages'));
        $this->assertFalse($defaultSchema->hasTable('bird_flock_dead_letters'));

        $this->assertSame(2, $birdFlockConnection->table('bird_flock_outbound_messages')->count());
        $this->assertTrue(
            $birdFlockConnection->table('bird_flock_outbound_messages')
                ->where('id_outboundMessage', $singleId)
                ->exists()
        );
        $this->assertTrue(
            $birdFlockConnection->table('bird_flock_outbound_messages')
                ->where('id_outboundMessage', $batchIds[0])
                ->exists()
        );
        $this->assertSame(1, $birdFlockConnection->table('bird_flock_dead_letters')->count());
    }

    private function createOutboundMessagesTable(object $schema): void
    {
        $schema->create('bird_flock_outbound_messages', function (Blueprint $table) {
            $table->char('id_outboundMessage', 26)->primary();
            $table->enum('channel', ['sms', 'whatsapp', 'email']);
            $table->string('to', 320);
            $table->string('from', 320)->nullable();
            $table->string('subject', 255)->nullable();
            $table->string('templateKey', 128)->nullable();
            $table->json('payload');
            $table->enum('status', ['queued', 'sending', 'sent', 'delivered', 'failed', 'undeliverable'])->default('queued');
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
            $table->timestamp('updatedAt')->useCurrent();
            $table->unique('idempotencyKey', 'uniq_idempotencyKey');
        });
    }

    private function createDeadLettersTable(object $schema): void
    {
        $schema->create('bird_flock_dead_letters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('message_id', 26);
            $table->string('channel', 32);
            $table->json('payload');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('error_code', 64)->nullable();
            $table->string('error_message', 1024)->nullable();
            $table->longText('last_exception')->nullable();
            $table->timestamps();
        });
    }
}
