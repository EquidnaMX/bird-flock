<?php

/**
 * Unit tests for TwilioSmsSender.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Unit\Senders
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Unit\Senders;

use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Senders\TwilioSmsSender;
use Equidna\BirdFlock\Tests\TestCase;
use Twilio\Rest\Api\V2010\Account\MessageInstance;
use Twilio\Rest\Api\V2010\Account\MessageList;
use Twilio\Rest\Client;
use Twilio\Exceptions\RestException;

final class TwilioSmsSenderTest extends TestCase
{
    public function testSendSuccess(): void
    {
        $mockMessage = $this->createMock(MessageInstance::class);
        $mockMessage->sid = 'SM123';
        $mockMessage->status = 'queued';
        $mockMessage->to = '+15551234567';
        $mockMessage->from = '+15559876543';

        $mockMessageList = $this->createMock(MessageList::class);
        $mockMessageList->expects($this->once())
            ->method('create')
            ->with(
                $this->equalTo('+15551234567'),
                $this->arrayHasKey('body')
            )
            ->willReturn($mockMessage);

        $mockClient = $this->createMock(Client::class);
        $mockClient->messages = $mockMessageList;

        $sender = new TwilioSmsSender(
            client: $mockClient,
            from: '+15559876543'
        );

        $payload = new FlightPlan(
            channel: 'sms',
            to: '+15551234567',
            text: 'Test message'
        );

        $result = $sender->send($payload);

        $this->assertSame('sent', $result->status);
        $this->assertSame('SM123', $result->providerMessageId);
        $this->assertNull($result->errorCode);
    }

    public function testSendClientError(): void
    {
        $mockMessageList = $this->createMock(MessageList::class);
        $mockMessageList->expects($this->once())
            ->method('create')
            ->willThrowException(new RestException('Invalid phone number', 400, 21211));

        $mockClient = $this->createMock(Client::class);
        $mockClient->messages = $mockMessageList;

        $sender = new TwilioSmsSender(
            client: $mockClient,
            from: '+15559876543'
        );

        $payload = new FlightPlan(
            channel: 'sms',
            to: 'invalid',
            text: 'Test'
        );

        $result = $sender->send($payload);

        $this->assertSame('undeliverable', $result->status);
        $this->assertNotNull($result->errorCode);
    }

    public function testSendRateLimitError(): void
    {
        $mockMessageList = $this->createMock(MessageList::class);
        $mockMessageList->expects($this->once())
            ->method('create')
            ->willThrowException(new RestException('Too many requests', 429, 20429));

        $mockClient = $this->createMock(Client::class);
        $mockClient->messages = $mockMessageList;

        $sender = new TwilioSmsSender(
            client: $mockClient,
            from: '+15559876543'
        );

        $payload = new FlightPlan(
            channel: 'sms',
            to: '+15551234567',
            text: 'Test'
        );

        $result = $sender->send($payload);

        $this->assertSame('failed', $result->status);
        $this->assertNotNull($result->errorCode);
    }

    public function testSendServerError(): void
    {
        $mockMessageList = $this->createMock(MessageList::class);
        $mockMessageList->expects($this->once())
            ->method('create')
            ->willThrowException(new RestException('Internal server error', 500, 20500));

        $mockClient = $this->createMock(Client::class);
        $mockClient->messages = $mockMessageList;

        $sender = new TwilioSmsSender(
            client: $mockClient,
            from: '+15559876543'
        );

        $payload = new FlightPlan(
            channel: 'sms',
            to: '+15551234567',
            text: 'Test'
        );

        $result = $sender->send($payload);

        $this->assertSame('failed', $result->status);
    }

    public function testCircuitBreakerPreventsCall(): void
    {
        // Circuit breaker will be open if previous calls failed
        $mockClient = $this->createMock(Client::class);

        $sender = new TwilioSmsSender(
            client: $mockClient,
            from: '+15559876543'
        );

        $payload = new FlightPlan(
            channel: 'sms',
            to: '+15551234567',
            text: 'Test'
        );

        // Manually open circuit (simulate failures)
        $reflection = new \ReflectionClass($sender);
        $cbProperty = $reflection->getProperty('circuitBreaker');
        $cbProperty->setAccessible(true);
        $cb = $cbProperty->getValue($sender);

        for ($i = 0; $i < 5; $i++) {
            $cb->recordFailure();
        }

        $result = $sender->send($payload);

        $this->assertSame('failed', $result->status);
        $this->assertSame('CIRCUIT_OPEN', $result->errorCode);
    }
}
