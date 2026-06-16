<?php

namespace Equidna\BirdFlock\Senders\Mailgun;

use Equidna\BirdFlock\Contracts\SenderDefinitionInterface;
use Equidna\BirdFlock\Contracts\SenderConfigValidatorInterface;
use Equidna\BirdFlock\Support\Logger;

final class MailgunEmailSenderDefinition implements SenderDefinitionInterface, SenderConfigValidatorInterface
{
    public function sender(): string
    {
        return MailgunEmailSender::class;
    }

    public function arguments(): array
    {
        return [
            'domain' => 'config:bird-flock-mailgun.domain',
            'fromEmail' => 'config:bird-flock-mailgun.from_email',
            'fromName' => 'config:bird-flock-mailgun.from_name',
            'replyTo' => 'config:bird-flock-mailgun.reply_to',
            'templates' => 'config:bird-flock-mailgun.templates',
        ];
    }

    public function validator(): ?string
    {
        return self::class;
    }

    public function validate(string $channel, string $vendor, array $senderConfig): void
    {
        if (! config('bird-flock-mailgun.api_key')) {
            Logger::warning('bird-flock-mailgun.api_key_missing', [
                'hint' => 'Set MAILGUN_API_KEY to enable Mailgun email sends',
            ]);
        }

        if (! config('bird-flock-mailgun.domain')) {
            Logger::warning('bird-flock-mailgun.domain_missing', [
                'hint' => 'Set MAILGUN_DOMAIN to enable Mailgun email sends',
            ]);
        }

        $fromEmail = config('bird-flock-mailgun.from_email');
        if (! $fromEmail) {
            Logger::warning('bird-flock-mailgun.from_email_missing', [
                'hint' => 'Set MAILGUN_FROM_EMAIL to ensure proper envelope from address',
            ]);
        } elseif (! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            Logger::warning('bird-flock-mailgun.from_email_invalid', [
                'value' => $fromEmail,
            ]);
        }
    }
}
