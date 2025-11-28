<?php

/**
 * Unit tests for TwilioWhatsappSender.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Unit\Senders
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Unit\Senders;

use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Senders\TwilioWhatsappSender;
use Equidna\BirdFlock\Tests\TestCase;
use Twilio\Rest\Api\V2010\Account\MessageInstance;
use Twilio\Rest\Api\V2010\Account\MessageList;
use Twilio\Rest\Client;
use Twilio\Exceptions\RestException;

final class TwilioWhatsappSenderTest extends TestCase
{
    public function testSendSuccess(): void
    {
        $mockMessage = $this->createMock(MessageInstance::class);
        $mockMessage->sid = 'SM123';
        $mockMessage->status = 'queued';
        $mockMessage->to = 'whatsapp:+15551234567';
        $mockMessage->from = 'whatsapp:+15559876543';

        $mockMessageList = $this->createMock(MessageList::class);
        $mockMessageList->expects($this->once())
            ->method('create')
            ->with(
                $this->equalTo('whatsapp:+15551234567'),
                $this->arrayHasKey('body')
            )
            ->willReturn($mockMessage);

        $mockClient = $this->createMock(Client::class);
        $mockClient->messages = $mockMessageList;

        $payload = new FlightPlan(
            channel: 'whatsapp',
            to: '+15551234567',
            text: 'Test message'
        );

        $sender = new TwilioWhatsappSender($mockClient, 'whatsapp:+15559876543');
        $result = $sender->send($payload);

        $this->assertEquals('sent', $result->status);
        $this->assertSame('SM123', $result->providerMessageId);
    }

    public function testSendClientErrorPermanent(): void
    {
        $mockMessageList = $this->createMock(MessageList::class);
        $mockMessageList->expects($this->once())
            ->method('create')
            ->willThrowException(new RestException('Invalid number', 400, 21211));

        $mockClient = $this->createMock(Client::class);
        $mockClient->messages = $mockMessageList;

        $payload = new FlightPlan(
            channel: 'whatsapp',
            to: 'invalid',
            text: 'Test'
        );

        $sender = new TwilioWhatsappSender($mockClient, 'whatsapp:+15559876543');
        $result = $sender->send($payload);

        $this->assertEquals('undeliverable', $result->status);
        $this->assertStringContainsString('21211', $result->errorCode ?? '');
    }

    public function testSendRateLimitTransient(): void
    {
        $mockMessageList = $this->createMock(MessageList::class);
        $mockMessageList->expects($this->once())
            ->method('create')
            ->willThrowException(new RestException('Rate limit exceeded', 429, 20429));

        $mockClient = $this->createMock(Client::class);
        $mockClient->messages = $mockMessageList;

        $payload = new FlightPlan(
            channel: 'whatsapp',
            to: '+15551234567',
            text: 'Test'
        );

        $sender = new TwilioWhatsappSender($mockClient, 'whatsapp:+15559876543');
        $result = $sender->send($payload);

        $this->assertEquals('failed', $result->status);
    }

    public function testSendServerErrorTransient(): void
    {
        $mockMessageList = $this->createMock(MessageList::class);
        $mockMessageList->expects($this->once())
            ->method('create')
            ->willThrowException(new RestException('Internal server error', 500, 20500));

        $mockClient = $this->createMock(Client::class);
        $mockClient->messages = $mockMessageList;

        $payload = new FlightPlan(
            channel: 'whatsapp',
            to: '+15551234567',
            text: 'Test'
        );

        $sender = new TwilioWhatsappSender($mockClient, 'whatsapp:+15559876543');
        $result = $sender->send($payload);

        $this->assertEquals('failed', $result->status);
    }

    public function testCircuitBreakerPreventsCall(): void
    {
        $mockClient = $this->createMock(Client::class);

        $sender = new TwilioWhatsappSender($mockClient, 'whatsapp:+15559876543');

        // Force circuit open using reflection
        $reflection = new \ReflectionClass($sender);
        $cbProperty = $reflection->getProperty('circuitBreaker');
        $cbProperty->setAccessible(true);
        $cb = $cbProperty->getValue($sender);

        $cbReflection = new \ReflectionClass($cb);
        $stateProperty = $cbReflection->getProperty('cacheKeyState');
        $stateProperty->setAccessible(true);
        $stateKey = $stateProperty->getValue($cb);

        cache()->forever($stateKey, 'open');

        $payload = new FlightPlan(
            channel: 'whatsapp',
            to: '+15551234567',
            text: 'Test'
        );

        $result = $sender->send($payload);

        $this->assertEquals('failed', $result->status);
        $this->assertStringContainsString('CIRCUIT_OPEN', $result->errorCode ?? '');
    }
}
