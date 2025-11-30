<?php

/**
 * Event for duplicate message skipped.
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
 * Dispatched when a duplicate message is skipped.
 *
 * Occurs when an existing queued/sent/delivered message with the same
 * idempotency key prevents a new dispatch.
 */
final class MessageDuplicateSkipped
{
    /**
     * Create a new event instance.
     *
     * @param string           $existingMessageId Existing message identifier
     * @param string           $idempotencyKey    Idempotency key
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
