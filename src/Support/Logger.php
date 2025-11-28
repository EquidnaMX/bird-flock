<?php

/**
 * Structured logging helper for Bird Flock package.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Support;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Helper for Bird Flock structured logging.
 */
final class Logger
{
    /**
     * Determine if logging is enabled.
     */
    public static function enabled(): bool
    {
        return (bool) config('bird-flock.logging.enabled', true);
    }

    /**
     * Retrieve the configured logger instance.
     */
    public static function channel(): LoggerInterface
    {
        if (!self::enabled()) {
            return new NullLogger();
        }

        return app('bird-flock.logger');
    }

    /**
     * Log an informational message.
     *
     * @param  string               $message Message to log.
     * @param  array<string, mixed> $context Additional context data.
     * @return void
     */
    public static function info(string $message, array $context = []): void
    {
        if (!self::enabled()) {
            return;
        }

        self::channel()->info($message, $context);
    }

    /**
     * Log a warning message.
     *
     * @param  string               $message Message to log.
     * @param  array<string, mixed> $context Additional context data.
     * @return void
     */
    public static function warning(string $message, array $context = []): void
    {
        if (!self::enabled()) {
            return;
        }

        self::channel()->warning($message, $context);
    }

    /**
     * Log an error message.
     *
     * @param  string               $message Message to log.
     * @param  array<string, mixed> $context Additional context data.
     * @return void
     */
    public static function error(string $message, array $context = []): void
    {
        if (!self::enabled()) {
            return;
        }

        self::channel()->error($message, $context);
    }
}
