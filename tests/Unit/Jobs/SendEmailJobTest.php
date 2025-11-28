<?php

/**
 * Unit tests for SendEmailJob retry and DLQ logic.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Unit\Jobs
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Unit\Jobs;

use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\DTO\ProviderSendResult;
use Equidna\BirdFlock\Jobs\SendEmailJob;
use Equidna\BirdFlock\MessageFactory;
use Equidna\BirdFlock\Senders\SendgridEmailSender;
use Equidna\BirdFlock\Support\DeadLetterService;
use Equidna\BirdFlock\Tests\TestCase;

final class SendEmailJobTest extends TestCase
{
    public function testHandleSuccessfulSend(): void
    {
        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->once())->method('incrementAttempts')->with('MSG123');
        $repository->expects($this->exactly(2))
            ->method('updateStatus')
            ->withConsecutive(
                ['MSG123', 'sending'],
                ['MSG123', 'sent', $this->arrayHasKey('provider_message_id')]
            );

        $mockSender = $this->createMock(SendgridEmailSender::class);
        $mockSender->expects($this->once())
            ->method('send')
            ->willReturn(ProviderSendResult::success('abc123'));

        $this->instance(OutboundMessageRepositoryInterface::class, $repository);

        $payload = new FlightPlan(
            channel: 'email',
            to: 'user@example.com',
            subject: 'Test',
            text: 'Test message'
        );

        $this->mock(MessageFactory::class, function ($mock) use ($mockSender) {
            $mock->shouldReceive('createSender')
                ->with('email')
                ->andReturn($mockSender);
        });

        $job = new SendEmailJob('MSG123', $payload);
        $job->handle($repository);
    }

    public function testHandleFailureWithRetries(): void
    {
        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->once())->method('incrementAttempts');
        $repository->expects($this->exactly(2))->method('updateStatus');

        $mockSender = $this->createMock(SendgridEmailSender::class);
        $mockSender->expects($this->once())
            ->method('send')
            ->willReturn(ProviderSendResult::failed('RATE_LIMIT', 'Too many requests'));

        $this->instance(OutboundMessageRepositoryInterface::class, $repository);

        $payload = new FlightPlan(
            channel: 'email',
            to: 'user@example.com',
            subject: 'Test',
            text: 'Test'
        );

        $this->mock(MessageFactory::class, function ($mock) use ($mockSender) {
            $mock->shouldReceive('createSender')->with('email')->andReturn($mockSender);
        });

        $job = new SendEmailJob('MSG123', $payload);
        $job->handle($repository);

        $this->assertTrue(true);
    }

    public function testHandleFailureMaxAttemptsRecordsDLQ(): void
    {
        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->once())->method('incrementAttempts');
        $repository->expects($this->exactly(2))->method('updateStatus');

        $mockSender = $this->createMock(SendgridEmailSender::class);
        $mockSender->expects($this->once())
            ->method('send')
            ->willReturn(ProviderSendResult::failed('ERROR', 'Failed'));

        $mockDLQ = $this->createMock(DeadLetterService::class);
        $mockDLQ->expects($this->once())
            ->method('record')
            ->with(
                'MSG123',
                'email',
                $this->isInstanceOf(FlightPlan::class),
                3,
                'ERROR',
                'Failed',
                null
            );

        $this->instance(OutboundMessageRepositoryInterface::class, $repository);
        $this->instance(DeadLetterService::class, $mockDLQ);

        $payload = new FlightPlan(
            channel: 'email',
            to: 'user@example.com',
            subject: 'Test',
            text: 'Test'
        );

        $this->mock(MessageFactory::class, function ($mock) use ($mockSender) {
            $mock->shouldReceive('createSender')->with('email')->andReturn($mockSender);
        });

        $job = new SendEmailJob('MSG123', $payload);

        $reflection = new \ReflectionClass($job);
        $triesProperty = $reflection->getProperty('tries');
        $triesProperty->setAccessible(true);
        $triesProperty->setValue($job, 1);

        $job->handle($repository);
    }

    public function testUndeliverableDoesNotRetry(): void
    {
        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->once())->method('incrementAttempts');
        $repository->expects($this->exactly(2))
            ->method('updateStatus')
            ->withConsecutive(
                ['MSG123', 'sending'],
                ['MSG123', 'undeliverable', $this->anything()]
            );

        $mockSender = $this->createMock(SendgridEmailSender::class);
        $mockSender->expects($this->once())
            ->method('send')
            ->willReturn(ProviderSendResult::undeliverable('INVALID', 'Invalid email'));

        $this->instance(OutboundMessageRepositoryInterface::class, $repository);

        $payload = new FlightPlan(
            channel: 'email',
            to: 'invalid',
            subject: 'Test',
            text: 'Test'
        );

        $this->mock(MessageFactory::class, function ($mock) use ($mockSender) {
            $mock->shouldReceive('createSender')->with('email')->andReturn($mockSender);
        });

        $job = new SendEmailJob('MSG123', $payload);
        $job->handle($repository);

        $this->assertTrue(true);
    }
}
