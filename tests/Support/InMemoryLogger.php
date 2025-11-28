<?php

/**
 * In-memory PSR-3 logger for testing.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Support;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final class InMemoryLogger implements LoggerInterface
{
    /** @var array<int, array{level: string, message: string, context: array}> */
    public array $records = [];

    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function has(string $message): bool
    {
        foreach ($this->records as $r) {
            if ($r['message'] === $message) {
                return true;
            }
        }

        return false;
    }

    public function messagesByLevel(string $level): array
    {
        $out = [];
        foreach ($this->records as $r) {
            if ($r['level'] === $level) {
                $out[] = $r;
            }
        }

        return $out;
    }
}
