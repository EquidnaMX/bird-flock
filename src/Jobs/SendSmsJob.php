<?php

/**
 * Job for sending SMS messages.
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
use Illuminate\Support\Facades\Event;
use Throwable;
use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Events\MessageFinalized;
use Equidna\BirdFlock\Events\MessageRetryScheduled;
use Equidna\BirdFlock\Events\MessageSending;
use Equidna\BirdFlock\MessageFactory;
use Equidna\BirdFlock\Support\DeadLetterService;
use Equidna\BirdFlock\Support\BackoffStrategy;
use Equidna\BirdFlock\Support\Logger;

/**
 * Sends SMS messages via Twilio.
 */
final class SendSmsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 3;
    private int $baseDelayMs;
    private int $maxDelayMs;

    /**
     * Create a new job instance.
     *
     * @param string         $messageId Message identifier
     * @param FlightPlan $payload   Message payload
     */
    public function __construct(
        private readonly string $messageId,
        private readonly FlightPlan $payload,
    ) {
        $config = config('bird-flock.retry.channels.sms', []);
        $this->tries = (int) ($config['max_attempts'] ?? $this->tries);
        $this->baseDelayMs = (int) ($config['base_delay_ms'] ?? 1000);
        $this->maxDelayMs = (int) ($config['max_delay_ms'] ?? 60000);
    }

    /**
     * Execute the job.
     *
     * @param OutboundMessageRepositoryInterface $repository Message repository
     *
     * @return void
     */
    public function handle(OutboundMessageRepositoryInterface $repository): void
    {
        $repository->incrementAttempts($this->messageId);

        $repository->updateStatus(
            id: $this->messageId,
            status: 'sending',
        );

        Event::dispatch(new MessageSending(
            messageId: $this->messageId,
            channel: 'sms',
            payload: $this->payload,
            attempt: $this->attempts()
        ));

        Logger::info('bird-flock.job.sending', [
            'job' => static::class,
            'message_id' => $this->messageId,
            'channel' => 'sms',
            'attempt' => $this->attempts(),
        ]);

        $sender = MessageFactory::createSender('sms');
        $result = $sender->send($this->payload);

        $repository->updateStatus(
            id: $this->messageId,
            status: $result->status,
            meta: [
                'provider_message_id' => $result->providerMessageId,
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage,
            ],
        );

        Event::dispatch(new MessageFinalized(
            messageId: $this->messageId,
            channel: 'sms',
            result: $result
        ));

        Logger::info('bird-flock.job.result', [
            'job' => static::class,
            'message_id' => $this->messageId,
            'channel' => 'sms',
            'status' => $result->status,
            'attempt' => $this->attempts(),
            'provider_message_id' => $result->providerMessageId,
            'error_code' => $result->errorCode,
        ]);

        if ($result->status === 'failed') {
            if ($this->attempts() >= $this->tries) {
                $this->recordDeadLetter(
                    errorCode: $result->errorCode ?? 'FAILED',
                    errorMessage: $result->errorMessage ?? 'Provider failure'
                );
                return;
            }

            $delaySeconds = $this->computeBackoffSeconds();
            Event::dispatch(new MessageRetryScheduled(
                messageId: $this->messageId,
                payload: $this->payload,
                channel: 'sms',
                attempt: $this->attempts(),
                delaySeconds: $delaySeconds
            ));
            Logger::warning('bird-flock.job.retry_scheduled', [
                'job' => static::class,
                'message_id' => $this->messageId,
                'channel' => 'sms',
                'delay_seconds' => $delaySeconds,
                'attempt' => $this->attempts(),
            ]);
            $this->release($delaySeconds);
        }
    }

    /**
     * Calculate delay for the next retry in seconds.
     */
    private function computeBackoffSeconds(): int
    {
        $delayMs = BackoffStrategy::exponentialWithJitter(
            $this->attempts(),
            $this->baseDelayMs,
            $this->maxDelayMs
        );

        return max(1, (int) ceil($delayMs / 1000));
    }

    public function failed(Throwable $exception): void
    {
        $this->recordDeadLetter(
            errorCode: 'JOB_EXCEPTION',
            errorMessage: $exception->getMessage(),
            exception: $exception
        );
    }

    private function recordDeadLetter(
        string $errorCode,
        string $errorMessage,
        ?Throwable $exception = null
    ): void {
        if (!config('bird-flock.dead_letter.enabled', true)) {
            return;
        }

        Logger::error('bird-flock.job.dead_letter', [
            'job' => static::class,
            'message_id' => $this->messageId,
            'channel' => 'sms',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);

        /** @var DeadLetterService $service */
        $service = app(DeadLetterService::class);
        $service->record(
            messageId: $this->messageId,
            channel: 'sms',
            payload: $this->payload,
            attempts: $this->attempts(),
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            lastException: $exception ? (string) $exception : null
        );
    }
}
