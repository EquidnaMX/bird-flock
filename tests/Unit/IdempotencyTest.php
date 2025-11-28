<?php

declare(strict_types=1);

namespace Equidna\BirdFlock\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Equidna\BirdFlock\BirdFlock;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;

final class IdempotencyTest extends TestCase
{
    private function makeRepo(array $existing = []): OutboundMessageRepositoryInterface
    {
        return new class($existing) implements OutboundMessageRepositoryInterface {
            private array $messages = [];
            private array $byKey = [];
            public function __construct(array $existing)
            {
                foreach ($existing as $row) {
                    $this->messages[$row['id_outboundMessage']] = $row;
                    if (isset($row['idempotencyKey'])) {
                        $this->byKey[$row['idempotencyKey']] = $row['id_outboundMessage'];
                    }
                }
            }
            public function create(array $data): mixed
            {
                $this->messages[$data['id_outboundMessage']] = $data;
                if (!empty($data['idempotencyKey'])) {
                    $this->byKey[$data['idempotencyKey']] = $data['id_outboundMessage'];
                }
                return $data['id_outboundMessage'];
            }
            public function updateStatus(string $id, string $status, ?array $meta = null): void
            {
                if (isset($this->messages[$id])) {
                    $this->messages[$id]['status'] = $status;
                    if ($meta) {
                        $this->messages[$id] = array_merge($this->messages[$id], $meta);
                    }
                }
            }
            public function findByIdempotencyKey(string $key): ?array
            {
                $id = $this->byKey[$key] ?? null;
                return $id ? $this->messages[$id] : null;
            }
            public function incrementAttempts(string $id): void
            {
                if (isset($this->messages[$id])) {
                    $this->messages[$id]['attempts'] = ($this->messages[$id]['attempts'] ?? 0) + 1;
                }
            }
            public function resetForRetry(string $id, array $data): void
            {
                if (isset($this->messages[$id])) {
                    $this->messages[$id]['status'] = 'queued';
                    $this->messages[$id]['attempts'] = 0;
                    $this->messages[$id] = array_merge($this->messages[$id], $data);
                }
            }
        };
    }

    public function testDuplicateKeyReturnsExistingId(): void
    {
        $existing = [
            [
                'id_outboundMessage' => '01HXEXISTING1',
                'status' => 'queued',
                'idempotencyKey' => 'tenant:1:order:100:sms',
            ],
        ];
        $repo = $this->makeRepo($existing);

        $plan = new FlightPlan(
            channel: 'sms',
            to: '+15550001111',
            text: 'duplicate test',
            idempotencyKey: 'tenant:1:order:100:sms'
        );

        $id = BirdFlock::dispatch($plan, $repo);
        $this->assertSame('01HXEXISTING1', $id);
    }

    public function testFailedExistingRowIsResetAndReused(): void
    {
        $existing = [
            [
                'id_outboundMessage' => '01HXEXISTING2',
                'status' => 'failed',
                'attempts' => 3,
                'idempotencyKey' => 'tenant:9:order:200:email',
            ],
        ];
        $repo = $this->makeRepo($existing);

        $plan = new FlightPlan(
            channel: 'email',
            to: 'user@example.com',
            text: 'reset test',
            idempotencyKey: 'tenant:9:order:200:email'
        );

        $id = BirdFlock::dispatch($plan, $repo);
        $this->assertSame('01HXEXISTING2', $id);
    }
}
