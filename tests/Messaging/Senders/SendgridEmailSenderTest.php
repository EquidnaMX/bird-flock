<?php

namespace Equidna\BirdFlock\Tests\Messaging\Senders;

use PHPUnit\Framework\TestCase;
use Equidna\BirdFlock\Senders\SendgridEmailSender;
use Equidna\BirdFlock\DTO\FlightPlan;
use SendGrid;
use SendGrid\Response;

class SendgridEmailSenderTest extends TestCase
{
    public function testSendWithTemplate(): void
    {
        $response = $this->createMock(Response::class);
        $response->method('statusCode')->willReturn(202);
        $response->method('headers')->willReturn(['X-Message-Id' => 'msg123']);
        $response->method('body')->willReturn('');

        $client = $this->createMock(SendGrid::class);
        $client->expects($this->once())
            ->method('send')
            ->willReturn($response);

        $sender = new SendgridEmailSender(
            client: $client,
            fromEmail: 'sender@example.com',
            fromName: 'Sender Name',
            templates: ['welcome' => 'template_id_123'],
        );

        $payload = new FlightPlan(
            channel: 'email',
            to: 'recipient@example.com',
            templateKey: 'welcome',
            templateData: ['name' => 'John'],
        );

        $result = $sender->send($payload);

        $this->assertEquals('sent', $result->status);
        $this->assertEquals('msg123', $result->providerMessageId);
    }

    public function testSendWithRawContent(): void
    {
        $response = $this->createMock(Response::class);
        $response->method('statusCode')->willReturn(202);
        $response->method('headers')->willReturn(['X-Message-Id' => 'msg456']);
        $response->method('body')->willReturn('');

        $client = $this->createMock(SendGrid::class);
        $client->expects($this->once())
            ->method('send')
            ->willReturn($response);

        $sender = new SendgridEmailSender(
            client: $client,
            fromEmail: 'sender@example.com',
            fromName: 'Sender Name',
        );

        $payload = new FlightPlan(
            channel: 'email',
            to: 'recipient@example.com',
            subject: 'Test Subject',
            text: 'Plain text content',
            html: '<p>HTML content</p>',
        );

        $result = $sender->send($payload);

        $this->assertEquals('sent', $result->status);
    }

    public function testSendFailsWithLargeAttachment(): void
    {
        $client = $this->createMock(SendGrid::class);

        $sender = new SendgridEmailSender(
            client: $client,
            fromEmail: 'sender@example.com',
            fromName: 'Sender Name',
        );

        $largeContent = base64_encode(str_repeat('x', 11000000));

        $payload = new FlightPlan(
            channel: 'email',
            to: 'recipient@example.com',
            subject: 'Test',
            text: 'Test',
            metadata: [
                'attachments' => [
                    [
                        'content' => $largeContent,
                        'filename' => 'large.pdf',
                        'type' => 'application/pdf',
                    ],
                ],
            ],
        );

        $result = $sender->send($payload);

        $this->assertEquals('undeliverable', $result->status);
        $this->assertEquals('ATTACHMENT_TOO_LARGE', $result->errorCode);
    }

    public function testSendHandles429Error(): void
    {
        $response = $this->createMock(Response::class);
        $response->method('statusCode')->willReturn(429);
        $response->method('body')->willReturn('Rate limit exceeded');

        $client = $this->createMock(SendGrid::class);
        $client->expects($this->once())
            ->method('send')
            ->willReturn($response);

        $sender = new SendgridEmailSender(
            client: $client,
            fromEmail: 'sender@example.com',
            fromName: 'Sender Name',
        );

        $payload = new FlightPlan(
            channel: 'email',
            to: 'recipient@example.com',
            subject: 'Test',
            text: 'Test',
        );

        $result = $sender->send($payload);

        $this->assertEquals('failed', $result->status);
        $this->assertEquals('429', $result->errorCode);
    }
}





