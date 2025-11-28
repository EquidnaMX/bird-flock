<?php

/**
 * Unit tests for BirdFlock dispatch with DB race conditions.
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

final class BirdFlockDispatchTest extends TestCase
{
    private function makeQueryException(
        string $message = 'unique constraint',
        array $errorInfo = ['23000', 1062, 'Duplicate entry'],
    ): QueryException {
        $pdoEx = new \PDOException($message, (int) ($errorInfo[1] ?? 0));
        $ex = new QueryException('default', 'insert into outbound_messages', [], $pdoEx);

        // set errorInfo property so code under test can inspect it
        $ref = new \ReflectionProperty(QueryException::class, 'errorInfo');
        $ref->setAccessible(true);
        $ref->setValue($ex, $errorInfo);

        return $ex;
    }

    public function testReturnsExistingIdWhenCreateThrowsUniqueAndFindReturnsExisting(): void
    {
        $repo = $this->createMock(OutboundMessageRepositoryInterface::class);

        $idempotency = 'order-1234-sms';

        // findByIdempotencyKey: first call => null, second call => existing record
        $repo->expects($this->exactly(2))
            ->method('findByIdempotencyKey')
            ->with($idempotency)
            ->willReturnOnConsecutiveCalls(null, ['id_outboundMessage' => '01FZEXAMPLE', 'status' => 'queued']);

        // create will throw a unique QueryException
        $repo->expects($this->once())
            ->method('create')
            ->willThrowException($this->makeQueryException('duplicate key'));

        $flight = new FlightPlan(channel: 'sms', to: '+15550001111', text: 'hello', idempotencyKey: $idempotency);

        $result = BirdFlock::dispatch($flight, $repo);

        $this->assertSame('01FZEXAMPLE', $result);
    }

    public function testRetriesAndSucceedsWhenCreateTransientlyFailsThenSucceeds(): void
    {
        $repo = $this->createMock(OutboundMessageRepositoryInterface::class);

        $idempotency = 'order-9999-email';

        // findByIdempotencyKey: always return null in this scenario
        $repo->method('findByIdempotencyKey')->willReturn(null);

        // create: first call throws QueryException, second call succeeds (returns null)
        $repo->expects($this->exactly(2))
            ->method('create')
            ->will($this->onConsecutiveCalls(
                $this->throwException($this->makeQueryException('duplicate key transient')),
                $this->returnValue(null)
            ));

        $flight = new FlightPlan(channel: 'email', to: 'to@example.com', text: 'hi', idempotencyKey: $idempotency);

        $result = BirdFlock::dispatch($flight, $repo);

        // Should return a string ULID-like id
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
}
