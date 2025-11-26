<?php

$loader = require __DIR__ . '/../../../vendor/autoload.php';

spl_autoload_register(function ($class) {
    $prefix = 'Equidna\\BirdFlock\\';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
}, true, true);

require_once __DIR__ . '/tests/Support/FakeResponseFactory.php';
require_once __DIR__ . '/tests/Support/FakeDispatcher.php';
require_once __DIR__ . '/tests/Support/FakeTwilioMessageList.php';
require_once __DIR__ . '/tests/TestCase.php';

use Equidna\BirdFlock\Tests\Support\FakeDispatcher;
use Equidna\BirdFlock\Tests\Support\FakeResponseFactory;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Events\Dispatcher as EventsContract;
use Illuminate\Contracts\Routing\ResponseFactory as ResponseFactoryContract;
use Illuminate\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

$container = Container::getInstance() ?? new Container();
Container::setInstance($container);
Facade::setFacadeApplication($container);

$config = new Repository([
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

$container->instance('config', $config);

$responseFactory = new FakeResponseFactory();
$container->instance(ResponseFactoryContract::class, $responseFactory);
$container->instance('Illuminate\Contracts\Routing\ResponseFactory', $responseFactory);

$dispatcher = new FakeDispatcher();
$container->instance(DispatcherContract::class, $dispatcher);
$container->instance('Illuminate\Contracts\Bus\Dispatcher', $dispatcher);

$logger = new NullLogger();
$container->instance(LoggerInterface::class, $logger);
$container->instance('log', $logger);
$container->instance('bird-flock.logger', $logger);

$events = new EventsDispatcher($container);
$container->instance('events', $events);
$container->instance(EventsContract::class, $events);
Event::setFacadeApplication($container);
