<?php

/**
 * Metrics collector contract.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Contracts
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Contracts;

/**
 * Contract for incrementing metrics counters.
 *
 * Implementations can forward to Prometheus, StatsD, OTEL, or other backends.
 */
interface MetricsCollectorInterface
{
    /**
     * Increment a named metric counter.
     *
     * @param  string               $metric Metric name (dot-separated)
     * @param  int                  $by     Amount to increment by
     * @param  array<string, mixed> $tags   Optional tags/labels
     * @return void
     */
    public function increment(
        string $metric,
        int $by = 1,
        array $tags = [],
    ): void;
}
