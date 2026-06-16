<?php

namespace Equidna\BirdFlock\Senders\Labsmobile;

use Equidna\BirdFlock\Contracts\SenderDefinitionInterface;
use Equidna\BirdFlock\Contracts\SenderConfigValidatorInterface;
use Equidna\BirdFlock\Support\Logger;

final class LabsmobileSmsSenderDefinition implements SenderDefinitionInterface, SenderConfigValidatorInterface
{
    public function sender(): string
    {
        return LabsmobileSmsSender::class;
    }

    public function arguments(): array
    {
        return [
            'endpoint' => 'config:bird-flock-labsmobile.endpoint',
            'from' => 'config:bird-flock-labsmobile.from_sms',
            'ackUrl' => 'config:bird-flock-labsmobile.ack_url',
            'test' => 'config:bird-flock-labsmobile.test',
            'long' => 'config:bird-flock-labsmobile.long',
            'ucs2' => 'config:bird-flock-labsmobile.ucs2',
            'shortlink' => 'config:bird-flock-labsmobile.shortlink',
        ];
    }

    public function validator(): ?string
    {
        return self::class;
    }

    public function validate(string $channel, string $vendor, array $senderConfig): void
    {
        $username = config('bird-flock-labsmobile.username');
        $token = config('bird-flock-labsmobile.token');

        if (! $username || ! $token) {
            Logger::warning('bird-flock-labsmobile.credentials_missing', [
                'hint' => 'Set LABSMOBILE_USERNAME and LABSMOBILE_TOKEN to enable LabsMobile SMS sends',
            ]);
        }

        $ackUrl = config('bird-flock-labsmobile.ack_url');
        if ($ackUrl && ! filter_var($ackUrl, FILTER_VALIDATE_URL)) {
            Logger::warning('bird-flock-labsmobile.ack_url_invalid', [
                'value' => $ackUrl,
            ]);
        }
    }
}
