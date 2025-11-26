<?php

/**
 * Backoff strategy for retry logic with jitter.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Support;

/**
 * Calculates backoff delays for retry operations.
 */
final class BackoffStrategy
{
    /**
     * Calculate backoff delay with decorrelated jitter.
     *
     * @param int $attempt  Attempt number (0-based)
     * @param int $baseMs   Base delay in milliseconds
     * @param int $maxMs    Maximum delay in milliseconds
     * @param int $previousMs Previous delay in milliseconds
     *
     * @return int Delay in milliseconds
     */
    public static function decorrelatedJitter(
        int $attempt,
        int $baseMs = 1000,
        int $maxMs = 60000,
        int $previousMs = 0
    ): int {
        if ($attempt === 0) {
            return $baseMs;
        }

        $temp = min($maxMs, $baseMs * (3 ** $attempt));
        $sleep = $previousMs * 3;
        $sleep = (int) min($maxMs, random_int($baseMs, $sleep));

        return min($maxMs, $sleep);
    }

    /**
     * Calculate exponential backoff with jitter.
     *
     * @param int $attempt Attempt number (0-based)
     * @param int $baseMs  Base delay in milliseconds
     * @param int $maxMs   Maximum delay in milliseconds
     *
     * @return int Delay in milliseconds
     */
    public static function exponentialWithJitter(
        int $attempt,
        int $baseMs = 1000,
        int $maxMs = 60000
    ): int {
        $temp = min($maxMs, $baseMs * (2 ** $attempt));
        $jitter = random_int(0, (int) ($temp / 2));

        return min($maxMs, $temp + $jitter);
    }
}
