<?php

namespace Equidna\BirdFlock\Tests\Messaging\Senders;

use DateTimeImmutable;
use DateTimeZone;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Senders\Labsmobile\LabsmobileSmsSender;
use Equidna\BirdFlock\Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

final class LabsmobileSmsSenderTest extends TestCase
{
    public function testSendSuccessReturnsSuccessResult(): void
    {
        $history = [];
        $sender = $this->senderWithResponses([
            new Response(200, [], json_encode([
                'code' => 0,
                'message' => 'Message has been successfully sent.',
                'subid' => '65f33a88ceb3d',
            ])),
        ], $history);

        $result = $sender->send(new FlightPlan(
            channel: 'sms',
            to: '+12015550123',
            text: 'Your verification code is 123'
        ));

        $this->assertSame('sent', $result->status);
        $this->assertSame('65f33a88ceb3d', $result->providerMessageId);

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('Your verification code is 123', $body['message']);
        $this->assertSame('12015550123', $body['recipient'][0]['msisdn']);
        $this->assertSame('Sender', $body['tpoa']);
        $this->assertSame('https://example.com/bird-flock/webhooks/labsmobile/ack', $body['ackurl']);
    }

    public function testProviderValidationErrorReturnsUndeliverable(): void
    {
        $history = [];
        $sender = $this->senderWithResponses([
            new Response(400, [], json_encode([
                'code' => 35,
                'message' => 'The account has no enough credit for this sending',
                'subid' => '65f7f7041385d',
            ])),
        ], $history);

        $result = $sender->send(new FlightPlan(
            channel: 'sms',
            to: '+12015550123',
            text: 'Test'
        ));

        $this->assertSame('undeliverable', $result->status);
        $this->assertSame('35', $result->errorCode);
        $this->assertSame('65f7f7041385d', $result->raw['subid']);
    }

    public function testTransientExceptionReturnsFailed(): void
    {
        $history = [];
        $request = new Request('POST', 'https://api.labsmobile.com/json/send');
        $sender = $this->senderWithResponses([
            new ConnectException('Connection timed out', $request),
        ], $history);

        $result = $sender->send(new FlightPlan(
            channel: 'sms',
            to: '+12015550123',
            text: 'Test'
        ));

        $this->assertSame('failed', $result->status);
        $this->assertSame('LABSMOBILE_ERROR', $result->errorCode);
    }

    public function testSendAtAppliesScheduledUtcField(): void
    {
        $history = [];
        $sender = $this->senderWithResponses([
            new Response(200, [], json_encode([
                'code' => 0,
                'message' => 'Message has been successfully sent.',
                'subid' => 'scheduled-1',
            ])),
        ], $history);

        $sender->send(new FlightPlan(
            channel: 'sms',
            to: '+12015550123',
            text: 'Scheduled',
            sendAt: new DateTimeImmutable('2026-06-15 10:30:00', new DateTimeZone('America/Mexico_City'))
        ));

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('2026-06-15 16:30:00', $body['scheduled']);
    }

    /**
     * @param array<int, mixed> $responses
     * @param array<int, array<string, mixed>> $history
     */
    private function senderWithResponses(array $responses, array &$history): LabsmobileSmsSender
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        return new LabsmobileSmsSender(
            client: new Client(['handler' => $stack]),
            endpoint: 'https://api.labsmobile.com/json/send',
            from: 'Sender',
            ackUrl: 'https://example.com/bird-flock/webhooks/labsmobile/ack',
            test: true,
            long: true,
            ucs2: false,
            shortlink: false,
        );
    }
}
