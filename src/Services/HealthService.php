<?php

/**
 * Health service for programmatic health data access.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Services
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Services;

use Equidna\BirdFlock\Support\CircuitBreaker;
use Equidna\BirdFlock\Support\DatabaseConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Provides health status data for host application dashboards.
 *
 * This service exposes package health information programmatically,
 * allowing host applications to generate centralized dashboards
 * without making HTTP requests to the health endpoints.
 */
final class HealthService
{
    /**
     * Get complete health status.
     *
     * Returns structured health data suitable for dashboard display.
     *
     * @return array{
     *     status: string,
     *     version: string,
     *     checks: array<string, array{healthy: bool, message: string}>,
     *     metrics: array<string, mixed>,
     *     timestamp: string
     * }
     */
    public function getHealthStatus(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'queue' => $this->checkQueue(),
            'circuits' => $this->checkCircuits(),
        ];

        foreach ($this->getConfiguredVendors() as $vendor) {
            $checks[$vendor] = match ($vendor) {
                'twilio' => $this->checkTwilio(),
                'vonage' => $this->checkVonage(),
                'sendgrid' => $this->checkSendgrid(),
                'mailgun' => $this->checkMailgun(),
                'labsmobile' => $this->checkLabsmobile(),
                default => $this->checkCustomSender($vendor),
            };
        }

        $healthy = ! in_array(false, array_column($checks, 'healthy'), true);

        $metrics = [
            'dlq' => $this->getDlqMetrics(),
            'queue' => $this->getQueueMetrics(),
            'performance' => $this->getPerformanceMetrics(),
        ];

        $version = $this->getPackageVersion();

