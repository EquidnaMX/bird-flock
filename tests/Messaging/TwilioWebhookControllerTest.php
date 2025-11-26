<?php

namespace Equidna\BirdFlock\Tests\Messaging;

use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Equidna\BirdFlock\Events\WebhookReceived;
use Equidna\BirdFlock\Http\Controllers\TwilioWebhookController;
use Equidna\BirdFlock\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

class TwilioWebhookControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setConfigValue('bird-flock.twilio.auth_token', 'test_token');
    }

    public function testStatusWebhookUpdatesMessageStatus(): void
    {
        $dispatcher = app('events');
        $webhooks = [];
        $dispatcher->listen(WebhookReceived::class, function ($event) use (&$webhooks) {
            $webhooks[] = $event;
        });

        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('updateStatus')
            ->with(
                'SM123',
                'delivered',
                $this->callback(function ($meta) {
                    return $meta['provider_message_id'] === 'SM123';
                })
            );

        $url = 'https://example.com/webhooks/twilio/status';
        $params = [
            'MessageSid' => 'SM123',
            'MessageStatus' => 'delivered',
        ];

        $authToken = 'test_token';
        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key . $value;
        }
        $signature = base64_encode(hash_hmac('sha1', $data, $authToken, true));

        $request = Request::create($url, 'POST', $params);
        $request->headers->set('X-Twilio-Signature', $signature);

        $controller = new TwilioWebhookController($repository);
        $response = $controller->status($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $webhooks);
        $this->assertEquals('status', $webhooks[0]->type);
        $dispatcher->forget(WebhookReceived::class);
    }

    public function testStatusWebhookRejectsInvalidSignature(): void
    {
        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->never())
            ->method('updateStatus');

        $url = 'https://example.com/webhooks/twilio/status';
        $request = Request::create($url, 'POST', ['MessageSid' => 'SM123']);
        $request->headers->set('X-Twilio-Signature', 'invalid');

        $controller = new TwilioWebhookController($repository);
        $response = $controller->status($request);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testInboundWebhookAcceptsValidSignature(): void
    {
        $dispatcher = app('events');
        $webhooks = [];
        $dispatcher->listen(WebhookReceived::class, function ($event) use (&$webhooks) {
            $webhooks[] = $event;
        });

        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);

        $url = 'https://example.com/webhooks/twilio/inbound';
        $params = [
            'From' => '+15005550006',
            'To' => '+15005550001',
            'Body' => 'Test message',
        ];

        $authToken = 'test_token';
        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key . $value;
        }
        $signature = base64_encode(hash_hmac('sha1', $data, $authToken, true));

        $request = Request::create($url, 'POST', $params);
        $request->headers->set('X-Twilio-Signature', $signature);

        $controller = new TwilioWebhookController($repository);
        $response = $controller->inbound($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($webhooks);
        $this->assertEquals('inbound', $webhooks[0]->type);
        $dispatcher->forget(WebhookReceived::class);
    }

    public function testStatusWebhookFailsWhenAuthTokenMissing(): void
    {
        $this->setConfigValue('bird-flock.twilio.auth_token', null);

        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $url = 'https://example.com/webhooks/twilio/status';
        $request = Request::create($url, 'POST', ['MessageSid' => 'SM123']);

        $controller = new TwilioWebhookController($repository);
        $response = $controller->status($request);

        $this->assertEquals(500, $response->getStatusCode());
    }
}





