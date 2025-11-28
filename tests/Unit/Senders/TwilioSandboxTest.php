<?php

namespace Equidna\BirdFlock\Tests\Unit\Senders;

use Equidna\BirdFlock\Senders\TwilioSmsSender;
use Equidna\BirdFlock\Senders\TwilioWhatsappSender;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Tests\Support\InMemoryLogger;
use Equidna\BirdFlock\Tests\TestCase;
use Twilio\Rest\Client as TwilioClient;
use Illuminate\Container\Container;

final class TwilioSandboxTest extends TestCase
{
    public function testSmsSenderLogsSandboxFromUsedWhenSandboxEnabledInferred(): void
    {
        $this->setConfigValue('bird-flock.twilio.sandbox_mode', true);
        $this->setConfigValue('bird-flock.twilio.sandbox_from', null);
        $this->setConfigValue('bird-flock.twilio.from_sms', '+15551234567');

        $logger = new InMemoryLogger();
        Container::getInstance()->instance('bird-flock.logger', $logger);

        $client = $this->createMock(TwilioClient::class);
        $client->messages = new class {
            public function create($to, $params)
            {
                $m = new \stdClass();
                $m->sid = 'SM123';
                $m->to = $to;
                $m->from = $params['from'] ?? null;
                $m->status = 'queued';
                return $m;
            }
        };

        $sender = new TwilioSmsSender($client, '+15551234567', null, null);

        $plan = new FlightPlan(channel: 'sms', to: '5551234567', text: 'hello');
        $result = $sender->send($plan);

        $this->assertTrue($logger->has('bird-flock.sender.twilio_sms.sandbox_from_used'));
        $this->assertSame('SM123', $result->providerMessageId);
    }

    public function testWhatsappSenderLogsSandboxFromUsedWhenSandboxEnabledInferred(): void
    {
        $this->setConfigValue('bird-flock.twilio.sandbox_mode', true);
        $this->setConfigValue('bird-flock.twilio.sandbox_from', null);
        $this->setConfigValue('bird-flock.twilio.from_whatsapp', 'whatsapp:+15551234567');

        $logger = new InMemoryLogger();
        Container::getInstance()->instance('bird-flock.logger', $logger);

        $client = $this->createMock(TwilioClient::class);
        $client->messages = new class {
            public function create($to, $params)
            {
                $m = new \stdClass();
                $m->sid = 'WH123';
                $m->to = $to;
                $m->from = $params['from'] ?? null;
                $m->status = 'queued';
                return $m;
            }
        };

        $sender = new TwilioWhatsappSender($client, 'whatsapp:+15551234567', null);

        $plan = new FlightPlan(channel: 'whatsapp', to: '521234567890', text: 'hi', templateKey: 'T1');
        $result = $sender->send($plan);

        $this->assertTrue($logger->has('bird-flock.sender.twilio_whatsapp.sandbox_from_used'));
        $this->assertSame('WH123', $result->providerMessageId);
    }
}
