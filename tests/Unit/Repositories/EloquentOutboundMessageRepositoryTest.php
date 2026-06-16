<?php

namespace Equidna\BirdFlock\Tests\Unit\Repositories;

use Equidna\BirdFlock\Models\OutboundMessage;
use Equidna\BirdFlock\Repositories\EloquentOutboundMessageRepository;
use Equidna\BirdFlock\Tests\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

final class EloquentOutboundMessageRepositoryTest extends TestCase
{
    private Capsule $capsule;
    private EloquentOutboundMessageRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capsule = new Capsule();
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ], 'bird_flock');
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        app()->instance('db', $this->capsule->getDatabaseManager());

        $this->createOutboundMessagesTable($this->capsule->getConnection()->getSchemaBuilder());
        $this->repository = new EloquentOutboundMessageRepository();
    }

    public function testCreateAndFindByIdempotencyKeyUseDefaultConnectionWhenNotConfigured(): void
    {
        $id = $this->repository->create($this->messageData([
            'id_outboundMessage' => '01J00000000000000000000001',
            'idempotencyKey' => 'default-key',
        ]));

        $message = $this->repository->findByIdempotencyKey('default-key');

        $this->assertSame('01J00000000000000000000001', $id);
        $this->assertSame('01J00000000000000000000001', $message['id_outboundMessage']);
    }

    public function testUpdateStatusSetsProviderStatusAndTimestamp(): void
    {
        $this->repository->create($this->messageData([
            'id_outboundMessage' => '01J00000000000000000000002',
        ]));

        $this->repository->updateStatus('01J00000000000000000000002', 'sent', [
            'provider_message_id' => 'PROV123',
        ]);

        $message = OutboundMessage::find('01J00000000000000000000002');

        $this->assertSame('sent', $message->status);
        $this->assertSame('PROV123', $message->providerMessageId);
        $this->assertNotNull($message->sentAt);
    }

    public function testResetForRetryClearsFailureFields(): void
    {
        $this->repository->create($this->messageData([
            'id_outboundMessage' => '01J00000000000000000000003',
            'status' => 'failed',
            'providerMessageId' => 'PROV123',
            'errorCode' => 'ERR',
            'errorMessage' => 'Failed',
            'attempts' => 2,
        ]));

        $this->repository->resetForRetry('01J00000000000000000000003', [
            'idempotencyKey' => 'retry-key',
        ]);

        $message = OutboundMessage::find('01J00000000000000000000003');

        $this->assertSame('queued', $message->status);
        $this->assertSame('retry-key', $message->idempotencyKey);
        $this->assertNull($message->providerMessageId);
        $this->assertNull($message->errorCode);
        $this->assertNull($message->errorMessage);
        $this->assertSame(0, $message->attempts);
    }

    public function testRepositoryUsesConfiguredConnection(): void
    {
        config(['bird-flock.database.connection' => 'bird_flock']);
        $this->createOutboundMessagesTable($this->capsule->getConnection('bird_flock')->getSchemaBuilder());

        $this->repository->create($this->messageData([
            'id_outboundMessage' => '01J00000000000000000000004',
            'idempotencyKey' => 'configured-key',
        ]));

        $this->assertSame(0, $this->capsule->getConnection()->table('bird_flock_outbound_messages')->count());
        $this->assertSame(1, $this->capsule->getConnection('bird_flock')->table('bird_flock_outbound_messages')->count());
        $this->assertSame(
            '01J00000000000000000000004',
            $this->repository->findByIdempotencyKey('configured-key')['id_outboundMessage']
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function messageData(array $overrides = []): array
    {
        return array_merge([
            'id_outboundMessage' => '01J00000000000000000000000',
            'channel' => 'sms',
            'to' => '+15005550001',
            'subject' => null,
            'templateKey' => null,
            'payload' => ['text' => 'Hello'],
            'status' => 'queued',
            'idempotencyKey' => null,
            'queuedAt' => now(),
        ], $overrides);
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
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->unique('idempotencyKey', 'uniq_idempotencyKey');
        });
    }
}
