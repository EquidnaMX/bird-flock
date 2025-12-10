<?php

/**
 * Abstract base class for send jobs.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Jobs
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Jobs;

use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Events\MessageFinalized;
use Equidna\BirdFlock\Events\MessageRetryScheduled;
use Equidna\BirdFlock\Events\MessageSending;
use Equidna\BirdFlock\MessageFactory;
use Equidna\BirdFlock\Support\BackoffStrategy;
use Equidna\BirdFlock\Support\DeadLetterService;
use Equidna\BirdFlock\Support\Logger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * Base job for sending messages through various channels.
 */
abstract class AbstractSendJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 3;
    private int $baseDelayMs;
    private int $maxDelayMs;
    private float $startTime;

    /**
     * Creates a new send job instance.
     *
     * @param string     $messageId Message identifier.
     * @param FlightPlan $payload   Message payload.
     */
    public function __construct(
        private readonly string $messageId,
        private readonly FlightPlan $payload,
    ) {
        $config = config("bird-flock.retry.channels.{$this->getChannel()}", []);
        $this->tries = (int) ($config['max_attempts'] ?? $this->tries);
        $this->baseDelayMs = (int) ($config['base_delay_ms'] ?? 1000);
        $this->maxDelayMs = (int) ($config['max_delay_ms'] ?? 60000);
    }

    /**
     * Returns the channel name for this job.
     *
     * @return non-empty-string Channel identifier (sms, whatsapp, email).
     */
    abstract protected function getChannel(): string;

    /**
     * Executes the job.
     *
     * @param OutboundMessageRepositoryInterface $repository Message repository.
     *
     * @return void
     */
    public function handle(OutboundMessageRepositoryInterface $repository): void
    {
        $this->startTime = microtime(true);

        $repository->incrementAttempts($this->messageId);

        $repository->updateStatus(
            id: $this->messageId,
            status: 'sending',
        );

        Event::dispatch(new MessageSending(
            messageId: $this->messageId,
            channel: $this->getChannel(),
            payload: $this->payload,
            attempt: $this->attempts()
        ));

        Logger::info('bird-flock.job.sending', [
            'job' => static::class,
            'message_id' => $this->messageId,
            'channel' => $this->getChannel(),
            'attempt' => $this->attempts(),
            'total_attempts' => $this->attempts(),
        ]);

        $senderStartTime = microtime(true);
        $sender = MessageFactory::createSender($this->getChannel());
        $result = $sender->send($this->payload);
        $senderDurationMs = (microtime(true) - $senderStartTime) * 1000;

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
            channel: $this->getChannel(),
            result: $result
        ));

        $totalDurationMs = (microtime(true) - $this->startTime) * 1000;

        Logger::info('bird-flock.job.result', [
            'job' => static::class,
            'message_id' => $this->messageId,
            'channel' => $this->getChannel(),
            'status' => $result->status,
            'attempt' => $this->attempts(),
            'total_attempts' => $this->attempts(),
            'provider_message_id' => $result->providerMessageId,
            'error_code' => $result->errorCode,
            'sender_duration_ms' => round($senderDurationMs, 2),
            'total_duration_ms' => round($totalDurationMs, 2),
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
                channel: $this->getChannel(),
                attempt: $this->attempts(),
                delaySeconds: $delaySeconds
            ));

            Logger::warning('bird-flock.job.retry_scheduled', [
                'job' => static::class,
                'message_id' => $this->messageId,
                'channel' => $this->getChannel(),
                'delay_seconds' => $delaySeconds,
                'attempt' => $this->attempts(),
                'retry_count' => $this->attempts(),
            ]);

            $this->release($delaySeconds);
        }
    }

    /**
     * Calculates delay for the next retry in seconds.
     *
     * @return int Delay in seconds (minimum 1).
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

    /**
     * Handles job failure.
     *
     * @param Throwable $exception Exception that caused the failure.
     *
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        $this->recordDeadLetter(
            errorCode: 'JOB_EXCEPTION',
            errorMessage: $exception->getMessage(),
            exception: $exception
        );
    }

    /**
     * Records a message in the dead letter queue.
     *
     * @param string          $errorCode    Error code identifier.
     * @param string          $errorMessage Human-readable error message.
     * @param Throwable|null  $exception    Optional exception instance.
     *
     * @return void
     */
    private function recordDeadLetter(
        string $errorCode,
        string $errorMessage,
        ?Throwable $exception = null
    ): void {
        if (!config('bird-flock.dead_letter.enabled', true)) {
            return;
        }

        // Check if properties are initialized before accessing them
        try {
            $messageId = $this->messageId;
            $channel = $this->getChannel();
            $payload = $this->payload;
        } catch (\Error $e) {
            // Properties not initialized, log error without property access
            Logger::error('bird-flock.job.dead_letter', [
                'job' => static::class,
                'message_id' => 'UNINITIALIZED',
                'channel' => 'UNKNOWN',
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'initialization_error' => $e->getMessage(),
                'total_attempts' => $this->attempts(),
            ]);
            return;
        }

        Logger::error('bird-flock.job.dead_letter', [
            'job' => static::class,
            'message_id' => $messageId,
            'channel' => $channel,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'total_attempts' => $this->attempts(),
        ]);

        /** @var DeadLetterService $service */
        $service = app(DeadLetterService::class);
        $service->record(
            messageId: $messageId,
            channel: $channel,
            payload: $payload,
            attempts: $this->attempts(),
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            lastException: $exception ? (string) $exception : null
        );
    }
}
