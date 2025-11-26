<?php

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

final class SendEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 3;
    private int $baseDelayMs;
    private int $maxDelayMs;

    public function __construct(
        private readonly string $messageId,
        private readonly FlightPlan $payload,
    ) {
        $config = config('bird-flock.retry.channels.email', []);
        $this->tries = (int) ($config['max_attempts'] ?? $this->tries);
        $this->baseDelayMs = (int) ($config['base_delay_ms'] ?? 1000);
        $this->maxDelayMs = (int) ($config['max_delay_ms'] ?? 60000);
    }

    public function handle(OutboundMessageRepositoryInterface $repository): void
    {
        $repository->incrementAttempts($this->messageId);

        $repository->updateStatus(
            id: $this->messageId,
            status: 'sending',
        );

        Event::dispatch(new MessageSending(
            messageId: $this->messageId,
            channel: 'email',
            payload: $this->payload,
            attempt: $this->attempts()
        ));

        Logger::info('bird-flock.job.sending', [
            'job' => static::class,
            'message_id' => $this->messageId,
            'channel' => 'email',
            'attempt' => $this->attempts(),
        ]);

        $sender = MessageFactory::createSender('email');
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
            channel: 'email',
            result: $result
        ));

        Logger::info('bird-flock.job.result', [
            'job' => static::class,
            'message_id' => $this->messageId,
            'channel' => 'email',
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

            $delay = $this->computeBackoffSeconds();
            Event::dispatch(new MessageRetryScheduled(
                messageId: $this->messageId,
                payload: $this->payload,
                channel: 'email',
                attempt: $this->attempts(),
                delaySeconds: $delay
            ));
            Logger::warning('bird-flock.job.retry_scheduled', [
                'job' => static::class,
                'message_id' => $this->messageId,
                'channel' => 'email',
                'delay_seconds' => $delay,
                'attempt' => $this->attempts(),
            ]);
            $this->release($delay);
        }
    }

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
            'channel' => 'email',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);

        /** @var DeadLetterService $service */
        $service = app(DeadLetterService::class);
        $service->record(
            messageId: $this->messageId,
            channel: 'email',
            payload: $this->payload,
            attempts: $this->attempts(),
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            lastException: $exception ? (string) $exception : null
        );
    }
}
