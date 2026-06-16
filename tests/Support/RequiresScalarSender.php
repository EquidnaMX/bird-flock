<?php

namespace Equidna\BirdFlock\Tests\Support;

use Equidna\BirdFlock\Contracts\MessageSenderInterface;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\DTO\ProviderSendResult;

final class RequiresScalarSender implements MessageSenderInterface
{
    public function __construct(
        public readonly string $apiKey,
    ) {
        //
    }

    public function send(FlightPlan $payload): ProviderSendResult
    {
        return ProviderSendResult::success('fake');
    }
}
