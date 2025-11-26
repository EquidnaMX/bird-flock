<?php

namespace Equidna\BirdFlock\Support;

use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Events\MessageDeadLettered;
use Equidna\BirdFlock\Jobs\DispatchMessageJob;
use Equidna\BirdFlock\Models\DeadLetterEntry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

final class DeadLetterService
{
    public function __construct(
        private readonly OutboundMessageRepositoryInterface $repository
    ) {
    }

    public function record(
        string $messageId,
        string $channel,
        FlightPlan $payload,
        int $attempts,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?string $lastException = null
    ): void {
        if (!config('bird-flock.dead_letter.enabled', true)) {
            return;
        }

        DeadLetterEntry::create([
            'id' => (string) Str::ulid(),
            'message_id' => $messageId,
            'channel' => $channel,
            'payload' => $payload->toArray(),
            'attempts' => $attempts,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'last_exception' => $lastException,
        ]);

        Event::dispatch(new MessageDeadLettered($messageId, $channel, $errorCode, $errorMessage));
    }

    public function replay(DeadLetterEntry $entry): void
    {
        $payload = FlightPlan::fromArray($entry->payload ?? []);

        $this->repository->resetForRetry(
            $entry->message_id,
            [
                'to' => $payload->to,
                'subject' => $payload->subject,
                'templateKey' => $payload->templateKey,
                'payload' => $payload->toArray(),
            ]
        );

        DispatchMessageJob::dispatch($entry->message_id, $payload)
            ->onQueue(config('bird-flock.default_queue', 'default'));

        $entry->delete();
    }
}
