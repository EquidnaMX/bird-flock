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

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Equidna\BirdFlock\Contracts\MetricsCollectorInterface;
use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Events\MessageCreateConflict;
use Equidna\BirdFlock\Events\MessageDuplicateSkipped;
use Equidna\BirdFlock\Events\MessageQueued;
use Equidna\BirdFlock\Events\MessageRetryScheduled;
use Equidna\BirdFlock\Jobs\DispatchMessageJob;
use Equidna\BirdFlock\Support\Logger;
use Equidna\BirdFlock\Support\MailableConverter;
use Equidna\BirdFlock\Support\Masking;
use Equidna\BirdFlock\Support\MetricsCollector;
use Illuminate\Contracts\Mail\Mailable as MailableContract;
use Illuminate\Mail\Mailable;

/**
 * Orchestrates message dispatching with idempotency and routing.
 */
final class BirdFlock
{
    /**
     * Dispatch a message for sending.
     *
     * @param  FlightPlan                             $payload     Message payload.
     * @param  OutboundMessageRepositoryInterface|null $repository Optional repository (useful for tests).
     * @return string                                              Message identifier.
     * @throws \RuntimeException                                   When payload exceeds maximum size.
     */
    public static function dispatch(
        FlightPlan $payload,
        ?OutboundMessageRepositoryInterface $repository = null
    ): string {
        $repository ??= app(OutboundMessageRepositoryInterface::class);

        // Validate payload size to prevent queue backend issues
        $maxSize = config('bird-flock.max_payload_size', 262144);
        $payloadJson = json_encode($payload->toArray());

        if (strlen($payloadJson) > $maxSize) {
            Logger::error('bird-flock.dispatch.payload_too_large', [
                'size' => strlen($payloadJson),
                'max' => $maxSize,
                'idempotency_key' => $payload->idempotencyKey,
                'channel' => $payload->channel,
            ]);

            throw new \RuntimeException("Payload exceeds maximum size of {$maxSize} bytes");
        }

        Logger::info('bird-flock.dispatch.received', [
            'channel' => $payload->channel,
            'idempotency_key' => $payload->idempotencyKey,
            'to' => Masking::maskPhone($payload->to),
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

                    // Emit event and metrics hook for duplicate skips
                    Event::dispatch(new MessageDuplicateSkipped(
                        existingMessageId: $existing['id_outboundMessage'],
                        idempotencyKey: $payload->idempotencyKey ?? '',
                        channel: $payload->channel,
                        payload: $payload
                    ));

                    try {
                        app(MetricsCollectorInterface::class)->increment('bird_flock.duplicate_skipped', 1, [
                            'channel' => $payload->channel,
                        ]);
                    } catch (\Throwable $e) {
                        Logger::warning('bird-flock.metrics.fallback', [
                            'metric' => 'duplicate_skipped',
                            'error' => $e->getMessage(),
                        ]);
                        (new MetricsCollector())->increment('bird_flock.duplicate_skipped', 1, [
                            'channel' => $payload->channel,
                        ]);
                    }

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
            // Use single-attempt insert with atomic conflict resolution
            try {
                $data = [
                    'id_outboundMessage' => $messageId,
                    'channel' => $payload->channel,
                    'to' => $payload->to,
                    'subject' => $payload->subject,
                    'templateKey' => $payload->templateKey,
                    'payload' => $payload->toArray(),
                    'status' => 'queued',
                    'idempotencyKey' => $payload->idempotencyKey,
                    'queuedAt' => now(),
                ];

                // Attempt insert; on conflict, retrieve existing ID
                try {
                    $repository->create($data);
                } catch (QueryException $e) {
                    // Detect unique-constraint errors across common drivers
                    $sqlState = $e->errorInfo[0] ?? null;
                    $driverCode = $e->errorInfo[1] ?? null;

                    $pgUniqueStates = ['23505'];
                    $integrityStates = ['23000', '19'];
                    $isPostgresUnique = in_array($sqlState, $pgUniqueStates, true);
                    $isIntegrityConstraint = in_array($sqlState, $integrityStates, true);
                    $isMysqlDuplicateKey = $driverCode === 1062;
                    $messageIndicatesUnique = str_contains(strtolower($e->getMessage()), 'unique');

                    $isUniqueConstraint = $isPostgresUnique
                        || $isIntegrityConstraint
                        || $isMysqlDuplicateKey
                        || $messageIndicatesUnique;

                    if (! $isUniqueConstraint) {
                        throw $e; // unknown DB error, rethrow
                    }

                    // Conflict detected: retrieve the existing message
                    if ($payload->idempotencyKey) {
                        $existing = $repository->findByIdempotencyKey($payload->idempotencyKey);
                        if ($existing) {
                            Logger::info('bird-flock.dispatch.create_conflict', [
                                'existing_message_id' => $existing['id_outboundMessage'],
                                'channel' => $payload->channel,
                                'idempotency_key' => $payload->idempotencyKey,
                            ]);

                            Event::dispatch(new MessageCreateConflict(
                                existingMessageId: $existing['id_outboundMessage'],
                                idempotencyKey: $payload->idempotencyKey ?? '',
                                channel: $payload->channel,
                                payload: $payload
                            ));

                            try {
                                app(MetricsCollectorInterface::class)->increment('bird_flock.create_conflict', 1, [
                                    'channel' => $payload->channel,
                                ]);
                            } catch (\Throwable $e) {
                                Logger::warning('bird-flock.metrics.fallback', [
                                    'metric' => 'create_conflict',
                                    'error' => $e->getMessage(),
                                ]);
                                (new MetricsCollector())->increment('bird_flock.create_conflict', 1, [
                                    'channel' => $payload->channel,
                                ]);
                            }

                            $messageId = $existing['id_outboundMessage'];
                            $shouldCreate = false;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Logger::error('bird-flock.dispatch.create_failed', [
                    'message' => $e->getMessage(),
                    'channel' => $payload->channel,
                ]);
                throw $e;
            }
        }

        $queue = config('bird-flock.default_queue', 'default');

        Logger::info('bird-flock.dispatch.queued', [
            'message_id' => $messageId,
            'queue' => $queue,
            'channel' => $payload->channel,
            'scheduled' => $payload->sendAt !== null,
        ]);

        Event::dispatch(new MessageQueued($messageId, $payload));

        $job = DispatchMessageJob::dispatch($messageId, $payload)
            ->onQueue($queue);

        // Delay job if sendAt is in the future
        if ($payload->sendAt) {
            $sendAtCarbon = \Illuminate\Support\Carbon::instance($payload->sendAt);
            if ($sendAtCarbon->isFuture()) {
                $delay = max(0, $sendAtCarbon->getTimestamp() - now()->getTimestamp());
                $job->delay($delay);

                Logger::info('bird-flock.dispatch.scheduled', [
                    'message_id' => $messageId,
                    'send_at' => $payload->sendAt->format('Y-m-d\TH:i:s\Z'),
                    'delay_seconds' => $delay,
                ]);
            }
        }

        return $messageId;
    }

    /**
     * Dispatch multiple messages in a single atomic operation.
     *
     * @param  FlightPlan[]                            $payloads    Array of message payloads.
     * @param  OutboundMessageRepositoryInterface|null $repository  Optional repository.
     * @return array<string>                                        Array of message identifiers.
     * @throws \InvalidArgumentException                            When payloads contain non-FlightPlan instances.
     * @throws \RuntimeException                                    When payload exceeds maximum size.
     */
    public static function dispatchBatch(
        array $payloads,
        ?OutboundMessageRepositoryInterface $repository = null
    ): array {
        if (empty($payloads)) {
            return [];
        }

        $repository ??= app(OutboundMessageRepositoryInterface::class);
        $messageIds = [];
        $dataToInsert = [];
        $maxSize = config('bird-flock.max_payload_size', 262144);

        Logger::info('bird-flock.batch.received', [
            'count' => count($payloads),
        ]);

        // Prepare all messages
        foreach ($payloads as $payload) {
            if (! $payload instanceof FlightPlan) {
                throw new \InvalidArgumentException('All payloads must be FlightPlan instances');
            }

            $payloadJson = json_encode($payload->toArray());
            if (strlen($payloadJson) > $maxSize) {
                throw new \RuntimeException("Payload exceeds maximum size of {$maxSize} bytes");
            }

            $messageId = (string) Str::ulid();
            $messageIds[] = $messageId;

            $dataToInsert[] = [
                'id_outboundMessage' => $messageId,
                'channel' => $payload->channel,
                'to' => $payload->to,
                'subject' => $payload->subject,
                'templateKey' => $payload->templateKey,
                'payload' => $payload->toArray(),
                'status' => 'queued',
                'idempotencyKey' => $payload->idempotencyKey,
                'queuedAt' => now(),
                'createdAt' => now(),
                'updatedAt' => now(),
            ];
        }

        // Atomic batch insert with chunking to avoid DB packet size limits
        try {
            DB::transaction(function () use ($dataToInsert, $payloads, $messageIds) {
                $tableName = config('bird-flock.tables.outbound_messages');
                $chunkSize = config('bird-flock.batch_insert_chunk_size', 500);

                foreach (array_chunk($dataToInsert, $chunkSize) as $chunk) {
                    DB::table($tableName)->insert($chunk);
                }

                // Dispatch all jobs
                $queue = config('bird-flock.default_queue', 'default');
                foreach ($payloads as $index => $payload) {
                    $messageId = $messageIds[$index];

                    Event::dispatch(new MessageQueued($messageId, $payload));

                    $job = DispatchMessageJob::dispatch($messageId, $payload)
                        ->onQueue($queue);

                    if ($payload->sendAt) {
                        $sendAtCarbon = \Illuminate\Support\Carbon::instance($payload->sendAt);
                        if ($sendAtCarbon->isFuture()) {
                            $delay = max(0, $sendAtCarbon->getTimestamp() - now()->getTimestamp());
                            $job->delay($delay);
                        }
                    }
                }
            });

            Logger::info('bird-flock.batch.dispatched', [
                'count' => count($messageIds),
                'message_ids' => $messageIds,
            ]);
        } catch (\Throwable $e) {
            Logger::error('bird-flock.batch.failed', [
                'count' => count($payloads),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $messageIds;
    }

    /**
     * Dispatch a Laravel Mailable for sending.
     *
     * @param  Mailable|MailableContract               $mailable        Laravel Mailable instance.
     * @param  string                                  $to              Recipient email address.
     * @param  string|null                             $idempotencyKey  Optional idempotency key.
     * @param  \DateTimeInterface|null                 $sendAt          Optional scheduled send time.
     * @param  array<string, mixed>                    $metadata        Additional metadata.
     * @param  OutboundMessageRepositoryInterface|null $repository      Optional repository (useful for tests).
     * @return string                                                   Message identifier.
     * @throws \RuntimeException                                        When payload exceeds maximum size.
     */
    public static function dispatchMailable(
        Mailable|MailableContract $mailable,
        string $to,
        ?string $idempotencyKey = null,
        ?\DateTimeInterface $sendAt = null,
        array $metadata = [],
        ?OutboundMessageRepositoryInterface $repository = null
    ): string {
        Logger::info('bird-flock.dispatch.mailable_received', [
            'to' => Masking::maskEmail($to),
            'mailable_class' => get_class($mailable),
            'idempotency_key' => $idempotencyKey,
        ]);

        $flightPlan = MailableConverter::convert(
            mailable: $mailable,
            to: $to,
            idempotencyKey: $idempotencyKey,
            sendAt: $sendAt,
            metadata: $metadata
        );

        return self::dispatch($flightPlan, $repository);
    }
}
