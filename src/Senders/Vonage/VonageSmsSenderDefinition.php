<?php

namespace Equidna\BirdFlock\Senders\Vonage;

use Equidna\BirdFlock\Contracts\SenderDefinitionInterface;
use Equidna\BirdFlock\Contracts\SenderConfigValidatorInterface;
use Equidna\BirdFlock\Support\Logger;

final class VonageSmsSenderDefinition implements SenderDefinitionInterface, SenderConfigValidatorInterface
{
    public function sender(): string
    {
        return VonageSmsSender::class;
    }

    public function arguments(): array
    {
        return [
            'from' => 'config:bird-flock-vonage.from_sms',
        ];
    }

    public function validator(): ?string
    {
        return self::class;
    }

    public function validate(string $channel, string $vendor, array $senderConfig): void
    {
        $apiKey = config('bird-flock-vonage.api_key');
        $apiSecret = config('bird-flock-vonage.api_secret');

        if (! $apiKey || ! $apiSecret) {
            Logger::warning('bird-flock-vonage.credentials_missing', [
                'hint' => 'Set VONAGE_API_KEY and VONAGE_API_SECRET to enable Vonage SMS sends',
            ]);
        }

        if (! config('bird-flock-vonage.from_sms')) {
            Logger::warning('bird-flock-vonage.from_sms_missing', [
                'hint' => 'Set VONAGE_FROM_SMS to enable Vonage SMS sends',
            ]);
        }
    }
}
