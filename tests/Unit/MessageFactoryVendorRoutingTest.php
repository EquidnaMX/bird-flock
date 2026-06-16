<?php

namespace Equidna\BirdFlock\Tests\Unit;

use Equidna\BirdFlock\MessageFactory;
use Equidna\BirdFlock\Senders\Labsmobile\LabsmobileSmsSender;
use Equidna\BirdFlock\Tests\TestCase;
use Equidna\BirdFlock\Tests\Support\NoArgSender;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;

final class MessageFactoryVendorRoutingTest extends TestCase
{
    public function testUnsupportedVendorForChannelThrowsClearException(): void
    {
        $this->setConfigValue('bird-flock.channels.sms', [
            'vendors' => ['mailgun'],
            'strategy' => 'round_robin',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Sender 'mailgun' is not configured for channel 'sms'");

        MessageFactory::createSender('sms');
    }

    public function testLabsmobileSmsVendorCreatesLabsmobileSender(): void
    {
        $this->setConfigValue('bird-flock.channels.sms', [
            'vendors' => ['labsmobile'],
            'strategy' => 'round_robin',
        ]);

        $this->setConfigValue('bird-flock-labsmobile.username', 'user@example.com');
        $this->setConfigValue('bird-flock-labsmobile.token', 'secret');
        app()->instance('bird-flock.labsmobile.http', new Client());
        app()->bind(ClientInterface::class, fn () => app('bird-flock.labsmobile.http'));

        $sender = MessageFactory::createSender('sms');

        $this->assertInstanceOf(LabsmobileSmsSender::class, $sender);
    }

    public function testExternalSenderConfiguredByClassCreatesSender(): void
    {
        $this->setConfigValue('bird-flock.channels.sms', [
            'strategy' => 'round_robin',
            'senders' => [
                'acme' => NoArgSender::class,
            ],
        ]);

        $sender = MessageFactory::createSender('sms');

        $this->assertInstanceOf(NoArgSender::class, $sender);
    }
}
