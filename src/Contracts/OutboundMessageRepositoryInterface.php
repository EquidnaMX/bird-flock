<?php

/**
 * Repository contract for outbound message persistence.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Contracts
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Contracts;

/**
 * Defines the contract for outbound message data persistence.
 */
interface OutboundMessageRepositoryInterface
{
    /**
     * Create a new outbound message record.
     *
     * @param array<string, mixed> $data Message data
     *
     * @return mixed The created message identifier
     */
    public function create(array $data): mixed;

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
    ): void;

    /**
     * Find a message by its idempotency key.
     *
     * @param string $key Idempotency key
     *
     * @return array<string, mixed>|null Message data or null if not found
     */
    public function findByIdempotencyKey(string $key): ?array;

    /**
     * Increment the attempt counter for a message.
     *
     * @param string $id Message identifier
     *
     * @return void
     */
    public function incrementAttempts(string $id): void;

    /**
     * Reset an existing outbound message for a retry attempt.
     *
     * @param string               $id   Message identifier
     * @param array<string, mixed> $data Fields to update alongside the reset
     *
     * @return void
     */
    public function resetForRetry(string $id, array $data): void;
}
