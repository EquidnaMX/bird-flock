<?php

namespace Equidna\BirdFlock\Events;

use Equidna\BirdFlock\DTO\FlightPlan;

final class MessageQueued
{
    public function __construct(
        public readonly string $messageId,
        public readonly FlightPlan $payload
    ) {
    }
}
