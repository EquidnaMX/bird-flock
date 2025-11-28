<?php

/**
 * Feature tests for batch dispatch functionality.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Feature
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Feature;

use Equidna\BirdFlock\BirdFlock;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Events\MessageQueued;
use Equidna\BirdFlock\Models\OutboundMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

final class BatchDispatchFeatureTest extends FeatureTestCase
{
    /**
     * Tests dispatching multiple messages in a batch.
     *
     * @return void
     */
    public function testDispatchBatchCreatesMultipleMessages(): void
    {
        Event::fake([MessageQueued::class]);

        $messages = [
            new FlightPlan(
                channel: 'sms',
                to: '+15005550001',
                text: 'Message 1'
            ),
            new FlightPlan(
                channel: 'email',
                to: 'user1@example.com',
                subject: 'Test Email',
                text: 'Message 2'
            ),
            new FlightPlan(
                channel: 'whatsapp',
                to: '+15005550002',
                text: 'Message 3'
            ),
        ];

        $messageIds = BirdFlock::dispatchBatch($messages);

        $this->assertCount(3, $messageIds);
        $this->assertDatabaseHas('bird_flock_outbound_messages', [
            'id_outboundMessage' => $messageIds[0],
            'channel' => 'sms',
            'to' => '+15005550001',
        ]);
        $this->assertDatabaseHas('bird_flock_outbound_messages', [
            'id_outboundMessage' => $messageIds[1],
            'channel' => 'email',
            'to' => 'user1@example.com',
        ]);
        $this->assertDatabaseHas('bird_flock_outbound_messages', [
            'id_outboundMessage' => $messageIds[2],
            'channel' => 'whatsapp',
            'to' => '+15005550002',
        ]);

        Event::assertDispatched(MessageQueued::class, 3);
    }

    /**
     * Tests batch dispatch with mixed channel types.
     *
     * @return void
     */
    public function testBatchDispatchHandlesMixedChannels(): void
    {
        $messages = [
            new FlightPlan(channel: 'sms', to: '+15005550001', text: 'SMS 1'),
            new FlightPlan(channel: 'sms', to: '+15005550002', text: 'SMS 2'),
            new FlightPlan(channel: 'email', to: 'test@example.com', subject: 'Test', text: 'Email 1'),
            new FlightPlan(channel: 'whatsapp', to: '+15005550003', text: 'WhatsApp 1'),
            new FlightPlan(channel: 'email', to: 'test2@example.com', subject: 'Test 2', text: 'Email 2'),
        ];

        $messageIds = BirdFlock::dispatchBatch($messages);

        $this->assertCount(5, $messageIds);

        $smsCount = OutboundMessage::where('channel', 'sms')->count();
        $emailCount = OutboundMessage::where('channel', 'email')->count();
        $whatsappCount = OutboundMessage::where('channel', 'whatsapp')->count();

        $this->assertEquals(2, $smsCount);
        $this->assertEquals(2, $emailCount);
        $this->assertEquals(1, $whatsappCount);
    }

    /**
     * Tests batch dispatch respects idempotency keys.
     *
     * @return void
     */
    public function testBatchDispatchRespectsIdempotency(): void
    {
        // First batch with idempotency keys
        $messages = [
            new FlightPlan(
                channel: 'sms',
                to: '+15005550001',
                text: 'Message 1',
                idempotencyKey: 'batch-test-1'
            ),
            new FlightPlan(
                channel: 'sms',
                to: '+15005550002',
                text: 'Message 2',
                idempotencyKey: 'batch-test-2'
            ),
        ];

        $firstIds = BirdFlock::dispatchBatch($messages);
        $this->assertCount(2, $firstIds);

        // Dispatch same batch again (should return existing IDs)
        $secondIds = BirdFlock::dispatchBatch($messages);
        $this->assertCount(2, $secondIds);
        $this->assertEquals($firstIds[0], $secondIds[0]);
        $this->assertEquals($firstIds[1], $secondIds[1]);

        // Verify only 2 records exist in database
        $count = OutboundMessage::count();
        $this->assertEquals(2, $count);
    }

    /**
     * Tests batch dispatch with scheduled messages.
     *
     * @return void
     */
    public function testBatchDispatchWithScheduledMessages(): void
    {
        $sendAt = Carbon::now()->addMinutes(30);

        $messages = [
            new FlightPlan(
                channel: 'sms',
                to: '+15005550001',
                text: 'Scheduled 1',
                sendAt: $sendAt
            ),
            new FlightPlan(
                channel: 'sms',
                to: '+15005550002',
                text: 'Scheduled 2',
                sendAt: $sendAt
            ),
        ];

        $messageIds = BirdFlock::dispatchBatch($messages);

        $this->assertCount(2, $messageIds);

        $messages = OutboundMessage::whereIn('id_outboundMessage', $messageIds)->get();

        foreach ($messages as $message) {
            $this->assertEquals('queued', $message->status);
            $payload = is_string($message->payload) ? json_decode($message->payload, true) : $message->payload;
            $this->assertArrayHasKey('sendAt', $payload);
        }
    }

    /**
     * Tests batch dispatch with large number of messages.
     *
     * @return void
     */
    public function testBatchDispatchHandlesLargeVolume(): void
    {
        $messages = [];
        for ($i = 0; $i < 100; $i++) {
            $messages[] = new FlightPlan(
                channel: 'sms',
                to: '+1500555' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                text: "Message {$i}"
            );
        }

        $messageIds = BirdFlock::dispatchBatch($messages);

        $this->assertCount(100, $messageIds);
        $this->assertEquals(100, OutboundMessage::count());
    }

    /**
     * Tests batch dispatch preserves message metadata.
     *
     * @return void
     */
    public function testBatchDispatchPreservesMetadata(): void
    {
        $messages = [
            new FlightPlan(
                channel: 'email',
                to: 'test@example.com',
                subject: 'Test Subject',
                text: 'Test Body',
                templateKey: 'welcome-email',
                metadata: ['user_id' => 123, 'campaign' => 'onboarding']
            ),
        ];

        $messageIds = BirdFlock::dispatchBatch($messages);

        $message = OutboundMessage::find($messageIds[0]);
        $payload = is_string($message->payload) ? json_decode($message->payload, true) : $message->payload;

        $this->assertEquals('welcome-email', $message->templateKey);
        $this->assertArrayHasKey('metadata', $payload);
        $this->assertEquals(123, $payload['metadata']['user_id']);
        $this->assertEquals('onboarding', $payload['metadata']['campaign']);
    }

    /**
     * Helper assertion for database has check.
     *
     * @param string $table   Table name
     * @param array  $data    Data to check
     *
     * @return void
     */
    protected function assertDatabaseHas(string $table, array $data): void
    {
        $query = OutboundMessage::query();

        foreach ($data as $key => $value) {
            $query->where($key, $value);
        }

        $this->assertTrue(
            $query->exists(),
            "Failed asserting that table [{$table}] contains matching record."
        );
    }
}
