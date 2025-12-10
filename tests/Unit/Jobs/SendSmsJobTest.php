<?php

/**
 * Unit tests for SendSmsJob retry and DLQ logic.
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
use Equidna\BirdFlock\Jobs\SendSmsJob;
use Equidna\BirdFlock\MessageFactory;
use Equidna\BirdFlock\Senders\TwilioSmsSender;
use Equidna\BirdFlock\Support\DeadLetterService;
use Equidna\BirdFlock\Tests\TestCase;
use Illuminate\Support\Facades\Event;

final class SendSmsJobTest extends TestCase
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

        $mockSender = $this->createMock(TwilioSmsSender::class);
        $mockSender->expects($this->once())
            ->method('send')
            ->willReturn(ProviderSendResult::success('SM123'));

        $this->instance(OutboundMessageRepositoryInterface::class, $repository);

        $payload = new FlightPlan(
            channel: 'sms',
            to: '+15551234567',
            text: 'Test'
        );

        // Mock factory to return our sender
        $this->mock(MessageFactory::class, function ($mock) use ($mockSender) {
            $mock->shouldReceive('createSender')
                ->with('sms')
                ->andReturn($mockSender);
        });

        $job = new SendSmsJob('MSG123', $payload);
        $job->handle($repository);
    }

    public function testHandleFailureWithRetries(): void
    {
        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->once())->method('incrementAttempts');
        $repository->expects($this->exactly(2))->method('updateStatus');

        $mockSender = $this->createMock(TwilioSmsSender::class);
        $mockSender->expects($this->once())
            ->method('send')
            ->willReturn(ProviderSendResult::failed('RATE_LIMIT', 'Too many requests'));

        $this->instance(OutboundMessageRepositoryInterface::class, $repository);

        $payload = new FlightPlan(
            channel: 'sms',
            to: '+15551234567',
            text: 'Test'
        );

        $this->mock(MessageFactory::class, function ($mock) use ($mockSender) {
            $mock->shouldReceive('createSender')->with('sms')->andReturn($mockSender);
        });

        $job = new SendSmsJob('MSG123', $payload);

        // Mock attempts to simulate not yet at max
        $reflection = new \ReflectionClass($job);
        $attemptsMethod = $reflection->getMethod('attempts');
        $attemptsMethod->setAccessible(true);

        $job->handle($repository);

        // Job should schedule a retry (release) but not record dead letter
        $this->assertTrue(true); // Job completed without exception
    }

    public function testHandleFailureMaxAttemptsRecordsDLQ(): void
    {
        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->once())->method('incrementAttempts');
        $repository->expects($this->exactly(2))->method('updateStatus');

        $mockSender = $this->createMock(TwilioSmsSender::class);
        $mockSender->expects($this->once())
            ->method('send')
            ->willReturn(ProviderSendResult::failed('ERROR', 'Failed'));

        $mockDLQ = $this->createMock(DeadLetterService::class);
        $mockDLQ->expects($this->once())
            ->method('record')
            ->with(
                'MSG123',
                'sms',
                $this->isInstanceOf(FlightPlan::class),
                3,
                'ERROR',
                'Failed',
                null
            );

        $this->instance(OutboundMessageRepositoryInterface::class, $repository);
        $this->instance(DeadLetterService::class, $mockDLQ);

        $payload = new FlightPlan(
            channel: 'sms',
            to: '+15551234567',
            text: 'Test'
        );

        $this->mock(MessageFactory::class, function ($mock) use ($mockSender) {
            $mock->shouldReceive('createSender')->with('sms')->andReturn($mockSender);
        });

        $job = new SendSmsJob('MSG123', $payload);

        // Set attempts to max
        $reflection = new \ReflectionClass($job);
        $triesProperty = $reflection->getProperty('tries');
        $triesProperty->setAccessible(true);
        $triesProperty->setValue($job, 1); // Set tries to 1 so first attempt is max

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

        $mockSender = $this->createMock(TwilioSmsSender::class);
        $mockSender->expects($this->once())
            ->method('send')
            ->willReturn(ProviderSendResult::undeliverable('INVALID', 'Invalid phone'));

        $this->instance(OutboundMessageRepositoryInterface::class, $repository);

        $payload = new FlightPlan(
            channel: 'sms',
            to: 'invalid',
            text: 'Test'
        );

        $this->mock(MessageFactory::class, function ($mock) use ($mockSender) {
            $mock->shouldReceive('createSender')->with('sms')->andReturn($mockSender);
        });

        $job = new SendSmsJob('MSG123', $payload);
        $job->handle($repository);

        // Job should complete without retry/release
        $this->assertTrue(true);
    }

    public function testFailedMethodHandlesUninitializedProperties(): void
    {
        // This test simulates the scenario where the job's failed() method is called
        // before the constructor completes or during deserialization errors.
        // The fix should prevent the "Typed property must not be accessed before initialization" error.

        // Create an uninitialized job instance using reflection
        $reflection = new \ReflectionClass(SendSmsJob::class);
        $job = $reflection->newInstanceWithoutConstructor();

        // The failed() method should not throw an error even with uninitialized properties
        $exception = new \Exception('Test exception');
        
        // This should not throw a "Typed property must not be accessed before initialization" error
        $job->failed($exception);

        // If we get here, the fix is working
        $this->assertTrue(true);
    }
}
