<?php

/**
 * Event dispatched when a message is being sent to a provider.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Events
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Events;

use Equidna\BirdFlock\DTO\FlightPlan;

final class MessageSending
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $channel,
        public readonly FlightPlan $payload,
        public readonly int $attempt
    ) {}
}
