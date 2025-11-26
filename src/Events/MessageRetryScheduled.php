<?php

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
