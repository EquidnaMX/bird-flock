<?php

namespace Equidna\BirdFlock\Events;

use Equidna\BirdFlock\DTO\ProviderSendResult;

final class MessageFinalized
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $channel,
        public readonly ProviderSendResult $result
    ) {
    }
}
