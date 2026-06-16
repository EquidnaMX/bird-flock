<?php

namespace Equidna\BirdFlock\Tests\Unit\Jobs;

use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Jobs\AbstractSendJob;
use Equidna\BirdFlock\Jobs\SendSmsJob;
use Equidna\BirdFlock\Tests\TestCase;
use ReflectionClass;

final class ChannelRetryConfigTest extends TestCase
{
    public function testSendJobReadsRetryFromChannelConfig(): void
    {
        $this->setConfigValue('bird-flock.channels.sms.retry', [
            'max_attempts' => 7,
            'base_delay_ms' => 250,
            'max_delay_ms' => 5000,
        ]);

        $job = new SendSmsJob('MSG123', new FlightPlan(
            channel: 'sms',
            to: '+15551234567',
            text: 'Test'
        ));

        $reflection = new ReflectionClass(AbstractSendJob::class);
        $baseDelay = $reflection->getProperty('baseDelayMs');
        $maxDelay = $reflection->getProperty('maxDelayMs');
        $baseDelay->setAccessible(true);
        $maxDelay->setAccessible(true);

        $this->assertSame(7, $job->tries);
        $this->assertSame(250, $baseDelay->getValue($job));
        $this->assertSame(5000, $maxDelay->getValue($job));
    }

    public function testLegacyRootRetryConfigIsIgnored(): void
    {
        $this->setConfigValue('bird-flock.channels.sms.retry', []);
        $this->setConfigValue('bird-flock.retry.channels.sms', [
            'max_attempts' => 9,
            'base_delay_ms' => 250,
            'max_delay_ms' => 5000,
        ]);

        $job = new SendSmsJob('MSG123', new FlightPlan(
            channel: 'sms',
            to: '+15551234567',
            text: 'Test'
        ));

        $this->assertSame(3, $job->tries);
    }
}
