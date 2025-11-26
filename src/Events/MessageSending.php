<?php

namespace Equidna\BirdFlock\Events;

use Equidna\BirdFlock\DTO\FlightPlan;

final class MessageSending
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $channel,
        public readonly FlightPlan $payload,
        public readonly int $attempt
    ) {
    }
}
