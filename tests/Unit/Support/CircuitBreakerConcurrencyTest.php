<?php

/**
 * Edge case tests for CircuitBreaker concurrent access and race conditions.
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
use Equidna\BirdFlock\Tests\TestCase;

final class CircuitBreakerConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function testConcurrentFailuresDoNotExceedThreshold(): void
    {
        $cb = new CircuitBreaker('test_concurrent', failureThreshold: 5, timeout: 60);

        // Simulate 10 concurrent failures
        for ($i = 0; $i < 10; $i++) {
            $cb->recordFailure();
        }

        // Circuit should be open after threshold
        $this->assertSame('open', $cb->getState());
        $this->assertFalse($cb->isAvailable());
    }

    public function testHalfOpenTrialLimitPreventsConcurrentStampede(): void
    {
        $cb = new CircuitBreaker(
            service: 'test_trial',
            failureThreshold: 3,
            timeout: 0, // Immediate half-open
            successThreshold: 2,
            maxTrials: 3
        );

        // Trigger circuit open
        $cb->recordFailure();
        $cb->recordFailure();
        $cb->recordFailure();

        $this->assertSame('open', $cb->getState());

        // Transition to half-open immediately (timeout=0)
        sleep(1);

        // First 3 requests should be allowed
        $this->assertTrue($cb->isAvailable());
        $this->assertTrue($cb->isAvailable());
        $this->assertTrue($cb->isAvailable());

        // 4th request should be blocked (maxTrials=3)
        $this->assertFalse($cb->isAvailable());
    }

    public function testSuccessCounterResetsOnFailureInHalfOpen(): void
    {
        $cb = new CircuitBreaker(
            service: 'test_reset',
            failureThreshold: 2,
            timeout: 0,
            successThreshold: 2
        );

        // Trigger open
        $cb->recordFailure();
        $cb->recordFailure();

        sleep(1); // Transition to half-open

        // Record 1 success
        $cb->recordSuccess();
        $this->assertSame('half_open', $cb->getState());

        // Record 1 failure - should reopen circuit
        $cb->recordFailure();
        $this->assertSame('open', $cb->getState());

        // Successes should be reset
        sleep(1); // Transition to half-open again
        $cb->recordSuccess();
        $this->assertSame('half_open', $cb->getState());
    }

    public function testResetClearsAllState(): void
    {
        $cb = new CircuitBreaker('test_full_reset', failureThreshold: 3);

        // Build up state
        $cb->recordFailure();
        $cb->recordFailure();
        $cb->recordFailure();

        $this->assertSame('open', $cb->getState());

        // Reset
        $cb->reset();

        // Should be closed with clean state
        $this->assertSame('closed', $cb->getState());
        $this->assertTrue($cb->isAvailable());

        // Should require threshold failures again
        $cb->recordFailure();
        $cb->recordFailure();
        $this->assertSame('closed', $cb->getState());
    }

    public function testSuccessInClosedStateResetsFailureCount(): void
    {
        $cb = new CircuitBreaker('test_closed_success', failureThreshold: 5);

        // Record 4 failures (under threshold)
        for ($i = 0; $i < 4; $i++) {
            $cb->recordFailure();
        }

        $this->assertSame('closed', $cb->getState());

        // Record success - should reset failure count
        $cb->recordSuccess();

        // Should now require 5 more failures to open
        for ($i = 0; $i < 4; $i++) {
            $cb->recordFailure();
        }

        $this->assertSame('closed', $cb->getState());

        // 5th failure should open
        $cb->recordFailure();
        $this->assertSame('open', $cb->getState());
    }

    public function testTransitionFromClosedToOpenAtExactThreshold(): void
    {
        $cb = new CircuitBreaker('test_exact_threshold', failureThreshold: 3);

        $cb->recordFailure();
        $this->assertSame('closed', $cb->getState());

        $cb->recordFailure();
        $this->assertSame('closed', $cb->getState());

        $cb->recordFailure();
        $this->assertSame('open', $cb->getState());
    }

    public function testHalfOpenToClosedRequiresExactSuccessThreshold(): void
    {
        $cb = new CircuitBreaker(
            service: 'test_success_threshold',
            failureThreshold: 2,
            timeout: 0,
            successThreshold: 3
        );

        // Open circuit
        $cb->recordFailure();
        $cb->recordFailure();

        sleep(1); // Half-open

        // 2 successes - should stay half-open
        $cb->recordSuccess();
        $cb->recordSuccess();
        $this->assertSame('half_open', $cb->getState());

        // 3rd success - should close
        $cb->recordSuccess();
        $this->assertSame('closed', $cb->getState());
    }

    public function testMultipleServicesIndependent(): void
    {
        $cb1 = new CircuitBreaker('service_a', failureThreshold: 2);
        $cb2 = new CircuitBreaker('service_b', failureThreshold: 2);

        // Open service_a
        $cb1->recordFailure();
        $cb1->recordFailure();

        // service_b should remain closed
        $this->assertSame('open', $cb1->getState());
        $this->assertSame('closed', $cb2->getState());

        $this->assertFalse($cb1->isAvailable());
        $this->assertTrue($cb2->isAvailable());
    }
}
