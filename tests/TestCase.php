<?php

namespace Equidna\BirdFlock\Tests;

use Equidna\BirdFlock\Tests\Support\FakeResponseFactory;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as EventsContract;
use Illuminate\Contracts\Routing\ResponseFactory as ResponseFactoryContract;
use Illuminate\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $container = Container::getInstance() ?? new Container();
        Container::setInstance($container);

        $repository = new Repository([
            'bird-flock' => [
                'default_queue' => 'default',
                'tables' => [
                    'prefix' => 'bird_flock_',
                    'outbound_messages' => 'bird_flock_outbound_messages',
                ],
                'twilio' => [
                    'auth_token' => null,
                ],
                'sendgrid' => [
                    'require_signed_webhooks' => false,
                    'webhook_public_key' => null,
                ],
            ],
        ]);

        $container->instance('config', $repository);

        $responseFactory = new FakeResponseFactory();
        $container->instance(ResponseFactoryContract::class, $responseFactory);
        $container->instance('Illuminate\Contracts\Routing\ResponseFactory', $responseFactory);

        $events = new EventsDispatcher($container);
        $container->instance('events', $events);
        $container->instance(EventsContract::class, $events);
        Event::setFacadeApplication($container);
        Event::swap($events);
    }

    protected function setConfigValue(string $key, mixed $value): void
    {
        app('config')->set($key, $value);
    }
}
