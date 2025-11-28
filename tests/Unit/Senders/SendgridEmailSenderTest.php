<?php

/**
 * Unit tests for SendgridEmailSender.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Unit\Senders
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Unit\Senders;

use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Senders\SendgridEmailSender;
use Equidna\BirdFlock\Tests\TestCase;
use SendGrid;
use SendGrid\Response;

final class SendgridEmailSenderTest extends TestCase
{
    public function testSendSuccess(): void
    {
        $mockResponse = new Response(202, '{"message": "success"}', ['X-Message-Id' => ['MSG123']]);

        $mockClient = $this->createMock(SendGrid::class);
        $mockClient->expects($this->once())
            ->method('send')
            ->willReturn($mockResponse);

        $sender = new SendgridEmailSender(
            client: $mockClient,
            fromEmail: 'test@example.com',
            fromName: 'Test Sender',
            templates: []
        );

        $payload = new FlightPlan(
            channel: 'email',
            to: 'recipient@example.com',
            subject: 'Test Subject',
            text: 'Test body'
        );

        $result = $sender->send($payload);

        $this->assertSame('sent', $result->status);
        $this->assertSame('MSG123', $result->providerMessageId);
    }

    public function testSendClientError(): void
    {
        $mockResponse = new Response(400, '{"errors": [{"message": "Bad request"}]}', []);

        $mockClient = $this->createMock(SendGrid::class);
        $mockClient->expects($this->once())
            ->method('send')
            ->willReturn($mockResponse);

        $sender = new SendgridEmailSender(
            client: $mockClient,
            fromEmail: 'test@example.com',
            fromName: 'Test Sender',
            templates: []
        );

        $payload = new FlightPlan(
            channel: 'email',
            to: 'invalid@',
            subject: 'Test',
            text: 'Test'
        );

        $result = $sender->send($payload);

        $this->assertSame('undeliverable', $result->status);
        $this->assertSame('400', $result->errorCode);
    }

    public function testSendRateLimitError(): void
    {
        $mockResponse = new Response(429, '{"errors": [{"message": "Too many requests"}]}', []);

        $mockClient = $this->createMock(SendGrid::class);
        $mockClient->expects($this->once())
            ->method('send')
            ->willReturn($mockResponse);

        $sender = new SendgridEmailSender(
            client: $mockClient,
            fromEmail: 'test@example.com',
            fromName: 'Test Sender',
            templates: []
        );

        $payload = new FlightPlan(
            channel: 'email',
            to: 'test@example.com',
            subject: 'Test',
            text: 'Test'
        );

        $result = $sender->send($payload);

        $this->assertSame('failed', $result->status);
        $this->assertSame('429', $result->errorCode);
    }

    public function testSendServerError(): void
    {
        $mockResponse = new Response(500, '{"errors": [{"message": "Internal server error"}]}', []);

        $mockClient = $this->createMock(SendGrid::class);
        $mockClient->expects($this->once())
            ->method('send')
            ->willReturn($mockResponse);

        $sender = new SendgridEmailSender(
            client: $mockClient,
            fromEmail: 'test@example.com',
            fromName: 'Test Sender',
            templates: []
        );

        $payload = new FlightPlan(
            channel: 'email',
            to: 'test@example.com',
            subject: 'Test',
            text: 'Test'
        );

        $result = $sender->send($payload);

        $this->assertSame('failed', $result->status);
        $this->assertSame('500', $result->errorCode);
    }

    public function testCircuitBreakerPreventsCall(): void
    {
        $mockClient = $this->createMock(SendGrid::class);

        $sender = new SendgridEmailSender(
            client: $mockClient,
            fromEmail: 'test@example.com',
            fromName: 'Test Sender',
            templates: []
        );

        $payload = new FlightPlan(
            channel: 'email',
            to: 'test@example.com',
            subject: 'Test',
            text: 'Test'
        );

        // Open circuit manually
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
