<?php

namespace Equidna\BirdFlock\Tests\Messaging\Senders;

use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Senders\TwilioWhatsappSender;
use Equidna\BirdFlock\Tests\Support\TwilioMessageListFake;
use Equidna\BirdFlock\Tests\TestCase;
use Twilio\Rest\Client;

class TwilioWhatsappSenderTest extends TestCase
{
    public function testSendSuccessWithMediaUrls(): void
    {
        $messageInstance = (object) [
            'sid' => 'SM123456',
            'status' => 'queued',
            'to' => 'whatsapp:+15005550006',
            'from' => 'whatsapp:+15005550001',
        ];

        $messagesResource = $this->getMockBuilder(TwilioMessageListFake::class)
            ->onlyMethods(['create'])
            ->getMock();
        $messagesResource->expects($this->once())
            ->method('create')
            ->with(
                'whatsapp:+15005550006',
                $this->callback(function ($params) {
                    return $params['body'] === 'Hello'
                        && $params['from'] === 'whatsapp:+15005550001'
                        && $params['mediaUrl'] === ['https://example.com/image.jpg'];
                })
            )
            ->willReturn($messageInstance);

        $client = $this->createMock(Client::class);
        $client->messages = $messagesResource;

        $this->setConfigValue('bird-flock.twilio.sandbox_mode', true);
        $this->setConfigValue('bird-flock.twilio.sandbox_from', null);

        $sender = new TwilioWhatsappSender(
            client: $client,
            from: 'whatsapp:+15005550001',
        );

        $payload = new FlightPlan(
            channel: 'whatsapp',
            to: '+15005550006',
            text: 'Hello',
            mediaUrls: ['https://example.com/image.jpg'],
        );

        $result = $sender->send($payload);

        $this->assertEquals('SM123456', $result->providerMessageId);
        $this->assertEquals('sent', $result->status);
    }

    public function testSendFailsWithoutTemplateInProduction(): void
    {
        $client = $this->createMock(Client::class);

        $this->setConfigValue('bird-flock.twilio.sandbox_mode', false);

        $sender = new TwilioWhatsappSender(
            client: $client,
            from: 'whatsapp:+15005550001',
        );

        $payload = new FlightPlan(
            channel: 'whatsapp',
            to: '+15005550006',
            text: 'Hello',
        );

        $result = $sender->send($payload);

        $this->assertEquals('undeliverable', $result->status);
        $this->assertEquals('TEMPLATE_REQUIRED', $result->errorCode);
    }

    public function testNormalizesRecipientToWhatsAppFormat(): void
    {
        $messageInstance = (object) [
            'sid' => 'SM789',
            'status' => 'queued',
            'to' => 'whatsapp:+15005550006',
            'from' => 'whatsapp:+15005550001',
        ];

        $messagesResource = $this->getMockBuilder(TwilioMessageListFake::class)
            ->onlyMethods(['create'])
            ->getMock();
        $messagesResource->expects($this->once())
            ->method('create')
            ->with(
                'whatsapp:+15005550006',
                $this->anything()
            )
            ->willReturn($messageInstance);

        $client = $this->createMock(Client::class);
        $client->messages = $messagesResource;

        $this->setConfigValue('bird-flock.twilio.sandbox_mode', true);
        $this->setConfigValue('bird-flock.twilio.sandbox_from', null);

        $sender = new TwilioWhatsappSender(
            client: $client,
            from: 'whatsapp:+15005550001',
        );

        $payload = new FlightPlan(
            channel: 'whatsapp',
            to: '15005550006',
            text: 'Test',
        );

        $result = $sender->send($payload);

        $this->assertEquals('sent', $result->status);
    }
}
