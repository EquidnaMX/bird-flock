<?php

namespace Equidna\BirdFlock\Events;

final class WebhookReceived
{
    public function __construct(
        public readonly string $provider,
        public readonly string $type,
        public readonly array $payload
    ) {
    }
}
