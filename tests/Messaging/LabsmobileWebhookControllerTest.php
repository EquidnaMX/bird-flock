<?php

namespace Equidna\BirdFlock\Tests\Messaging;

use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Equidna\BirdFlock\Events\WebhookReceived;
use Equidna\BirdFlock\Http\Controllers\LabsmobileWebhookController;
use Equidna\BirdFlock\Tests\TestCase;
use Illuminate\Http\Request;

final class LabsmobileWebhookControllerTest extends TestCase
{
    public function testAckWebhookUpdatesMessageStatus(): void
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
                '65f33a88ceb3d',
                'delivered',
                $this->callback(function ($meta) {
                    return $meta['provider_message_id'] === '65f33a88ceb3d'
                        && $meta['acklevel'] === 'handset'
                        && $meta['provider_status'] === 'ok';
                })
            );

        $request = Request::create('/bird-flock/webhooks/labsmobile/ack', 'GET', [
            'acklevel' => 'handset',
            'msisdn' => '12015550123',
            'status' => 'ok',
            'desc' => '',
            'subid' => '65f33a88ceb3d',
            'timestamp' => '2026-06-15 16:30:00',
        ]);

        $controller = new LabsmobileWebhookController($repository);
        $response = $controller->ack($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $webhooks);
        $this->assertSame('labsmobile', $webhooks[0]->provider);
        $this->assertSame('ack', $webhooks[0]->type);
        $dispatcher->forget(WebhookReceived::class);
    }

    public function testAckWebhookRejectsMissingFields(): void
    {
        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->never())->method('updateStatus');

        $request = Request::create('/bird-flock/webhooks/labsmobile/ack', 'GET', [
            'subid' => '65f33a88ceb3d',
        ]);

        $controller = new LabsmobileWebhookController($repository);
        $response = $controller->ack($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testAckWebhookRejectsInvalidTokenWhenConfigured(): void
    {
        $this->setConfigValue('bird-flock-labsmobile.webhook_token', 'expected');

        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->never())->method('updateStatus');

        $request = Request::create('/bird-flock/webhooks/labsmobile/ack', 'GET', [
            'acklevel' => 'handset',
            'msisdn' => '12015550123',
            'status' => 'ok',
            'subid' => '65f33a88ceb3d',
            'token' => 'wrong',
        ]);

        $controller = new LabsmobileWebhookController($repository);
        $response = $controller->ack($request);

        $this->assertSame(401, $response->getStatusCode());
    }
}
