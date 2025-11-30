<?php

/**
 * Concurrent idempotency and race condition tests.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Unit
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Equidna\BirdFlock\BirdFlock;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Illuminate\Database\QueryException;

final class ConcurrentIdempotencyTest extends TestCase
{
    private function makeRaceConditionRepo(
        int $conflictOnAttempt = 1,
        ?string $existingId = '01EXISTING123'
    ): OutboundMessageRepositoryInterface {
        $attemptCount = 0;

        return new class($conflictOnAttempt, $existingId, $attemptCount) implements OutboundMessageRepositoryInterface
        {
            private array $messages = [];
            private array $byKey = [];

            public function __construct(
                private int $conflictOnAttempt,
                private ?string $existingId,
                private int &$attemptCount
            ) {}

            public function create(array $data): mixed
            {
                $this->attemptCount++;

                // Simulate race condition: first N attempts throw unique constraint
                if ($this->attemptCount <= $this->conflictOnAttempt) {
                    $ex = new \PDOException('Duplicate entry', 1062);
                    $qex = new QueryException('default', 'insert', [], $ex);

                    // Set errorInfo property
                    $ref = new \ReflectionProperty(QueryException::class, 'errorInfo');
                    $ref->setAccessible(true);
                    $ref->setValue($qex, ['23000', 1062, 'Duplicate entry']);

                    throw $qex;
                }

                // Successful insert
                $this->messages[$data['id_outboundMessage']] = $data;
                if (!empty($data['idempotencyKey'])) {
                    $this->byKey[$data['idempotencyKey']] = $data['id_outboundMessage'];
                }

                return $data['id_outboundMessage'];
            }

            public function findByIdempotencyKey(string $key): ?array
            {
                // First lookup returns null (race condition)
                // Second lookup returns existing record
                if ($this->attemptCount === 1) {
                    return null;
                }

                if ($this->existingId && !isset($this->byKey[$key])) {
                    return [
                        'id_outboundMessage' => $this->existingId,
                        'status' => 'queued',
                        'idempotencyKey' => $key,
                    ];
                }

                $id = $this->byKey[$key] ?? null;
                return $id ? $this->messages[$id] : null;
            }

            public function updateStatus(string $id, string $status, ?array $meta = null): void {}

            public function incrementAttempts(string $id): void {}

            public function resetForRetry(string $id, array $data): void {}
        };
    }

    public function testConcurrentInsertReturnsExistingId(): void
    {
        $repo = $this->makeRaceConditionRepo(conflictOnAttempt: 1, existingId: '01RACE123');

        $flight = new FlightPlan(
            channel: 'sms',
            to: '+15005550006',
            text: 'Race condition test',
            idempotencyKey: 'race:test:1'
        );

        $result = BirdFlock::dispatch($flight, $repo);

        // Should return existing ID, not create new
        $this->assertSame('01RACE123', $result);
    }

    public function testMultipleConcurrentInsertsEventuallySucceed(): void
    {
        $repo = $this->makeRaceConditionRepo(conflictOnAttempt: 3, existingId: '01MULTI123');

        $flight = new FlightPlan(
            channel: 'email',
            to: 'test@example.com',
            text: 'Multi-attempt race',
            idempotencyKey: 'race:multi:1'
        );

        $result = BirdFlock::dispatch($flight, $repo);

        // Should eventually succeed after retries
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testDifferentIdempotencyKeysNoConflict(): void
    {
        $messages = [];

        $repo = new class($messages) implements OutboundMessageRepositoryInterface
        {
            public function __construct(private array &$messages) {}

            public function create(array $data): mixed
            {
                $this->messages[$data['id_outboundMessage']] = $data;
                return $data['id_outboundMessage'];
            }

            public function findByIdempotencyKey(string $key): ?array
            {
                foreach ($this->messages as $msg) {
                    if (($msg['idempotencyKey'] ?? null) === $key) {
                        return $msg;
                    }
                }
                return null;
            }

            public function updateStatus(string $id, string $status, ?array $meta = null): void {}

            public function incrementAttempts(string $id): void {}

            public function resetForRetry(string $id, array $data): void {}
        };

        $flight1 = new FlightPlan(
            channel: 'sms',
            to: '+15005550001',
            text: 'Message 1',
            idempotencyKey: 'key:1'
        );

        $flight2 = new FlightPlan(
            channel: 'sms',
            to: '+15005550002',
            text: 'Message 2',
            idempotencyKey: 'key:2'
        );

        $id1 = BirdFlock::dispatch($flight1, $repo);
        $id2 = BirdFlock::dispatch($flight2, $repo);

        // Both should succeed with different IDs
        $this->assertNotEquals($id1, $id2);
        $this->assertCount(2, $messages);
    }

    public function testNullIdempotencyKeyAllowsDuplicates(): void
    {
        $messages = [];

        $repo = new class($messages) implements OutboundMessageRepositoryInterface
        {
            public function __construct(private array &$messages) {}

            public function create(array $data): mixed
            {
                $this->messages[$data['id_outboundMessage']] = $data;
                return $data['id_outboundMessage'];
            }

            public function findByIdempotencyKey(string $key): ?array
            {
                return null;
            }

            public function updateStatus(string $id, string $status, ?array $meta = null): void {}

            public function incrementAttempts(string $id): void {}

            public function resetForRetry(string $id, array $data): void {}
        };

        $flight = new FlightPlan(
            channel: 'sms',
            to: '+15005550006',
            text: 'No idempotency key',
            idempotencyKey: null
        );

        $id1 = BirdFlock::dispatch($flight, $repo);
        $id2 = BirdFlock::dispatch($flight, $repo);

        // Should create two separate messages
        $this->assertNotEquals($id1, $id2);
        $this->assertCount(2, $messages);
    }
}
