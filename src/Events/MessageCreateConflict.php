<?php

/**
 * Event for DB create conflict on idempotency key.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Events
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Events;

use Equidna\BirdFlock\DTO\FlightPlan;

/**
 * Dispatched when a DB unique-constraint conflict occurs during create.
 *
 * Happens when concurrent dispatches attempt to create rows with the same
 * idempotency key; one wins and the other catches the constraint error.
 */
final class MessageCreateConflict
{
    /**
     * Create a new event instance.
     *
     * @param string           $existingMessageId Existing message identifier
     * @param string           $idempotencyKey    Idempotency key that collided
     * @param string           $channel           Channel (sms|whatsapp|email)
     * @param FlightPlan|null  $payload           Original payload
     */
    public function __construct(
        public string $existingMessageId,
        public string $idempotencyKey,
        public string $channel,
        public ?FlightPlan $payload = null,
    ) {
    }
}
