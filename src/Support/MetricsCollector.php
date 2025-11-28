<?php

/**
 * Default metrics collector implementation.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Support;

use Equidna\BirdFlock\Contracts\MetricsCollectorInterface;

/**
 * Default no-op metrics collector that logs increments for visibility.
 *
 * Provides a fallback implementation that emits metrics as log entries.
 */
final class MetricsCollector implements MetricsCollectorInterface
{
    /**
     * Increment a metric counter by logging the event.
     *
     * @param  string               $metric Metric name (dot-separated)
     * @param  int                  $by     Amount to increment
     * @param  array<string, mixed> $tags   Optional tags/labels
     * @return void
     */
    public function increment(
        string $metric,
        int $by = 1,
        array $tags = [],
    ): void {
        Logger::info(
            'bird-flock.metrics.increment',
            [
                'metric' => $metric,
                'by'     => $by,
                'tags'   => $tags,
            ],
        );
    }
}
