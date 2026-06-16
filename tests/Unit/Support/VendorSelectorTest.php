<?php

namespace Equidna\BirdFlock\Tests\Unit\Support;

use Equidna\BirdFlock\Support\VendorSelector;
use Equidna\BirdFlock\Tests\TestCase;
use InvalidArgumentException;

final class VendorSelectorTest extends TestCase
{
    public function testRoundRobinAlternatesConfiguredVendors(): void
    {
        $this->setConfigValue('bird-flock.channels.sms', [
            'strategy' => 'round_robin',
            'senders' => [
                'twilio' => [],
                'vonage' => [],
            ],
        ]);

        $selector = new VendorSelector();

        $this->assertSame('twilio', $selector->select('sms'));
        $this->assertSame('vonage', $selector->select('sms'));
        $this->assertSame('twilio', $selector->select('sms'));
    }

    public function testRandomSelectsOnlyConfiguredVendors(): void
    {
        $this->setConfigValue('bird-flock.channels.email', [
            'strategy' => 'random',
            'senders' => [
                'mailgun' => [],
                'sendgrid' => [],
            ],
        ]);

        $selector = new VendorSelector();

        for ($i = 0; $i < 20; $i++) {
            $this->assertContains($selector->select('email'), ['mailgun', 'sendgrid']);
        }
    }

    public function testEmptyVendorListThrowsClearException(): void
    {
        $this->setConfigValue('bird-flock.channels.sms', [
            'senders' => [],
            'strategy' => 'round_robin',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No vendors configured for channel: sms');

        (new VendorSelector())->select('sms');
    }

    public function testUnsupportedStrategyThrowsClearException(): void
    {
        $this->setConfigValue('bird-flock.channels.sms', [
            'strategy' => 'weighted',
            'senders' => [
                'twilio' => [],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported vendor selection strategy 'weighted' for channel: sms");

        (new VendorSelector())->select('sms');
    }

    public function testLegacyVendorsAreStillSupported(): void
    {
        $this->setConfigValue('bird-flock.channels.sms', [
            'vendors' => ['twilio', 'vonage'],
            'strategy' => 'round_robin',
        ]);

        $selector = new VendorSelector();

        $this->assertSame('twilio', $selector->select('sms'));
        $this->assertSame('vonage', $selector->select('sms'));
    }
}
