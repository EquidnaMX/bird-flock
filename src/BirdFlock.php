<?php

/**
 * Message bus for dispatching messages with idempotency support.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock;

use Illuminate\Support\Str;
use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Jobs\DispatchMessageJob;
use Illuminate\Support\Facades\Event;
use Equidna\BirdFlock\Support\Logger;
use Equidna\BirdFlock\Events\MessageQueued;
use Equidna\BirdFlock\Events\MessageRetryScheduled;

/**
 * Orchestrates message dispatching with idempotency and routing.
 */
final class BirdFlock
{
    /**
     * Dispatch a message for sending.
     *
     * @param FlightPlan                             $payload     Message payload
     * @param OutboundMessageRepositoryInterface|null    $repository Optional repository (useful for tests)
     *
     * @return string Message identifier
     */
    public static function dispatch(
        FlightPlan $payload,
        ?OutboundMessageRepositoryInterface $repository = null
    ): string {
        $repository ??= app(OutboundMessageRepositoryInterface::class);

        Logger::info('bird-flock.dispatch.received', [
            'channel' => $payload->channel,
            'idempotency_key' => $payload->idempotencyKey,
            'to' => $payload->to,
        ]);

        $messageId = (string) Str::ulid();
        $shouldCreate = true;

        if ($payload->idempotencyKey) {
            $existing = $repository->findByIdempotencyKey($payload->idempotencyKey);

            if ($existing) {
                if (in_array($existing['status'], ['sent', 'delivered', 'queued', 'sending'], true)) {
                    Logger::info('bird-flock.dispatch.duplicate_skipped', [
                        'message_id' => $existing['id_outboundMessage'],
                        'status' => $existing['status'],
                        'channel' => $payload->channel,
                    ]);
                    return $existing['id_outboundMessage'];
                }

                $messageId = $existing['id_outboundMessage'];
                $shouldCreate = false;

                Logger::info('bird-flock.dispatch.retrying', [
                    'message_id' => $messageId,
                    'idempotency_key' => $payload->idempotencyKey,
                    'channel' => $payload->channel,
                ]);

                $repository->resetForRetry($messageId, [
                    'to' => $payload->to,
                    'subject' => $payload->subject,
                    'templateKey' => $payload->templateKey,
                    'payload' => $payload->toArray(),
                ]);

                Event::dispatch(new MessageRetryScheduled(
                    messageId: $messageId,
                    payload: $payload,
                    channel: $payload->channel,
                    attempt: 0,
                    delaySeconds: 0
                ));
            }
        }

        if ($shouldCreate) {
            $repository->create([
                'id_outboundMessage' => $messageId,
                'channel' => $payload->channel,
                'to' => $payload->to,
                'subject' => $payload->subject,
                'templateKey' => $payload->templateKey,
                'payload' => $payload->toArray(),
                'status' => 'queued',
                'idempotencyKey' => $payload->idempotencyKey,
                'queuedAt' => now(),
            ]);
        }

        $queue = config('bird-flock.default_queue', 'default');

        Logger::info('bird-flock.dispatch.queued', [
            'message_id' => $messageId,
            'queue' => $queue,
            'channel' => $payload->channel,
        ]);

        Event::dispatch(new MessageQueued($messageId, $payload));

        DispatchMessageJob::dispatch($messageId, $payload)
            ->onQueue($queue);

        return $messageId;
    }
}

