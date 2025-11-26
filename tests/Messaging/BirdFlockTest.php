<?php

namespace Equidna\BirdFlock\Tests\Messaging;

use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Events\MessageQueued;
use Equidna\BirdFlock\Events\MessageRetryScheduled;
use Equidna\BirdFlock\BirdFlock;
use Equidna\BirdFlock\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class BirdFlockTest extends TestCase
{
    public function testDispatchCreatesMessage(): void
    {
        $dispatcher = app('events');
        $queued = [];
        $dispatcher->listen(MessageQueued::class, function ($event) use (&$queued) {
            $queued[] = $event;
        });

        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) {
                return $data['channel'] === 'sms'
                    && $data['to'] === '+15005550006'
                    && $data['status'] === 'queued';
            }))
            ->willReturn('msg_123');

        $repository->expects($this->never())
            ->method('findByIdempotencyKey');

        $payload = new FlightPlan(
            channel: 'sms',
            to: '+15005550006',
            text: 'Hello',
        );

        $messageId = BirdFlock::dispatch($payload, $repository);
        $this->assertNotEmpty($messageId);
        $this->assertNotEmpty($queued);
        $this->assertEquals('sms', $queued[0]->payload->channel);
        $dispatcher->forget(MessageQueued::class);
    }

    public function testDispatchWithIdempotencyKeyReturnsSameMessage(): void
    {
        $dispatcher = app('events');
        $queued = [];
        $dispatcher->listen(MessageQueued::class, function ($event) use (&$queued) {
            $queued[] = $event;
        });

        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByIdempotencyKey')
            ->with('idempotency_123')
            ->willReturn([
                'id_outboundMessage' => 'msg_existing',
                'status' => 'sent',
            ]);

        $repository->expects($this->never())
            ->method('create');

        $payload = new FlightPlan(
            channel: 'sms',
            to: '+15005550006',
            text: 'Hello',
            idempotencyKey: 'idempotency_123',
        );

        $messageId = BirdFlock::dispatch($payload, $repository);
        $this->assertEquals('msg_existing', $messageId);
        $this->assertCount(0, $queued);
        $dispatcher->forget(MessageQueued::class);
    }

    public function testDispatchWithIdempotencyKeyRetriesAfterFailure(): void
    {
        $dispatcher = app('events');
        $retries = [];
        $dispatcher->listen(MessageRetryScheduled::class, function ($event) use (&$retries) {
            $retries[] = $event;
        });

        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByIdempotencyKey')
            ->with('idempotency_456')
            ->willReturn([
                'id_outboundMessage' => 'msg_existing',
                'status' => 'failed',
            ]);

        $repository->expects($this->never())
            ->method('create');

        $repository->expects($this->once())
            ->method('resetForRetry')
            ->with('msg_existing', $this->arrayHasKey('payload'));

        $payload = new FlightPlan(
            channel: 'sms',
            to: '+15005550006',
            text: 'Hello',
            idempotencyKey: 'idempotency_456',
        );

        $messageId = BirdFlock::dispatch($payload, $repository);
        $this->assertEquals('msg_existing', $messageId);
        $this->assertEquals('sms', $retries[0]->channel);
        $dispatcher->forget(MessageRetryScheduled::class);
    }

    public function testDispatchWithIdempotencyKeyReturnsExistingWhenInFlight(): void
    {
        $dispatcher = app('events');
        $retries = [];
        $dispatcher->listen(MessageRetryScheduled::class, function ($event) use (&$retries) {
            $retries[] = $event;
        });

        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByIdempotencyKey')
            ->with('idempotency_inflight')
            ->willReturn([
                'id_outboundMessage' => 'msg_existing',
                'status' => 'queued',
            ]);

        $repository->expects($this->never())
            ->method('create');

        $repository->expects($this->never())
            ->method('resetForRetry');

        $payload = new FlightPlan(
            channel: 'sms',
            to: '+15005550006',
            text: 'Hello',
            idempotencyKey: 'idempotency_inflight',
        );

        $messageId = BirdFlock::dispatch($payload, $repository);
        $this->assertEquals('msg_existing', $messageId);
        $this->assertCount(0, $retries);
        $dispatcher->forget(MessageRetryScheduled::class);
    }
}





