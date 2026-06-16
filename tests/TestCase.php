<?php

/**
 * Base test case for Bird Flock unit tests.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests;

use Equidna\BirdFlock\Tests\Support\ResponseFactoryFake;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as EventsContract;
use Illuminate\Contracts\Routing\ResponseFactory as ResponseFactoryContract;
use Illuminate\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $container = Container::getInstance() ?? new Container();
        Container::setInstance($container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);

        $repository = new Repository([
            'bird-flock' => [
                'database' => [
                    'connection' => null,
                ],
                'default_queue' => 'default',
                'tables' => [
                    'prefix' => 'bird_flock_',
                    'outbound_messages' => 'bird_flock_outbound_messages',
                ],
                'channels' => [
                    'sms' => [
                        'strategy' => 'round_robin',
                        'retry' => [
                            'max_attempts' => 3,
                            'base_delay_ms' => 1000,
                            'max_delay_ms' => 60000,
                        ],
                        'senders' => [
                            'twilio' => \Equidna\BirdFlock\Senders\Twilio\TwilioSmsSenderDefinition::class,
                        ],
                    ],
                    'whatsapp' => [
                        'strategy' => 'round_robin',
                        'retry' => [
                            'max_attempts' => 3,
                            'base_delay_ms' => 1000,
                            'max_delay_ms' => 60000,
                        ],
                        'senders' => [
                            'twilio' => \Equidna\BirdFlock\Senders\Twilio\TwilioWhatsappSenderDefinition::class,
                        ],
                    ],
                    'email' => [
                        'strategy' => 'round_robin',
                        'retry' => [
                            'max_attempts' => 3,
                            'base_delay_ms' => 1000,
                            'max_delay_ms' => 60000,
                        ],
                        'senders' => [
                            'mailgun' => \Equidna\BirdFlock\Senders\Mailgun\MailgunEmailSenderDefinition::class,
                        ],
                    ],
                ],
            ],
            'bird-flock-twilio' => [
                'auth_token' => null,
            ],
            'bird-flock-sendgrid' => [
                    'require_signed_webhooks' => false,
                    'webhook_public_key' => null,
            ],
            'bird-flock-mailgun' => [
                'templates' => [],
            ],
            'bird-flock-labsmobile' => [
                'username' => null,
                'token' => null,
                'from_sms' => null,
                'ack_url' => null,
                'webhook_token' => null,
                'test' => false,
                'long' => false,
                'ucs2' => false,
                'shortlink' => false,
                'endpoint' => 'https://api.labsmobile.com/json/send',
                'timeout' => 30,
                'connect_timeout' => 10,
            ],
        ]);

        $container->instance('config', $repository);
        $cache = new class (new ArrayStore()) extends CacheRepository {
            public function refreshEventDispatcher(): void
            {
                //
            }
        };
        $container->instance('cache', $cache);
        $container->instance('cache.store', $cache);

        $responseFactory = new ResponseFactoryFake();
        $container->instance(ResponseFactoryContract::class, $responseFactory);
        $container->instance('Illuminate\Contracts\Routing\ResponseFactory', $responseFactory);

        $events = new EventsDispatcher($container);
        $container->instance('events', $events);
        $container->instance(EventsContract::class, $events);
        Event::swap($events);
    }

    protected function setConfigValue(string $key, mixed $value): void
    {
        app('config')->set($key, $value);
    }
}
