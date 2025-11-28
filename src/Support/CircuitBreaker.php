<?php

/**
 * Circuit breaker for preventing cascading failures.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Implements circuit breaker pattern to prevent repeated calls to failing services.
 *
 * States:
 * - Closed: Normal operation, requests pass through
 * - Open: Service is failing, requests are blocked
 * - Half-Open: Testing if service recovered
 */
final class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    private readonly string $cacheKey;
    private readonly string $failureCountKey;
    private readonly string $lastFailureTimeKey;
    private readonly string $trialCountKey;
    private readonly string $successCountKey;

    /**
     * Create a new circuit breaker.
     *
     * @param string $service          Service identifier (e.g., 'twilio_sms')
     * @param int    $failureThreshold Number of failures before opening circuit
     * @param int    $timeout          Seconds to wait before half-open attempt
     * @param int    $successThreshold Successes needed to close from half-open
     * @param int    $maxTrials        Maximum concurrent trials in half-open state
     */
    public function __construct(
        private readonly string $service,
        private readonly int $failureThreshold = 5,
        private readonly int $timeout = 60,
        private readonly int $successThreshold = 2,
        private readonly int $maxTrials = 3,
    ) {
        $this->cacheKey = "circuit_breaker:{$service}:state";
        $this->failureCountKey = "circuit_breaker:{$service}:failures";
        $this->lastFailureTimeKey = "circuit_breaker:{$service}:last_failure";
        $this->trialCountKey = "circuit_breaker:{$service}:trials";
        $this->successCountKey = "circuit_breaker:{$service}:successes";
    }

    /**
     * Check if requests are allowed through the circuit.
     *
     * @return bool True if requests can proceed
     */
    public function isAvailable(): bool
    {
        $state = $this->getState();

        if ($state === self::STATE_CLOSED) {
            return true;
        }

        if ($state === self::STATE_OPEN) {
            // Check if timeout has elapsed
            $lastFailure = Cache::get($this->lastFailureTimeKey, 0);
            $elapsed = time() - $lastFailure;

            if ($elapsed >= $this->timeout) {
                $this->transitionTo(self::STATE_HALF_OPEN);
                Cache::forget($this->trialCountKey);
                return true;
            }

            return false;
        }

        // Half-open: allow limited trial requests to prevent stampeding
        if ($state === self::STATE_HALF_OPEN) {
            $trials = Cache::get($this->trialCountKey, 0);
            if ($trials >= $this->maxTrials) {
                return false;
            }
            Cache::increment($this->trialCountKey, 1);
            Cache::put($this->trialCountKey, $trials + 1, 300);
            return true;
        }

        return true;
    }

    /**
     * Record a successful operation.
     *
     * @return void
     */
    public function recordSuccess(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            $successCount = Cache::increment($this->successCountKey, 1);
            Cache::put($this->successCountKey, $successCount, 300);

            if ($successCount >= $this->successThreshold) {
                $this->transitionTo(self::STATE_CLOSED);
                Cache::forget($this->successCountKey);
                Cache::forget($this->trialCountKey);
            }
        }

        // Reset failure count on success in closed state
        if ($state === self::STATE_CLOSED) {
            Cache::forget($this->failureCountKey);
        }
    }

    /**
     * Record a failed operation.
     *
     * @return void
     */
    public function recordFailure(): void
    {
        $state = $this->getState();

        // Use 24-hour TTL for failure timestamp (longer than typical timeout windows)
        Cache::put($this->lastFailureTimeKey, time(), 86400);

        if ($state === self::STATE_HALF_OPEN) {
            // Failure in half-open immediately reopens circuit
            $this->transitionTo(self::STATE_OPEN);
            Cache::forget($this->successCountKey);
            Cache::forget($this->trialCountKey);
            return;
        }

        if ($state === self::STATE_CLOSED) {
            $failures = Cache::increment($this->failureCountKey, 1);
            Cache::put($this->failureCountKey, $failures, 3600);

            if ($failures >= $this->failureThreshold) {
                $this->transitionTo(self::STATE_OPEN);
                Logger::warning('bird-flock.circuit_breaker.opened', [
                    'service' => $this->service,
                    'failures' => $failures,
                    'threshold' => $this->failureThreshold,
                ]);
            }
        }
    }

    /**
     * Get the current state of the circuit breaker.
     *
     * @return string Current state (closed|open|half_open)
     */
    public function getState(): string
    {
        return Cache::get($this->cacheKey, self::STATE_CLOSED) ?? self::STATE_CLOSED;
    }

    /**
     * Reset the circuit breaker to closed state.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->transitionTo(self::STATE_CLOSED);
        Cache::forget($this->failureCountKey);
        Cache::forget($this->lastFailureTimeKey);
        Cache::forget($this->successCountKey);
        Cache::forget($this->trialCountKey);

        Logger::info('bird-flock.circuit_breaker.reset', [
            'service' => $this->service,
        ]);
    }

    /**
     * Transition to a new state.
     *
     * @param string $newState Target state
     *
     * @return void
     */
    private function transitionTo(string $newState): void
    {
        $oldState = $this->getState();

        if ($oldState !== $newState) {
            // Use 24-hour TTL to prevent indefinite cache growth while maintaining state
            Cache::put($this->cacheKey, $newState, 86400);

            Logger::info('bird-flock.circuit_breaker.state_transition', [
                'service' => $this->service,
                'from' => $oldState,
                'to' => $newState,
            ]);
        }
    }
}
