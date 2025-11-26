<?php

namespace Equidna\BirdFlock\Events;

final class MessageDeadLettered
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $channel,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null
    ) {
    }
}
