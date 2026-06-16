<?php

namespace Equidna\BirdFlock\Tests\Unit\Models;

use Equidna\BirdFlock\Models\DeadLetterEntry;
use Equidna\BirdFlock\Models\OutboundMessage;
use Equidna\BirdFlock\Tests\TestCase;

final class DatabaseConnectionModelTest extends TestCase
{
    public function testOutboundMessageUsesConfiguredConnection(): void
    {
        config(['bird-flock.database.connection' => 'bird_flock']);

        $this->assertSame('bird_flock', (new OutboundMessage())->getConnectionName());
    }

    public function testDeadLetterEntryUsesConfiguredConnection(): void
    {
        config(['bird-flock.database.connection' => 'bird_flock']);

        $this->assertSame('bird_flock', (new DeadLetterEntry())->getConnectionName());
    }
}
