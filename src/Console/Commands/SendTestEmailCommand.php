<?php

/**
 * Artisan command for sending test email messages.
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

final class SendTestEmailCommand extends Command
{
    protected $signature = 'bird-flock:send-email
        {to : Destination email address}
        {subject? : Email subject}
        {--text= : Plain text body}
        {--html= : HTML body}
        {--idempotency= : Optional idempotency key}';

    protected $description = 'Send a test email via Bird Flock.';
    /**
     * Detailed help shown by `php artisan help bird-flock:send-email`.
     *
     * Examples:
     *  php artisan bird-flock:send-email "to@example.com" --text="Plain text body"
     *      --html="<p>HTML</p>" --idempotency="order-1234-email"
     */
    protected $help = "Send a test email via Bird Flock.\n\n" .
        "Arguments: to (recipient email).\n" .
        "Options: --text, --html, --idempotency (stable key to deduplicate).";

    public function handle(): int
    {
        $to = $this->argument('to');
        $subject = $this->argument('subject') ?? 'Test email from Bird Flock';
        $text = $this->option('text') ?: "This is a test email sent by Bird Flock.";
        $html = $this->option('html') ?: null;
        $idempotency = $this->option('idempotency') ?: null;

        $flight = new FlightPlan(
            channel: 'email',
            to: $to,
            subject: $subject,
            text: $text,
            html: $html,
            idempotencyKey: $idempotency
        );

        $messageId = BirdFlock::dispatch($flight);

        $this->info("Email queued (message_id={$messageId}).");

        return self::SUCCESS;
    }
}
