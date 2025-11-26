<?php

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

    public static function info(string $message, array $context = []): void
    {
        if (!self::enabled()) {
            return;
        }

        self::channel()->info($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        if (!self::enabled()) {
            return;
        }

        self::channel()->warning($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        if (!self::enabled()) {
            return;
        }

        self::channel()->error($message, $context);
    }
}
