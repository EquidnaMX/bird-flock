<?php

namespace Equidna\BirdFlock\Tests\Support;

use Equidna\BirdFlock\Contracts\SenderDefinitionInterface;
use Equidna\BirdFlock\Contracts\SenderConfigValidatorInterface;
use Equidna\BirdFlock\Support\Logger;

final class ConfigurableSenderDefinition implements SenderDefinitionInterface, SenderConfigValidatorInterface
{
    public function sender(): string
    {
        return ConfigurableSender::class;
    }

    public function arguments(): array
    {
        return [
            'apiKey' => 'config:services.acme.api_key',
            'from' => 'config:services.acme.from_sms',
        ];
    }

    public function validator(): ?string
    {
        return self::class;
    }

    public function validate(string $channel, string $vendor, array $senderConfig): void
    {
        Logger::info('bird-flock.test_sender.validator', [
            'channel' => $channel,
            'vendor' => $vendor,
        ]);
    }
}
