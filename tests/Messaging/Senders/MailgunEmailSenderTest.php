<?php

/**
 * Mailgun email sender unit tests.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Messaging\Senders
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Messaging\Senders;

use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Senders\Mailgun\MailgunEmailSender;
use Exception;
use PHPUnit\Framework\TestCase;
use Mailgun\Mailgun;
use Mailgun\Api\Message as MessageApi;

/**
 * Tests Mailgun email sender behavior.
 */
final class MailgunEmailSenderTest extends TestCase
{
    /**
     * Returns a successful email send result.
     *
     * @return void
     */
    public function testSendSuccessReturnsSuccessResult(): void
    {
        $response = new class {
            public function getId(): string
            {
                return '<20250101010101.1.ABC123@example.com>';
            }

            public function getMessage(): string
            {
                return 'Queued. Thank you.';
            }
        };

        $messageApi = $this->createMock(MessageApi::class);
        $messageApi->expects($this->once())
            ->method('send')
            ->with(
                'mg.example.com',
                $this->callback(function ($params) {
                    return isset($params['from'])
                        && isset($params['to'])
                        && isset($params['subject'])
                        && $params['to'] === 'test@example.com';
                })
            )
            ->willReturn($response);

        $mailgun = $this->createMock(Mailgun::class);
        $mailgun->method('messages')->willReturn($messageApi);

        $sender = new MailgunEmailSender(
            client: $mailgun,
            domain: 'mg.example.com',
            fromEmail: 'noreply@example.com',
            fromName: 'Test Sender',
        );

        $payload = new FlightPlan(
            channel: 'email',
            to: 'test@example.com',
            subject: 'Test Subject',
            text: 'Test body',
        );

        $result = $sender->send($payload);

        $this->assertEquals('sent', $result->status);
        $this->assertEquals('<20250101010101.1.ABC123@example.com>', $result->providerMessageId);
        $this->assertIsArray($result->raw);
    }

    /**
     * Returns undeliverable when subject is missing for non-template emails.
     *
     * @return void
     */
    public function testSendWithoutSubjectReturnsUndeliverable(): void
    {
        $mailgun = $this->createMock(Mailgun::class);

        $sender = new MailgunEmailSender(
            client: $mailgun,
            domain: 'mg.example.com',
            fromEmail: 'noreply@example.com',
            fromName: 'Test Sender',
        );

        $payload = new FlightPlan(
            channel: 'email',
            to: 'test@example.com',
            text: 'Body without subject',
        );

        $result = $sender->send($payload);

        $this->assertEquals('undeliverable', $result->status);
        $this->assertEquals('MISSING_SUBJECT', $result->errorCode);
    }

    /**
     * Returns a successful result when using templates.
     *
     * @return void
     */
    public function testSendWithTemplateSuccess(): void
    {
        $response = new class {
            public function getId(): string
            {
                return '<20250101020202.2.XYZ789@example.com>';
            }

            public function getMessage(): string
            {
                return 'Queued. Thank you.';
            }
        };

        $messageApi = $this->createMock(MessageApi::class);
        $messageApi->expects($this->once())
            ->method('send')
            ->with(
                'mg.example.com',
                $this->callback(function ($params) {
                    return isset($params['template'])
                        && $params['template'] === 'welcome-email'
                        && isset($params['v:username'])
                        && $params['v:username'] === 'JohnDoe';
                })
            )
            ->willReturn($response);

        $mailgun = $this->createMock(Mailgun::class);
        $mailgun->method('messages')->willReturn($messageApi);

        $sender = new MailgunEmailSender(
            client: $mailgun,
            domain: 'mg.example.com',
            fromEmail: 'noreply@example.com',
            fromName: 'Test Sender',
            templates: ['welcome' => 'welcome-email'],
        );

        $payload = new FlightPlan(
            channel: 'email',
            to: 'test@example.com',
            templateKey: 'welcome',
            templateData: ['username' => 'JohnDoe'],
        );

        $result = $sender->send($payload);

        $this->assertEquals('sent', $result->status);
        $this->assertEquals('<20250101020202.2.XYZ789@example.com>', $result->providerMessageId);
    }

    /**
     * Returns undeliverable for attachment validation errors.
     *
     * @return void
     */
    public function testSendWithInvalidAttachmentReturnsUndeliverable(): void
    {
        $mailgun = $this->createMock(Mailgun::class);

        $sender = new MailgunEmailSender(
            client: $mailgun,
            domain: 'mg.example.com',
            fromEmail: 'noreply@example.com',
            fromName: 'Test Sender',
        );

        $payload = new FlightPlan(
            channel: 'email',
            to: 'test@example.com',
            subject: 'Test',
            text: 'Body',
            metadata: [
                'attachments' => [
                    [
                        'filename' => 'test.txt',
                        'content' => 'not-valid-base64!!!',
                    ],
                ],
            ],
        );

        $result = $sender->send($payload);

        $this->assertEquals('undeliverable', $result->status);
        $this->assertEquals('ATTACHMENT_INVALID_BASE64', $result->errorCode);
    }

