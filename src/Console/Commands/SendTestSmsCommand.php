<?php

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
