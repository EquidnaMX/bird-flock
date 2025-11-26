<?php

/**
 * Eloquent implementation of outbound message repository.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Repositories
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Repositories;

use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Equidna\BirdFlock\Models\OutboundMessage;

/**
 * Eloquent-based repository for outbound messages.
 */
final class EloquentOutboundMessageRepository implements OutboundMessageRepositoryInterface
{
    /**
     * Create a new outbound message record.
     *
     * @param array<string, mixed> $data Message data
     *
     * @return mixed The created message identifier
     */
    public function create(array $data): mixed
    {
        $message = OutboundMessage::create($data);

        return $message->id_outboundMessage;
    }

    /**
     * Update the status of an outbound message.
     *
     * @param string               $id     Message identifier
     * @param string               $status New status
     * @param array<string, mixed> $meta   Optional metadata to update
     *
     * @return void
     */
    public function updateStatus(
        string $id,
        string $status,
        ?array $meta = null
    ): void {
        $message = OutboundMessage::where('providerMessageId', $id)
            ->orWhere('id_outboundMessage', $id)
            ->first();

        if (!$message) {
            return;
        }

        $updateData = ['status' => $status];

        if ($status === 'sent') {
            $updateData['sentAt'] = now();
        } elseif ($status === 'delivered') {
            $updateData['deliveredAt'] = now();
        } elseif ($status === 'failed') {
            $updateData['failedAt'] = now();
        }

        if ($meta) {
            if (isset($meta['provider_message_id'])) {
                $updateData['providerMessageId'] = $meta['provider_message_id'];
            }

            if (isset($meta['error_code'])) {
                $updateData['errorCode'] = $meta['error_code'];
            }

            if (isset($meta['error_message'])) {
                $updateData['errorMessage'] = $meta['error_message'];
            }
        }

        $message->update($updateData);
    }

    /**
     * Find a message by its idempotency key.
     *
     * @param string $key Idempotency key
     *
     * @return array<string, mixed>|null Message data or null if not found
     */
    public function findByIdempotencyKey(string $key): ?array
    {
        $message = OutboundMessage::where('idempotencyKey', $key)->first();

        return $message ? $message->toArray() : null;
    }

    /**
     * {@inheritdoc}
     */
    public function incrementAttempts(string $id): void
    {
        OutboundMessage::where('id_outboundMessage', $id)->increment('attempts');
    }

    /**
     * {@inheritdoc}
     */
    public function resetForRetry(string $id, array $data): void
    {
        $message = OutboundMessage::where('id_outboundMessage', $id)->first();

        if (!$message) {
            return;
        }

        $defaults = [
            'status' => 'queued',
            'queuedAt' => now(),
            'sentAt' => null,
            'deliveredAt' => null,
            'failedAt' => null,
            'providerMessageId' => null,
            'errorCode' => null,
            'errorMessage' => null,
            'attempts' => 0,
        ];

        $message->update(array_merge($defaults, $data));
    }
}