    /**
     * Sends inline attachments through Mailgun's inline parameter.
     *
     * @return void
     */
    public function testSendWithInlineAttachment(): void
    {
        $response = new class {
            public function getId(): string
            {
                return '<inline@example.com>';
            }

            public function getMessage(): string
            {
                return 'Queued. Thank you.';
            }
        };

        $messageApi = $this->createMock(MessageApi::class);
        $messageApi->expects($this->once())
            ->method('send')
            ->with(
                'mg.example.com',
                $this->callback(function ($params) {
                    return isset($params['inline'])
                        && !isset($params['attachment'])
                        && $params['inline'][0]['filename'] === 'logo.png'
                        && $params['inline'][0]['fileContent'] === 'image-bytes';
                })
            )
            ->willReturn($response);

        $mailgun = $this->createMock(Mailgun::class);
        $mailgun->method('messages')->willReturn($messageApi);

        $sender = new MailgunEmailSender(
            client: $mailgun,
            domain: 'mg.example.com',
            fromEmail: 'noreply@example.com',
            fromName: 'Test Sender',
        );

        $payload = new FlightPlan(
            channel: 'email',
            to: 'test@example.com',
            subject: 'Test',
            html: '<img src="cid:logo.png">',
            metadata: [
                'attachments' => [
                    [
                        'filename' => 'original-logo.png',
                        'content' => base64_encode('image-bytes'),
                        'type' => 'image/png',
                        'disposition' => 'inline',
                        'content_id' => 'logo.png',
                    ],
                ],
            ],
        );

        $result = $sender->send($payload);

        $this->assertEquals('sent', $result->status);
    }

    /**
     * Sends normal and inline attachments in separate Mailgun parameters.
     *
     * @return void
     */
    public function testSendWithMixedAttachments(): void
    {
        $response = new class {
            public function getId(): string
            {
                return '<mixed@example.com>';
            }

            public function getMessage(): string
            {
                return 'Queued. Thank you.';
            }
        };

        $messageApi = $this->createMock(MessageApi::class);
        $messageApi->expects($this->once())
            ->method('send')
            ->with(
                'mg.example.com',
                $this->callback(function ($params) {
                    return isset($params['attachment'], $params['inline'])
                        && $params['attachment'][0]['filename'] === 'report.txt'
                        && $params['attachment'][0]['fileContent'] === 'report'
                        && $params['inline'][0]['filename'] === 'logo.png'
                        && $params['inline'][0]['fileContent'] === 'image-bytes';
                })
            )
            ->willReturn($response);

        $mailgun = $this->createMock(Mailgun::class);
        $mailgun->method('messages')->willReturn($messageApi);

        $sender = new MailgunEmailSender(
            client: $mailgun,
            domain: 'mg.example.com',
            fromEmail: 'noreply@example.com',
            fromName: 'Test Sender',
        );

        $payload = new FlightPlan(
            channel: 'email',
            to: 'test@example.com',
            subject: 'Test',
            text: 'Body',
            metadata: [
                'attachments' => [
                    [
                        'filename' => 'report.txt',
                        'content' => base64_encode('report'),
                    ],
                    [
                        'filename' => 'original-logo.png',
                        'content' => base64_encode('image-bytes'),
                        'disposition' => 'inline',
                        'content_id' => 'logo.png',
                    ],
                ],
            ],
        );

        $result = $sender->send($payload);

        $this->assertEquals('sent', $result->status);
    }

    /**
     * Returns undeliverable for inline attachments without content ID.
     *
     * @return void
     */
    public function testSendInlineAttachmentWithoutContentIdReturnsUndeliverable(): void
    {
        $mailgun = $this->createMock(Mailgun::class);
        $mailgun->expects($this->never())->method('messages');

        $sender = new MailgunEmailSender(
            client: $mailgun,
            domain: 'mg.example.com',
            fromEmail: 'noreply@example.com',
            fromName: 'Test Sender',
        );

        $payload = new FlightPlan(
            channel: 'email',
            to: 'test@example.com',
            subject: 'Test',
            text: 'Body',
            metadata: [
                'attachments' => [
                    [
                        'filename' => 'logo.png',
                        'content' => base64_encode('image-bytes'),
                        'disposition' => 'inline',
                    ],
                ],
            ],
        );

        $result = $sender->send($payload);

        $this->assertEquals('undeliverable', $result->status);
        $this->assertEquals('ATTACHMENT_MISSING_CONTENT_ID', $result->errorCode);
    }

    /**
     * Returns failed result when API throws exception.
     *
     * @return void
     */
    public function testSendExceptionReturnsFailed(): void
    {
        $messageApi = $this->createMock(MessageApi::class);
        $messageApi->expects($this->once())
            ->method('send')
            ->willThrowException(new Exception('API error', 503));

        $mailgun = $this->createMock(Mailgun::class);
        $mailgun->method('messages')->willReturn($messageApi);

        $sender = new MailgunEmailSender(
            client: $mailgun,
            domain: 'mg.example.com',
            fromEmail: 'noreply@example.com',
            fromName: 'Test Sender',
        );

        $payload = new FlightPlan(
            channel: 'email',
            to: 'test@example.com',
            subject: 'Test',
            text: 'Body',
        );

        $result = $sender->send($payload);

        $this->assertEquals('failed', $result->status);
        $this->assertEquals('503', $result->errorCode);
        $this->assertStringContainsString('API error', $result->errorMessage);
    }

    /**
     * Returns undeliverable for permanent client errors.
     *
     * @return void
     */
    public function testSendWithPermanentErrorReturnsUndeliverable(): void
    {
        $messageApi = $this->createMock(MessageApi::class);
        $messageApi->expects($this->once())
            ->method('send')
            ->willThrowException(new Exception('Invalid recipient', 400));

        $mailgun = $this->createMock(Mailgun::class);
        $mailgun->method('messages')->willReturn($messageApi);

        $sender = new MailgunEmailSender(
            client: $mailgun,
            domain: 'mg.example.com',
            fromEmail: 'noreply@example.com',
            fromName: 'Test Sender',
        );

        $payload = new FlightPlan(
            channel: 'email',
            to: 'invalid@example.com',
            subject: 'Test',
            text: 'Body',
        );

        $result = $sender->send($payload);

        $this->assertEquals('undeliverable', $result->status);
        $this->assertEquals('400', $result->errorCode);
    }
}
