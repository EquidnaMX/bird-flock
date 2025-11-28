<?php

/**
 * Unit tests for CircuitBreaker state transitions.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Unit\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Unit\Support;

use Equidna\BirdFlock\Support\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerTest extends TestCase
{
    public function testOpensAfterFailureThreshold(): void
    {
        $cb = new CircuitBreaker('test_service', failureThreshold: 3, timeout: 60, successThreshold: 2);

        $this->assertSame('closed', $cb->getState(), 'Initial state should be closed');

        $cb->recordFailure();
        $this->assertSame('closed', $cb->getState(), 'State should remain closed after 1 failure');

        $cb->recordFailure();
        $this->assertSame('closed', $cb->getState(), 'State should remain closed after 2 failures');

        $cb->recordFailure();
        $this->assertSame('open', $cb->getState(), 'State should open after threshold reached');
    }

    public function testTransitionsToHalfOpenAfterTimeoutAndClosesAfterSuccesses(): void
    {
        $cb = new CircuitBreaker('half_open_service', failureThreshold: 2, timeout: 1, successThreshold: 2);

        // Open circuit
        $cb->recordFailure();
        $cb->recordFailure();
        $this->assertSame('open', $cb->getState());

        // Force last failure time into the past beyond timeout
        Cache::put('circuit_breaker:half_open_service:last_failure', time() - 5, 3600);

        // isAvailable should transition to half-open
        $this->assertTrue($cb->isAvailable());
        $this->assertSame('half_open', $cb->getState(), 'State should transition to half_open after timeout');

        // First success should not close yet
        $cb->recordSuccess();
        $this->assertSame('half_open', $cb->getState());

        // Second success should close circuit
        $cb->recordSuccess();
        $this->assertSame('closed', $cb->getState(), 'Circuit should close after success threshold');
    }

    public function testFailureInHalfOpenReopensCircuit(): void
    {
        $cb = new CircuitBreaker('reopen_service', failureThreshold: 2, timeout: 1, successThreshold: 2);

        // Open circuit
        $cb->recordFailure();
        $cb->recordFailure();
        $this->assertSame('open', $cb->getState());

        Cache::put('circuit_breaker:reopen_service:last_failure', time() - 5, 3600);
        $cb->isAvailable(); // transition to half_open
        $this->assertSame('half_open', $cb->getState());

        // Failure in half_open should reopen
        $cb->recordFailure();
        $this->assertSame('open', $cb->getState(), 'Failure during half_open must reopen circuit');
    }

    public function testResetClosesAndClearsState(): void
    {
        $cb = new CircuitBreaker('reset_service', failureThreshold: 1, timeout: 60, successThreshold: 1);

        $cb->recordFailure(); // opens immediately due to threshold 1
        $this->assertSame('open', $cb->getState());

        $cb->reset();
        $this->assertSame('closed', $cb->getState(), 'Reset should close circuit');
    }
}
