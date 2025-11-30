<?php

/**
 * Job for dispatching messages to channel-specific jobs.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Jobs
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Support\BackoffStrategy;

/**
 * Routes messages to appropriate channel-specific sending jobs.
 */
final class DispatchMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param string $messageId Message identifier
     * @param FlightPlan $payload Message payload
     */
    public function __construct(
        private readonly string $messageId,
        private readonly FlightPlan $payload,
    ) {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $job = match ($this->payload->channel) {
            'sms' => new SendSmsJob($this->messageId, $this->payload),
            'whatsapp' => new SendWhatsappJob($this->messageId, $this->payload),
            'email' => new SendEmailJob($this->messageId, $this->payload),
            default => null,
        };

        if ($job) {
            $queue = config('bird-flock.default_queue', 'default');
            $attemptIndex = max(0, $this->attempts() - 1);
            $delay = BackoffStrategy::exponentialWithJitter($attemptIndex);

            $delaySeconds = max(1, (int) ceil($delay / 1000));
            $job->onQueue($queue)->delay($delaySeconds);

            dispatch($job);
        }
    }
}
