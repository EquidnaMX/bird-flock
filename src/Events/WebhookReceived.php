<?php

/**
 * Event dispatched when a webhook is received from a provider.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Events
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Events;

final class WebhookReceived
{
    /**
     * Create a new webhook received event.
     *
     * @param string               $provider Provider name (twilio|sendgrid).
     * @param string               $type     Event type.
     * @param array<string, mixed> $payload  Webhook payload data.
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $type,
        public readonly array $payload,
    ) {}
}
