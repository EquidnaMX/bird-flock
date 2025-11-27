<?php

namespace Equidna\BirdFlock\Tests\Messaging\Senders;

use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Senders\TwilioSmsSender;
use Equidna\BirdFlock\Tests\Support\TwilioMessageListFake;
use Exception;
use PHPUnit\Framework\TestCase;
use Twilio\Rest\Client;

class TwilioSmsSenderTest extends TestCase
{
    public function testSendSuccessWithMessagingService(): void
    {
        $messageInstance = (object) [
            'sid' => 'SM123456',
            'status' => 'queued',
            'to' => '+15005550006',
            'from' => '+15005550001',
        ];

        $messagesResource = $this->getMockBuilder(TwilioMessageListFake::class)
            ->onlyMethods(['create'])
            ->getMock();
        $messagesResource->expects($this->once())
            ->method('create')
            ->with(
                '+15005550006',
                $this->callback(function ($params) {
                    return $params['body'] === 'Hello'
                        && $params['messagingServiceSid'] === 'MG123';
                })
            )
            ->willReturn($messageInstance);

        $client = $this->createMock(Client::class);
        $client->messages = $messagesResource;

        $sender = new TwilioSmsSender(
            client: $client,
            from: '+15005550001',
            messagingServiceSid: 'MG123',
        );

        $payload = new FlightPlan(
            channel: 'sms',
            to: '+15005550006',
            text: 'Hello',
        );

        $result = $sender->send($payload);

        $this->assertEquals('SM123456', $result->providerMessageId);
        $this->assertEquals('sent', $result->status);
    }

    public function testSendSuccessWithFromNumber(): void
    {
        $messageInstance = (object) [
            'sid' => 'SM789',
            'status' => 'queued',
            'to' => '+15005550006',
            'from' => '+15005550001',
        ];

        $messagesResource = $this->getMockBuilder(TwilioMessageListFake::class)
            ->onlyMethods(['create'])
            ->getMock();
        $messagesResource->expects($this->once())
            ->method('create')
            ->with(
                '+15005550006',
                $this->callback(function ($params) {
                    return $params['body'] === 'Test'
                        && $params['from'] === '+15005550001'
                        && !isset($params['messagingServiceSid']);
                })
            )
            ->willReturn($messageInstance);

        $client = $this->createMock(Client::class);
        $client->messages = $messagesResource;

        $sender = new TwilioSmsSender(
            client: $client,
            from: '+15005550001',
        );

        $payload = new FlightPlan(
            channel: 'sms',
            to: '+15005550006',
            text: 'Test',
        );

        $result = $sender->send($payload);

        $this->assertEquals('SM789', $result->providerMessageId);
        $this->assertEquals('sent', $result->status);
    }

    public function testSendFailureReturnsFailedResult(): void
    {
        $exception = new Exception('API Error');

        $messagesResource = $this->getMockBuilder(TwilioMessageListFake::class)
            ->onlyMethods(['create'])
            ->getMock();
        $messagesResource->expects($this->once())
            ->method('create')
            ->willThrowException($exception);

        $client = $this->createMock(Client::class);
        $client->messages = $messagesResource;

        $sender = new TwilioSmsSender(
            client: $client,
            from: '+15005550001',
        );

        $payload = new FlightPlan(
            channel: 'sms',
            to: '+15005550006',
            text: 'Test',
        );

        $result = $sender->send($payload);

        $this->assertEquals('undeliverable', $result->status);
        $this->assertEquals('0', $result->errorCode);
    }
}
