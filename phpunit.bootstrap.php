<?php

$loader = require __DIR__ . '/vendor/autoload.php';

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

// Test support classes are autoloaded via Composer `autoload-dev` PSR-4 mapping.
// See `composer.json` -> `autoload-dev` for `Equidna\\BirdFlock\\Tests\\`.

use Equidna\BirdFlock\Tests\Support\DispatcherFake;
use Equidna\BirdFlock\Tests\Support\ResponseFactoryFake;
// Using installed Illuminate\Config\Repository directly (real package installed).

// Minimal Illuminate\Http\Request shim for tests that use Request::create(),
// header(), and all().
// Request provided by installed Illuminate\Http package.

// Rely on real Symfony UID and Illuminate Routing classes installed via Composer.

use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Events\Dispatcher as EventsContract;
use Illuminate\Contracts\Routing\ResponseFactory as ResponseFactoryContract;
use Illuminate\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

// `laravel/framework` is installed; the real
// `Illuminate\Foundation\Bus\Dispatchable` trait is available via Composer.

$container = Container::getInstance() ?? new Container();
Container::setInstance($container);
Facade::setFacadeApplication($container);

if (! function_exists('app')) {
    function app($abstract = null, array $parameters = [])
    {
        $c = \Illuminate\Container\Container::getInstance();
        if ($abstract === null) {
            return $c;
        }
        return $c->make($abstract, $parameters);
    }
}

if (! function_exists('config')) {
    function config($key = null, $default = null)
    {
        $cfg = app('config');
        if ($key === null) {
            return $cfg;
        }
        if (method_exists($cfg, 'get')) {
            return $cfg->get($key, $default);
        }
        return $default;
    }
}

if (! function_exists('now')) {
    function now()
    {
        return new \DateTimeImmutable();
    }
}

$configArray = [
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
];

// Use the real Illuminate Config repository (installed via Composer).
$config = new \Illuminate\Config\Repository($configArray);

$container->instance('config', $config);

$responseFactory = new ResponseFactoryFake();
$container->instance(ResponseFactoryContract::class, $responseFactory);
$container->instance('Illuminate\Contracts\Routing\ResponseFactory', $responseFactory);

$dispatcher = new DispatcherFake();
$container->instance(DispatcherContract::class, $dispatcher);
$container->instance('Illuminate\Contracts\Bus\Dispatcher', $dispatcher);

$logger = new \Psr\Log\NullLogger();
$container->instance(LoggerInterface::class, $logger);
$container->instance('log', $logger);
$container->instance('bird-flock.logger', $logger);

$events = new EventsDispatcher($container);
$container->instance('events', $events);
$container->instance(EventsContract::class, $events);
Event::setFacadeApplication($container);

// Bind a simple in-memory cache for testing (ArrayStore).
$cache = new \Illuminate\Cache\ArrayStore();
$container->instance('cache', $cache);
$container->instance('cache.store', $cache);

// Ensure a simple `response()` helper exists for tests when not provided by
// framework helpers. Use the real Illuminate Response when available.
if (! function_exists('response')) {
    function response($content = '', int $status = 200, array $headers = [])
    {
        return new \Illuminate\Http\Response($content, $status, $headers);
    }
}
