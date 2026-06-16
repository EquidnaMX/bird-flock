<?php

namespace Equidna\BirdFlock\Senders\Twilio;

use Equidna\BirdFlock\Contracts\SenderDefinitionInterface;
use Equidna\BirdFlock\Contracts\SenderConfigValidatorInterface;
use Equidna\BirdFlock\Support\Logger;

final class TwilioSmsSenderDefinition implements SenderDefinitionInterface, SenderConfigValidatorInterface
{
    public function sender(): string
    {
        return TwilioSmsSender::class;
    }

    public function arguments(): array
    {
        return [
            'from' => 'config:bird-flock-twilio.from_sms',
            'messagingServiceSid' => 'config:bird-flock-twilio.messaging_service_sid',
            'statusCallback' => 'config:bird-flock-twilio.status_webhook_url',
        ];
    }

    public function validator(): ?string
    {
        return self::class;
    }

    public function validate(string $channel, string $vendor, array $senderConfig): void
    {
        $accountSid = config('bird-flock-twilio.account_sid');
        $authToken = config('bird-flock-twilio.auth_token');

        if (! $accountSid || ! $authToken) {
            Logger::warning('bird-flock-twilio.credentials_missing', [
                'hint' => 'Set TWILIO_ACCOUNT_SID and TWILIO_AUTH_TOKEN to enable Twilio services',
            ]);
            return;
        }

        $messagingService = config('bird-flock-twilio.messaging_service_sid');
        $fromSms = config('bird-flock-twilio.from_sms');

        if (! $messagingService && ! $fromSms) {
            Logger::warning('bird-flock-twilio.sms_sender_not_configured', [
                'hint' => 'Set TWILIO_MESSAGING_SERVICE_SID or TWILIO_FROM_SMS to enable SMS sends',
            ]);
        }

        $sandboxFrom = config('bird-flock-twilio.sandbox_from');
        if ($sandboxFrom && ! str_starts_with((string) $sandboxFrom, 'whatsapp:')) {
            Logger::warning('bird-flock-twilio.sandbox_from_missing_prefix', [
                'value' => $sandboxFrom,
                'hint' => 'WhatsApp sandbox From should include the "whatsapp:" prefix',
            ]);
        }
    }
}
