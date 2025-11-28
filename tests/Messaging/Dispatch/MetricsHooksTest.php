<?php

/**
 * Unit tests for metrics hooks in dispatch.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Messaging\Dispatch
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Messaging\Dispatch;

use Equidna\BirdFlock\BirdFlock;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Equidna\BirdFlock\Contracts\MetricsCollectorInterface;
use Equidna\BirdFlock\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Illuminate\Container\Container;

final class MetricsHooksTest extends TestCase
{
    public function testDuplicateSkipIncrementsMetricAndDispatchesEvent(): void
    {
        $existing = [
            'id_outboundMessage' => 'MSG-123',
            'status' => 'sent',
        ];

        // Stub repository to return existing on idempotency lookup.
        $repo = new class($existing) implements OutboundMessageRepositoryInterface
        {
            private $existing;

            public function __construct($existing)
            {
                $this->existing = $existing;
            }

            public function create(array $data): mixed
            {
                return $data['id_outboundMessage'];
            }

            public function updateStatus(string $id, string $status, ?array $meta = null): void
            {
                // Intentionally empty.
            }

            public function findByIdempotencyKey(string $key): ?array
            {
                return $this->existing;
            }

            public function incrementAttempts(string $id): void
            {
                // Intentionally empty.
            }

            public function resetForRetry(string $id, array $data): void
            {
                // Intentionally empty.
            }
        };

        Container::getInstance()->instance(OutboundMessageRepositoryInterface::class, $repo);

        /** @var MetricsCollectorInterface|MockObject $metrics */
        $metrics = $this->createMock(MetricsCollectorInterface::class);
        $metrics->expects($this->once())
            ->method('increment')
            ->with('bird_flock.duplicate_skipped', 1, $this->arrayHasKey('channel'));

        Container::getInstance()->instance(MetricsCollectorInterface::class, $metrics);

        $id = BirdFlock::dispatch(new FlightPlan(channel: 'sms', to: '+15551234567', idempotencyKey: 'k1'));

        $this->assertSame('MSG-123', $id);
    }
}
