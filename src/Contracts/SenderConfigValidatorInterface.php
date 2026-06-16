<?php

namespace Equidna\BirdFlock\Contracts;

interface SenderConfigValidatorInterface
{
    /**
     * Validate sender-specific configuration and emit warnings through the package logger.
     *
     * @param array<string, mixed> $senderConfig
     */
    public function validate(string $channel, string $vendor, array $senderConfig): void;
}
