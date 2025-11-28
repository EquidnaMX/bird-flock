<?php

/**
 * Base test case for Bird Flock feature tests with database.
 *
 * NOTE: Feature tests require full Laravel application context including
 * DB facade, Queue facade, and event dispatcher. These tests are designed
 * to run within a real Laravel application, not in the package's isolated
 * test environment. Use these as integration test templates for your app.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Feature
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Feature;

use Equidna\BirdFlock\Tests\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

abstract class FeatureTestCase extends TestCase
{
    protected static Capsule $capsule;

    /**
     * Sets up in-memory SQLite database for feature tests.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$capsule = new Capsule();
        self::$capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        self::$capsule->setAsGlobal();
        self::$capsule->bootEloquent();
    }

    /**
     * Sets up database schema before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        $this->bindRepository();
    }

    /**
     * Binds the outbound message repository implementation.
     *
     * @return void
     */
    protected function bindRepository(): void
    {
        app()->bind(
            \Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface::class,
            \Equidna\BirdFlock\Repositories\EloquentOutboundMessageRepository::class
        );
    }

    /**
     * Creates database tables for testing.
     *
     * @return void
     */
    protected function createTables(): void
    {
        $prefix = config('bird-flock.tables.prefix', 'bird_flock_');
        $schema = self::$capsule->schema();

        // Create outbound_messages table
        if (!$schema->hasTable($prefix . 'outbound_messages')) {
            $schema->create($prefix . 'outbound_messages', function (Blueprint $table) {
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
                $table->timestamp('updatedAt')->useCurrent();

                $table->unique('idempotencyKey', 'uniq_idempotencyKey');
                $table->index(['providerMessageId', 'status'], 'idx_provider_status');
                $table->index(['channel', 'status'], 'idx_channel_status');
            });
        }

        // Create dead_letters table
        if (!$schema->hasTable($prefix . 'dead_letters')) {
            $schema->create($prefix . 'dead_letters', function (Blueprint $table) {
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
            });
        }
    }

    /**
     * Tears down database after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $prefix = config('bird-flock.tables.prefix', 'bird_flock_');
        $schema = self::$capsule->schema();

        $schema->dropIfExists($prefix . 'outbound_messages');
        $schema->dropIfExists($prefix . 'dead_letters');

        parent::tearDown();
    }
}
