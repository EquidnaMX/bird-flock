<?php

/**
 * Event dispatched when a message retry is scheduled after a failure.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Events
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Events;

use Equidna\BirdFlock\DTO\FlightPlan;

final class MessageRetryScheduled
{
    public function __construct(
        public readonly string $messageId,
        public readonly FlightPlan $payload,
        public readonly string $channel,
        public readonly int $attempt,
        public readonly int $delaySeconds
    ) {
    }
}
