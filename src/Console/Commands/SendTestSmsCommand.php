<?php

/**
 * Artisan command for sending test SMS messages.
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

final class SendTestSmsCommand extends Command
{
    protected $signature = 'bird-flock:send-sms
        {to : Destination phone number (E.164)}
        {text? : Message text (optional)}
        {--idempotency= : Optional idempotency key}';

    protected $description = 'Send a test SMS via Bird Flock.';

    /**
     * Detailed help shown by `php artisan help bird-flock:send-sms`.
     *
     * Examples:
     *  php artisan bird-flock:send-sms "+14155551234" "Hello world" --idempotency="order-1234-sms"
     */
    protected $help = "Send a test SMS message via Bird Flock.\n\n" .
        "Positional args: to (E.164 recipient), text (optional).\n" .
        "Options: --idempotency (stable key to deduplicate).";
    public function handle(): int
    {
        $to = $this->argument('to');
        $text = $this->argument('text') ?? 'Test SMS from Bird Flock';
        $idempotency = $this->option('idempotency') ?: null;

        $flight = new FlightPlan(
            channel: 'sms',
            to: $to,
            text: $text,
            idempotencyKey: $idempotency
        );

        $messageId = BirdFlock::dispatch($flight);

        $this->info("SMS queued (message_id={$messageId}).");

        return self::SUCCESS;
    }
}
