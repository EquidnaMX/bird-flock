<?php

namespace Equidna\BirdFlock\Tests\Unit\Support;

use Equidna\BirdFlock\Support\SenderResolver;
use Equidna\BirdFlock\Tests\Support\AutowiredSender;
use Equidna\BirdFlock\Tests\Support\ConfigurableSenderDefinition;
use Equidna\BirdFlock\Tests\Support\ConfigurableSender;
use Equidna\BirdFlock\Tests\Support\FakeSenderClient;
use Equidna\BirdFlock\Tests\Support\NoArgSender;
use Equidna\BirdFlock\Tests\Support\RequiresScalarSender;
use Equidna\BirdFlock\Tests\Support\WrongSender;
use Equidna\BirdFlock\Tests\TestCase;
use Illuminate\Container\Container;
use InvalidArgumentException;
use RuntimeException;

final class SenderResolverTest extends TestCase
{
    public function testResolvesShortClassStringSender(): void
    {
        $this->setConfigValue('bird-flock.channels.sms', [
            'strategy' => 'round_robin',
            'senders' => [
                'acme' => NoArgSender::class,
            ],
        ]);

        $sender = (new SenderResolver())->resolve('sms');

        $this->assertInstanceOf(NoArgSender::class, $sender);
    }

    public function testResolvesArraySenderConfigAndConfigArguments(): void
    {
        $this->setConfigValue('services.acme.api_key', 'secret');
        $this->setConfigValue('bird-flock.channels.sms', [
            'strategy' => 'round_robin',
            'senders' => [
                'acme' => [
                    'sender' => ConfigurableSender::class,
                    'arguments' => [
                        'apiKey' => 'config:services.acme.api_key',
                        'from' => '+15551234567',
                    ],
                ],
            ],
        ]);

        $sender = (new SenderResolver())->resolve('sms');

        $this->assertInstanceOf(ConfigurableSender::class, $sender);
        $this->assertSame('secret', $sender->apiKey);
        $this->assertSame('+15551234567', $sender->from);
    }

    public function testResolvesSenderDefinitionClassAndConfigArguments(): void
    {
        $this->setConfigValue('services.acme.api_key', 'secret');
        $this->setConfigValue('services.acme.from_sms', '+15551234567');
        $this->setConfigValue('bird-flock.channels.sms', [
            'strategy' => 'round_robin',
            'senders' => [
                'acme' => ConfigurableSenderDefinition::class,
            ],
        ]);

        $sender = (new SenderResolver())->resolve('sms');

        $this->assertInstanceOf(ConfigurableSender::class, $sender);
        $this->assertSame('secret', $sender->apiKey);
        $this->assertSame('+15551234567', $sender->from);
    }

    public function testAutowiresTypedDependenciesAndPassesNamedArguments(): void
    {
        $client = new FakeSenderClient();
        Container::getInstance()->instance(FakeSenderClient::class, $client);

        $this->setConfigValue('bird-flock.channels.sms', [
            'strategy' => 'round_robin',
            'senders' => [
                'acme' => [
                    'sender' => AutowiredSender::class,
                    'arguments' => [
                        'from' => '+15551234567',
                    ],
                ],
            ],
        ]);

        $sender = (new SenderResolver())->resolve('sms');

        $this->assertInstanceOf(AutowiredSender::class, $sender);
        $this->assertSame($client, $sender->client);
        $this->assertSame('+15551234567', $sender->from);
    }

    public function testMissingSenderClassThrowsClearException(): void
    {
        $this->setConfigValue('bird-flock.channels.sms', [
            'strategy' => 'round_robin',
            'senders' => [
                'acme' => [
                    'sender' => 'App\\Messaging\\MissingSender',
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Sender class 'App\\Messaging\\MissingSender' for 'sms:acme' does not exist");

        (new SenderResolver())->resolve('sms');
    }

    public function testWrongInterfaceThrowsClearException(): void
    {
        $this->setConfigValue('bird-flock.channels.sms', [
            'strategy' => 'round_robin',
            'senders' => [
                'acme' => WrongSender::class,
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("must implement");

        (new SenderResolver())->resolve('sms');
    }

    public function testMissingRequiredScalarArgumentThrowsClearException(): void
    {
        $this->setConfigValue('bird-flock.channels.sms', [
            'strategy' => 'round_robin',
            'senders' => [
                'acme' => RequiresScalarSender::class,
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unable to resolve sender 'acme' for channel 'sms'");

        (new SenderResolver())->resolve('sms');
    }
}
