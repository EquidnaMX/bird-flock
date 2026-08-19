<?php

/** Verifies immediate channel-job dispatch. */

namespace Equidna\BirdFlock\Tests\Unit\Jobs;

use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Jobs\DispatchMessageJob;
use Equidna\BirdFlock\Jobs\SendEmailJob;
use Equidna\BirdFlock\Jobs\SendSmsJob;
use Equidna\BirdFlock\Jobs\SendWhatsappJob;
use Equidna\BirdFlock\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

final class DispatchMessageJobTest extends TestCase
{
    /** @dataProvider channels */
    public function test_first_channel_job_is_dispatched_without_delay(string $channel, string $jobClass): void
    {
        Queue::fake();
        $payload = new FlightPlan(
            channel: $channel,
            to: $channel === 'email' ? 'user@example.test' : '+525512345678',
            text: 'Test message',
        );

        (new DispatchMessageJob('message-1', $payload))->handle();

        Queue::assertPushed($jobClass, static fn ($job): bool => $job->delay === null);
    }

    /** @return array<string, array{string, class-string}> */
    public static function channels(): array
    {
        return [
            'sms' => ['sms', SendSmsJob::class],
            'whatsapp' => ['whatsapp', SendWhatsappJob::class],
            'email' => ['email', SendEmailJob::class],
        ];
    }
}
