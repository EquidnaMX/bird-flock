<?php

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
