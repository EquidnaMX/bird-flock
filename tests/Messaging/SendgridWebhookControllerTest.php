<?php

namespace Equidna\BirdFlock\Tests\Messaging;

use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Equidna\BirdFlock\Events\WebhookReceived;
use Equidna\BirdFlock\Http\Controllers\SendgridWebhookController;
use Equidna\BirdFlock\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

class SendgridWebhookControllerTest extends TestCase
{
    public function testEventsWebhookProcessesDeliveredEvent(): void
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
                'msg123',
                'delivered',
                $this->callback(function ($meta) {
                    return $meta['provider_message_id'] === 'msg123';
                })
            );

        $events = [
            [
                'event' => 'delivered',
                'sg_message_id' => 'msg123',
                'email' => 'test@example.com',
                'timestamp' => 1234567890,
            ],
        ];

        $request = $this->makeJsonRequest($events);

        $controller = new SendgridWebhookController($repository);
        $response = $controller->events($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $webhooks);
        $this->assertEquals('sendgrid', $webhooks[0]->provider);
        $dispatcher->forget(WebhookReceived::class);
    }

    public function testEventsWebhookProcessesBounceEvent(): void
    {
        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('updateStatus')
            ->with(
                'msg456',
                'failed',
                $this->callback(function ($meta) {
                    return $meta['provider_message_id'] === 'msg456'
                        && isset($meta['error_code']);
                })
            );

        $events = [
            [
                'event' => 'bounce',
                'sg_message_id' => 'msg456',
                'email' => 'bounce@example.com',
                'reason' => 'Mailbox not found',
                'type' => 'hard',
                'timestamp' => 1234567890,
            ],
        ];

        $request = $this->makeJsonRequest($events);

        $controller = new SendgridWebhookController($repository);
        $response = $controller->events($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testEventsWebhookHandlesMultipleEvents(): void
    {
        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->exactly(2))
            ->method('updateStatus');

        $events = [
            [
                'event' => 'delivered',
                'sg_message_id' => 'msg1',
                'email' => 'test1@example.com',
            ],
            [
                'event' => 'bounce',
                'sg_message_id' => 'msg2',
                'email' => 'test2@example.com',
                'reason' => 'Invalid',
            ],
        ];

        $request = $this->makeJsonRequest($events);

        $controller = new SendgridWebhookController($repository);
        $response = $controller->events($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testEventsWebhookSkipsMalformedEvents(): void
    {
        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->never())
            ->method('updateStatus');

        $events = [
            [
                'event' => 'delivered',
            ],
        ];

        $request = $this->makeJsonRequest($events);

        $controller = new SendgridWebhookController($repository);
        $response = $controller->events($request);

        $this->assertEquals(200, $response->getStatusCode());
    }
    public function testEventsWebhookThrowsWhenSignedWebhooksEnabledWithoutKey(): void
    {
        $this->setConfigValue('bird-flock.sendgrid.require_signed_webhooks', true);
        $this->setConfigValue('bird-flock.sendgrid.webhook_public_key', null);

        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $controller = new SendgridWebhookController($repository);
        $request = Request::create('/webhooks/sendgrid/events', 'POST');

        $this->expectException(\RuntimeException::class);
        $controller->events($request);
    }

    public function testEventsWebhookAllowsSignedWebhooksWithKeyConfigured(): void
    {
        $this->setConfigValue('bird-flock.sendgrid.require_signed_webhooks', true);
        $this->setConfigValue('bird-flock.sendgrid.webhook_public_key', 'test-key');

        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->never())->method('updateStatus');

        $events = [['event' => 'processed', 'sg_message_id' => 'msg']];
        $request = $this->makeJsonRequest($events);

        $controller = new SendgridWebhookController($repository);
        // We expect authorization to fail because signature is missing.
        $response = $controller->events($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * Build a JSON webhook request for test data.
     *
     * @param array<int,array<string,mixed>> $events
     */
    private function makeJsonRequest(array $events): Request
    {
        $request = Request::create(
            '/webhooks/sendgrid/events',
            'POST',
            [],
            [],
            [],
            [],
            json_encode($events, JSON_THROW_ON_ERROR)
        );
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }
}







