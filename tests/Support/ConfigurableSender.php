<?php

namespace Equidna\BirdFlock\Tests\Support;

use Equidna\BirdFlock\Contracts\MessageSenderInterface;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\DTO\ProviderSendResult;

final class ConfigurableSender implements MessageSenderInterface
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $from,
    ) {
        //
    }

    public function send(FlightPlan $payload): ProviderSendResult
    {
        return ProviderSendResult::success('fake');
    }
}
