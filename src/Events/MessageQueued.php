<?php

/**
 * Event dispatched when a message is queued for dispatch.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Events
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Events;

use Equidna\BirdFlock\DTO\FlightPlan;

final class MessageQueued
{
    public function __construct(
        public readonly string $messageId,
        public readonly FlightPlan $payload
    ) {}
}
