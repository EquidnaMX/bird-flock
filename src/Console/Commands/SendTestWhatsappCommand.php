<?php

/**
 * Artisan command for sending test WhatsApp messages.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Console\Commands
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

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
    /**
     * Detailed help shown by `php artisan help bird-flock:send-whatsapp`.
     *
     * Examples:
     *  php artisan bird-flock:send-whatsapp "+14155551234" "Hello"
     *      --media="https://example.com/img.jpg" --idempotency="order-1234-whatsapp"
     */
    protected $help = "Send a test WhatsApp message via Bird Flock.\n\n" .
        "Positional args: to (E.164 recipient), text (optional).\n" .
        "Options: --media (repeatable), --idempotency (stable key to deduplicate).";

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
