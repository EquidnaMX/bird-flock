<?php

namespace Equidna\BirdFlock\Senders\Sendgrid;

use Equidna\BirdFlock\Contracts\SenderDefinitionInterface;
use Equidna\BirdFlock\Contracts\SenderConfigValidatorInterface;
use Equidna\BirdFlock\Support\Logger;

final class SendgridEmailSenderDefinition implements SenderDefinitionInterface, SenderConfigValidatorInterface
{
    public function sender(): string
    {
        return SendgridEmailSender::class;
    }

    public function arguments(): array
    {
        return [
            'fromEmail' => 'config:bird-flock-sendgrid.from_email',
            'fromName' => 'config:bird-flock-sendgrid.from_name',
            'replyTo' => 'config:bird-flock-sendgrid.reply_to',
            'templates' => 'config:bird-flock-sendgrid.templates',
        ];
    }

    public function validator(): ?string
    {
        return self::class;
    }

    public function validate(string $channel, string $vendor, array $senderConfig): void
    {
        if (! config('bird-flock-sendgrid.api_key')) {
            Logger::warning('bird-flock-sendgrid.api_key_missing', [
                'hint' => 'Set SENDGRID_API_KEY to enable SendGrid email sends',
            ]);
        }

        $requireSigned = config('bird-flock-sendgrid.require_signed_webhooks');
        $publicKey = config('bird-flock-sendgrid.webhook_public_key');

        if ($requireSigned && ! $publicKey) {
            Logger::warning('bird-flock-sendgrid.webhook_signing_key_missing', [
                'hint' => 'Set SENDGRID_WEBHOOK_PUBLIC_KEY or disable SENDGRID_REQUIRE_SIGNED_WEBHOOKS',
            ]);
        }

        if (! $requireSigned && ! $publicKey) {
            Logger::info('bird-flock-sendgrid.webhook_signing_disabled');
        }

        $fromEmail = config('bird-flock-sendgrid.from_email');
        if (! $fromEmail) {
            Logger::warning('bird-flock-sendgrid.from_email_missing', [
                'hint' => 'Set SENDGRID_FROM_EMAIL to ensure proper envelope from address',
            ]);
        } elseif (! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            Logger::warning('bird-flock-sendgrid.from_email_invalid', [
                'value' => $fromEmail,
            ]);
        }
    }
}
