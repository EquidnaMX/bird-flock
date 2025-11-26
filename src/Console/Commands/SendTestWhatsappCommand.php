<?php

namespace Equidna\BirdFlock\Console\Commands;

use Illuminate\Console\Command;
use Equidna\BirdFlock\BirdFlock;
use Equidna\BirdFlock\DTO\FlightPlan;

final class SendTestWhatsappCommand extends Command
{
    protected $signature = 'bird-flock:send-whatsapp
        {to : Destination WhatsApp number (E.164)}
        {text? : Message text (optional)}
        {--media=* : Optional media URLs (multiple allowed)}
        {--idempotency= : Optional idempotency key}';

    protected $description = 'Send a test WhatsApp message via Bird Flock.';

    public function handle(): int
    {
        $to = $this->argument('to');
        $text = $this->argument('text') ?? 'Test WhatsApp message from Bird Flock';
        $media = $this->option('media') ?: [];
        $idempotency = $this->option('idempotency') ?: null;

        $flight = new FlightPlan(
            channel: 'whatsapp',
            to: $to,
            text: $text,
            mediaUrls: $media,
            idempotencyKey: $idempotency
        );

        $messageId = BirdFlock::dispatch($flight);

        $this->info("WhatsApp queued (message_id={$messageId}).");

        return self::SUCCESS;
    }
}