        return [
            'status' => $healthy ? 'healthy' : 'degraded',
            'version' => $version,
            'checks' => $checks,
            'metrics' => $metrics,
            'timestamp' => now()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * Get circuit breaker states for all providers.
     *
     * @return array{
     *     status: string,
     *     circuits: array<string, array<string, mixed>>,
     *     timestamp: string
     * }
     */
    public function getCircuitBreakerStatus(): array
    {
        $services = [
            'twilio_sms',
            'twilio_whatsapp',
            'sendgrid_email',
            'vonage_sms',
            'mailgun_email',
            'labsmobile_sms',
        ];

        $circuits = [];

        foreach ($services as $service) {
            $circuits[$service] = $this->getCircuitBreakerDetails($service);
        }

        $healthySummary = array_reduce($circuits, function ($carry, $circuit) {
            return $carry && ($circuit['state'] === 'closed');
        }, true);

        return [
            'status' => $healthySummary ? 'healthy' : 'degraded',
            'circuits' => $circuits,
            'timestamp' => now()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * Get detailed circuit breaker information for a service.
     *
     * @param string $service Service identifier
     *
     * @return array<string, mixed> Circuit breaker details
     */
    private function getCircuitBreakerDetails(string $service): array
    {
        $stateKey = "circuit_breaker:{$service}:state";
        $failureCountKey = "circuit_breaker:{$service}:failures";
        $lastFailureTimeKey = "circuit_breaker:{$service}:last_failure";
        $successCountKey = "circuit_breaker:{$service}:successes";
        $trialCountKey = "circuit_breaker:{$service}:trials";

        $state = Cache::get($stateKey, 'closed');
        $failureCount = Cache::get($failureCountKey, 0);
        $lastFailureTime = Cache::get($lastFailureTimeKey);
        $successCount = Cache::get($successCountKey, 0);
        $trialCount = Cache::get($trialCountKey, 0);

        $config = config('bird-flock.circuit_breaker', [
            'failure_threshold' => 5,
            'timeout' => 60,
            'success_threshold' => 2,
        ]);

        $details = [
            'state' => $state,
            'healthy' => $state === 'closed',
            'failure_count' => $failureCount,
            'success_count' => $successCount,
            'trial_count' => $trialCount,
            'configuration' => [
                'failure_threshold' => $config['failure_threshold'],
                'timeout_seconds' => $config['timeout'],
                'success_threshold' => $config['success_threshold'],
            ],
        ];

        if ($lastFailureTime) {
            $elapsed = time() - $lastFailureTime;
            $details['last_failure_at'] = date('Y-m-d\TH:i:s\Z', $lastFailureTime);
            $details['seconds_since_failure'] = $elapsed;

            if ($state === 'open') {
                $remainingTimeout = max(0, $config['timeout'] - $elapsed);
                $details['recovery_in_seconds'] = $remainingTimeout;
                $details['estimated_recovery_at'] = date(
                    'Y-m-d\TH:i:s\Z',
                    $lastFailureTime + $config['timeout']
                );
            }
        }

        if ($state === 'half_open') {
            $details['status_message'] = 'Testing recovery - allowing trial requests';
            $maxTrials = 3; // Default from CircuitBreaker::$maxTrials
            $details['trials_remaining'] = max(0, $maxTrials - $trialCount);
        } elseif ($state === 'open') {
            $details['status_message'] = 'Circuit open - blocking requests to protect service';
        } else {
            $details['status_message'] = 'Circuit closed - normal operation';
        }

        return $details;
    }

    /**
     * Get package version from composer.lock.
     *
     * @return string Package version or 'unknown'
     */
    private function getPackageVersion(): string
    {
        try {
            $lockPath = function_exists('base_path')
                ? base_path('composer.lock')
                : dirname(__DIR__, 2) . '/composer.lock';
        } catch (Throwable) {
            $lockPath = dirname(__DIR__, 2) . '/composer.lock';
        }

        if (! file_exists($lockPath)) {
            return 'unknown';
        }

        try {
            $lock = json_decode(file_get_contents($lockPath), true);
            $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);

            foreach ($packages as $package) {
                if ($package['name'] === 'equidna/bird-flock') {
                    return $package['version'] ?? 'dev';
                }
            }
        } catch (Throwable) {
            //
        }

        return 'dev';
    }

    /**
     * Check database connectivity.
     *
     * @return array{healthy: bool, message: string}
     */
    private function checkDatabase(): array
    {
        try {
            DatabaseConnection::connection()->getPdo();
            $tableName = config('bird-flock.tables.outbound_messages');
            $exists = DatabaseConnection::schema()->hasTable($tableName);

            return [
                'healthy' => $exists,
                'message' => $exists
                    ? 'Database connected and table exists'
                    : "Table {$tableName} not found",
            ];
        } catch (Throwable $e) {
            return [
                'healthy' => false,
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check Twilio configuration.
     *
     * @return array{healthy: bool, message: string}
     */
    private function checkTwilio(): array
    {
        $accountSid = config('bird-flock-twilio.account_sid');
        $authToken = config('bird-flock-twilio.auth_token');

        if (! $accountSid || ! $authToken) {
            return [
                'healthy' => false,
                'message' => 'Twilio credentials not configured',
            ];
        }

        $messagingService = config('bird-flock-twilio.messaging_service_sid');
        $fromSms = config('bird-flock-twilio.from_sms');

        if (! $messagingService && ! $fromSms) {
            return [
                'healthy' => false,
                'message' => 'No Twilio sender configured (need messaging_service_sid or from_sms)',
            ];
        }

        return [
            'healthy' => true,
            'message' => 'Twilio configured',
        ];
    }

    /**
     * Check Vonage configuration.
     *
     * @return array{healthy: bool, message: string}
     */
    private function checkVonage(): array
    {
        $apiKey = config('bird-flock-vonage.api_key');
        $apiSecret = config('bird-flock-vonage.api_secret');
        $fromSms = config('bird-flock-vonage.from_sms');

        if (! $apiKey || ! $apiSecret) {
            return [
                'healthy' => false,
                'message' => 'Vonage credentials not configured',
            ];
        }

        if (! $fromSms) {
            return [
                'healthy' => false,
                'message' => 'Vonage from_sms not configured',
            ];
        }

        return [
            'healthy' => true,
            'message' => 'Vonage configured',
        ];
    }

    /**
     * Check SendGrid configuration.
     *
     * @return array{healthy: bool, message: string}
     */
    private function checkSendgrid(): array
    {
        $apiKey = config('bird-flock-sendgrid.api_key');
        $fromEmail = config('bird-flock-sendgrid.from_email');

        if (! $apiKey) {
            return [
                'healthy' => false,
                'message' => 'SendGrid API key not configured',
            ];
        }

        if (! $fromEmail) {
            return [
                'healthy' => false,
                'message' => 'SendGrid from_email not configured',
            ];
        }

        return [
            'healthy' => true,
            'message' => 'SendGrid configured',
        ];
    }

    /**
     * Check Mailgun configuration.
     *
     * @return array{healthy: bool, message: string}
     */
    private function checkMailgun(): array
    {
        $apiKey = config('bird-flock-mailgun.api_key');
        $domain = config('bird-flock-mailgun.domain');
        $fromEmail = config('bird-flock-mailgun.from_email');

        if (! $apiKey) {
            return [
                'healthy' => false,
                'message' => 'Mailgun API key not configured',
            ];
        }

        if (! $domain) {
            return [
                'healthy' => false,
                'message' => 'Mailgun domain not configured',
            ];
        }

        if (! $fromEmail) {
            return [
                'healthy' => false,
                'message' => 'Mailgun from_email not configured',
            ];
        }

        return [
            'healthy' => true,
            'message' => 'Mailgun configured',
        ];
    }

    /**
     * Check LabsMobile configuration.
     *
     * @return array{healthy: bool, message: string}
     */
    private function checkLabsmobile(): array
    {
        $username = config('bird-flock-labsmobile.username');
        $token = config('bird-flock-labsmobile.token');

        if (! $username || ! $token) {
            return [
                'healthy' => false,
                'message' => 'LabsMobile credentials not configured',
            ];
        }

        return [
            'healthy' => true,
            'message' => 'LabsMobile configured',
        ];
    }

    /**
     * @return array{healthy: bool, message: string}
     */
    private function checkCustomSender(string $vendor): array
    {
        if (! $this->isCustomSenderConfigured($vendor)) {
            return [
                'healthy' => false,
                'message' => "Unsupported vendor configured: {$vendor}",
            ];
        }

        return [
            'healthy' => true,
            'message' => "Custom sender configured for vendor: {$vendor}",
        ];
    }

    /**
     * Check queue configuration.
     *
     * @return array{healthy: bool, message: string}
     */
    private function checkQueue(): array
    {
        $queue = config('bird-flock.default_queue');

        if (! $queue) {
            return [
                'healthy' => false,
                'message' => 'Default queue not configured',
            ];
        }

        return [
            'healthy' => true,
            'message' => "Queue configured: {$queue}",
        ];
    }

    /**
     * Check circuit breaker states for providers.
     *
     * @return array{healthy: bool, message: string, states: array<string,string>}
     */
    private function checkCircuits(): array
    {
        $services = [
            'twilio_sms' => new CircuitBreaker('twilio_sms'),
            'twilio_whatsapp' => new CircuitBreaker('twilio_whatsapp'),
            'sendgrid_email' => new CircuitBreaker('sendgrid_email'),
            'vonage_sms' => new CircuitBreaker('vonage_sms'),
            'mailgun_email' => new CircuitBreaker('mailgun_email'),
            'labsmobile_sms' => new CircuitBreaker('labsmobile_sms'),
        ];

        $states = [];
        $allHealthy = true;
        foreach ($services as $name => $cb) {
            $state = $cb->getState();
            $states[$name] = $state;
            if ($state !== 'closed') {
                $allHealthy = false;
            }
        }

        return [
            'healthy' => $allHealthy,
            'message' => $allHealthy ? 'All circuits closed' : 'One or more circuits not closed',
            'states' => $states,
        ];
    }

    /**
     * @return list<string>
     */
    private function getConfiguredVendors(): array
    {
        $channels = config('bird-flock.channels', []);
        $vendors = [];

        foreach ($channels as $channel) {
            $configured = [];

            if (isset($channel['senders']) && is_array($channel['senders'])) {
                $configured = array_keys($channel['senders']);
            } elseif (isset($channel['vendors']) && is_array($channel['vendors'])) {
                $configured = $channel['vendors'];
            }

            foreach ($configured as $vendor) {
                if (is_string($vendor) && trim($vendor) !== '') {
                    $vendors[] = strtolower(trim($vendor));
                }
            }
        }

        return array_values(array_unique($vendors));
    }

    private function isCustomSenderConfigured(string $vendor): bool
    {
        $channels = config('bird-flock.channels', []);

        foreach ($channels as $channel) {
            if (! isset($channel['senders']) || ! is_array($channel['senders'])) {
                continue;
            }

            foreach (array_keys($channel['senders']) as $configuredVendor) {
                if (is_string($configuredVendor) && strtolower(trim($configuredVendor)) === $vendor) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get DLQ metrics.
     *
     * @return array{count: int, by_channel: array<string,int>}
     */
    private function getDlqMetrics(): array
    {
        try {
            $tableName = config('bird-flock.dead_letter.table', 'bird_flock_dead_letters');

            if (! DatabaseConnection::schema()->hasTable($tableName)) {
                return ['count' => 0, 'by_channel' => []];
            }

            $total = DatabaseConnection::table($tableName)->count();
            $byChannel = DatabaseConnection::table($tableName)
                ->select('channel', DatabaseConnection::raw('count(*) as count'))
                ->groupBy('channel')
                ->pluck('count', 'channel')
                ->toArray();

            return [
                'count' => $total,
                'by_channel' => $byChannel,
            ];
        } catch (Throwable) {
            return ['count' => 0, 'by_channel' => []];
        }
    }

    /**
     * Get queue metrics.
     *
     * @return array{pending: int, queue_name: string}
     */
    private function getQueueMetrics(): array
    {
        try {
            $queueName = config('bird-flock.default_queue', 'default');
            $connection = config('queue.default');
            $driver = config("queue.connections.{$connection}.driver");

            $pending = 0;

            if ($driver === 'database') {
                $jobsTable = config('queue.connections.database.table', 'jobs');
                $pending = DB::table($jobsTable)
                    ->where('queue', $queueName)
                    ->count();
            }

            return [
                'pending' => $pending,
                'queue_name' => $queueName,
            ];
        } catch (Throwable) {
            return ['pending' => 0, 'queue_name' => config('bird-flock.default_queue', 'default')];
        }
    }

    /**
     * Get performance metrics from recent logs.
     *
     * @return array{avg_sender_duration_ms: float|null, recent_samples: int}
     */
    private function getPerformanceMetrics(): array
    {
        // This is a placeholder - in production, query your log aggregator or cache
        // For now, return null to indicate metrics not available
        return [
            'avg_sender_duration_ms' => null,
            'recent_samples' => 0,
        ];
    }
}
