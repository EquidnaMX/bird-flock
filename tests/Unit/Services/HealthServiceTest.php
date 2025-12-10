<?php

/**
 * Unit tests for HealthService.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Unit\Services
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Unit\Services;

use Equidna\BirdFlock\Services\HealthService;
use Equidna\BirdFlock\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

final class HealthServiceTest extends TestCase
{
    private HealthService $healthService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->healthService = new HealthService();
    }

    public function testGetHealthStatusReturnsHealthyWhenAllServicesUp(): void
    {
        // Mock database connection
        DB::shouldReceive('connection->getPdo')->andReturn(true);
        DB::shouldReceive('getSchemaBuilder->hasTable')->andReturn(true);
        DB::shouldReceive('table->count')->andReturn(0);
        DB::shouldReceive('table->select->groupBy->pluck->toArray')->andReturn([]);

        config([
            'bird-flock.twilio.account_sid' => 'test_sid',
            'bird-flock.twilio.auth_token' => 'test_token',
            'bird-flock.twilio.from_sms' => '+1234567890',
            'bird-flock.sendgrid.api_key' => 'test_key',
            'bird-flock.sendgrid.from_email' => 'test@example.com',
            'bird-flock.default_queue' => 'default',
        ]);

        // Mock circuit breakers
        Cache::shouldReceive('get')
            ->with('circuit_breaker:twilio_sms:state', 'closed')
            ->andReturn('closed');
        Cache::shouldReceive('get')
            ->with('circuit_breaker:twilio_whatsapp:state', 'closed')
            ->andReturn('closed');
        Cache::shouldReceive('get')
            ->with('circuit_breaker:sendgrid_email:state', 'closed')
            ->andReturn('closed');

        $health = $this->healthService->getHealthStatus();

        $this->assertSame('healthy', $health['status']);
        $this->assertArrayHasKey('checks', $health);
        $this->assertArrayHasKey('metrics', $health);
        $this->assertArrayHasKey('timestamp', $health);
        $this->assertTrue($health['checks']['database']['healthy']);
        $this->assertTrue($health['checks']['twilio']['healthy']);
        $this->assertTrue($health['checks']['sendgrid']['healthy']);
    }

    public function testGetHealthStatusReturnsDegradedWhenDatabaseFails(): void
    {
        // Mock database failure
        DB::shouldReceive('connection->getPdo')->andThrow(new \Exception('Connection failed'));

        config([
            'bird-flock.twilio.account_sid' => 'test_sid',
            'bird-flock.twilio.auth_token' => 'test_token',
            'bird-flock.twilio.from_sms' => '+1234567890',
            'bird-flock.sendgrid.api_key' => 'test_key',
            'bird-flock.sendgrid.from_email' => 'test@example.com',
            'bird-flock.default_queue' => 'default',
        ]);

        Cache::shouldReceive('get')->andReturn('closed');

        $health = $this->healthService->getHealthStatus();

        $this->assertSame('degraded', $health['status']);
        $this->assertFalse($health['checks']['database']['healthy']);
        $this->assertStringContainsString('Connection failed', $health['checks']['database']['message']);
    }

    public function testGetCircuitBreakerStatusReturnsAllCircuits(): void
    {
        // Mock all cache gets for circuit breaker data
        Cache::shouldReceive('get')->andReturnUsing(function ($key, $default = null) {
            if (str_contains($key, ':state')) {
                return 'closed';
            }
            if (str_contains($key, ':failures') || str_contains($key, ':successes') || str_contains($key, ':trials')) {
                return 0;
            }
            return $default;
        });

        $circuits = $this->healthService->getCircuitBreakerStatus();

        $this->assertSame('healthy', $circuits['status']);
        $this->assertArrayHasKey('circuits', $circuits);
        $this->assertArrayHasKey('timestamp', $circuits);
        
        // Check all expected services are present
        $this->assertArrayHasKey('twilio_sms', $circuits['circuits']);
        $this->assertArrayHasKey('twilio_whatsapp', $circuits['circuits']);
        $this->assertArrayHasKey('sendgrid_email', $circuits['circuits']);
        $this->assertArrayHasKey('vonage_sms', $circuits['circuits']);
        $this->assertArrayHasKey('mailgun_email', $circuits['circuits']);
        
        // Check circuit details
        $this->assertSame('closed', $circuits['circuits']['twilio_sms']['state']);
        $this->assertTrue($circuits['circuits']['twilio_sms']['healthy']);
    }

    public function testGetCircuitBreakerStatusDetectsOpenCircuit(): void
    {
        // Mock circuit breaker with one open circuit
        Cache::shouldReceive('get')->andReturnUsing(function ($key, $default = null) {
            if ($key === 'circuit_breaker:twilio_sms:state') {
                return 'open';
            }
            if (str_contains($key, ':state')) {
                return 'closed';
            }
            if (str_contains($key, ':failures')) {
                return 5;
            }
            if ($key === 'circuit_breaker:twilio_sms:last_failure') {
                return time() - 30; // 30 seconds ago
            }
            if (str_contains($key, ':successes') || str_contains($key, ':trials')) {
                return 0;
            }
            return $default;
        });

        $circuits = $this->healthService->getCircuitBreakerStatus();

        $this->assertSame('degraded', $circuits['status']);
        $this->assertSame('open', $circuits['circuits']['twilio_sms']['state']);
        $this->assertFalse($circuits['circuits']['twilio_sms']['healthy']);
        $this->assertArrayHasKey('last_failure_at', $circuits['circuits']['twilio_sms']);
        $this->assertArrayHasKey('recovery_in_seconds', $circuits['circuits']['twilio_sms']);
    }
}
