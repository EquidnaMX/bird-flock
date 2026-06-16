<?php

namespace Equidna\BirdFlock\Senders\Twilio;

use Equidna\BirdFlock\Contracts\SenderDefinitionInterface;
use Equidna\BirdFlock\Contracts\SenderConfigValidatorInterface;
use Equidna\BirdFlock\Support\Logger;

final class TwilioWhatsappSenderDefinition implements SenderDefinitionInterface, SenderConfigValidatorInterface
{
    public function sender(): string
    {
        return TwilioWhatsappSender::class;
    }

    public function arguments(): array
    {
        return [
            'from' => 'config:bird-flock-twilio.from_whatsapp',
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

        $fromWhatsApp = config('bird-flock-twilio.from_whatsapp');
        $sandboxMode = config('bird-flock-twilio.sandbox_mode', false);
        $sandboxFrom = config('bird-flock-twilio.sandbox_from');

        if (! $fromWhatsApp) {
            if (! $sandboxMode) {
                Logger::warning('bird-flock-twilio.whatsapp_sender_missing', [
                    'hint' => 'Set TWILIO_FROM_WHATSAPP when using WhatsApp in production',
                ]);
            } else {
                Logger::info('bird-flock-twilio.whatsapp_sandbox_no_from', [
                    'hint' => 'Sandbox mode: WhatsApp FROM may be inferred from TWILIO_FROM_WHATSAPP',
                ]);
            }
        }

        if ($sandboxFrom && ! str_starts_with((string) $sandboxFrom, 'whatsapp:')) {
            Logger::warning('bird-flock-twilio.sandbox_from_missing_prefix', [
                'value' => $sandboxFrom,
                'hint' => 'WhatsApp sandbox From should include the "whatsapp:" prefix',
            ]);
        }
    }
}
