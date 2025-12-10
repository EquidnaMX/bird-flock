<?php

/**
 * Unit tests for HealthCheckController.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Unit\Http\Controllers
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Unit\Http\Controllers;

use Equidna\BirdFlock\Http\Controllers\HealthCheckController;
use Equidna\BirdFlock\Services\HealthService;
use Equidna\BirdFlock\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Mockery;

final class HealthCheckControllerTest extends TestCase
{
    public function testHealthCheckReturnsHealthyWhenAllServicesUp(): void
    {
        $healthService = Mockery::mock(HealthService::class);
        $healthService->shouldReceive('getHealthStatus')->andReturn([
            'status' => 'healthy',
            'version' => '1.0.0',
            'checks' => [
                'database' => ['healthy' => true, 'message' => 'OK'],
            ],
            'metrics' => [],
            'timestamp' => '2025-12-10T00:00:00Z',
        ]);

        $controller = new HealthCheckController($healthService);
        $response = $controller->check();

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertSame('healthy', $data['status']);
        $this->assertArrayHasKey('checks', $data);
        $this->assertTrue($data['checks']['database']['healthy']);
    }

    public function testHealthCheckReturnsDegradedWhenDatabaseFails(): void
    {
        $healthService = Mockery::mock(HealthService::class);
        $healthService->shouldReceive('getHealthStatus')->andReturn([
            'status' => 'degraded',
            'version' => '1.0.0',
            'checks' => [
                'database' => ['healthy' => false, 'message' => 'Connection failed'],
            ],
            'metrics' => [],
            'timestamp' => '2025-12-10T00:00:00Z',
        ]);

        $controller = new HealthCheckController($healthService);
        $response = $controller->check();

        $this->assertSame(503, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertSame('degraded', $data['status']);
        $this->assertFalse($data['checks']['database']['healthy']);
    }

    public function testHealthCheckIncludesCircuitBreakerStates(): void
    {
        $healthService = Mockery::mock(HealthService::class);
        $healthService->shouldReceive('getHealthStatus')->andReturn([
            'status' => 'healthy',
            'version' => '1.0.0',
            'checks' => [
                'circuits' => [
                    'healthy' => true,
                    'message' => 'All circuits closed',
                    'states' => [
                        'twilio_sms' => 'closed',
                        'twilio_whatsapp' => 'closed',
                        'sendgrid_email' => 'closed',
                    ],
                ],
            ],
            'metrics' => [],
            'timestamp' => '2025-12-10T00:00:00Z',
        ]);

        $controller = new HealthCheckController($healthService);
        $response = $controller->check();

        $data = $response->getData(true);
        $this->assertArrayHasKey('circuits', $data['checks']);
        $this->assertTrue($data['checks']['circuits']['healthy']);
        $this->assertArrayHasKey('states', $data['checks']['circuits']);
        $this->assertSame('closed', $data['checks']['circuits']['states']['twilio_sms']);
    }

    public function testHealthCheckDetectsOpenCircuit(): void
    {
        $healthService = Mockery::mock(HealthService::class);
        $healthService->shouldReceive('getHealthStatus')->andReturn([
            'status' => 'degraded',
            'version' => '1.0.0',
            'checks' => [
                'circuits' => [
                    'healthy' => false,
                    'message' => 'One or more circuits not closed',
                    'states' => [
                        'twilio_sms' => 'open',
                        'twilio_whatsapp' => 'closed',
                        'sendgrid_email' => 'closed',
                    ],
                ],
            ],
            'metrics' => [],
            'timestamp' => '2025-12-10T00:00:00Z',
        ]);

        $controller = new HealthCheckController($healthService);
        $response = $controller->check();

        $this->assertSame(503, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertSame('degraded', $data['status']);
        $this->assertFalse($data['checks']['circuits']['healthy']);
        $this->assertSame('open', $data['checks']['circuits']['states']['twilio_sms']);
    }

    public function testHealthCheckValidatesTwilioConfig(): void
    {
        $healthService = Mockery::mock(HealthService::class);
        $healthService->shouldReceive('getHealthStatus')->andReturn([
            'status' => 'degraded',
            'version' => '1.0.0',
            'checks' => [
                'twilio' => [
                    'healthy' => false,
                    'message' => 'Twilio credentials not configured',
                ],
            ],
            'metrics' => [],
            'timestamp' => '2025-12-10T00:00:00Z',
        ]);

        $controller = new HealthCheckController($healthService);
        $response = $controller->check();

        $data = $response->getData(true);
        $this->assertFalse($data['checks']['twilio']['healthy']);
        $this->assertStringContainsString('credentials', $data['checks']['twilio']['message']);
    }

    public function testHealthCheckValidatesSendgridConfig(): void
    {
        $healthService = Mockery::mock(HealthService::class);
        $healthService->shouldReceive('getHealthStatus')->andReturn([
            'status' => 'degraded',
            'version' => '1.0.0',
            'checks' => [
                'sendgrid' => [
                    'healthy' => false,
                    'message' => 'SendGrid API key not configured',
                ],
            ],
            'metrics' => [],
            'timestamp' => '2025-12-10T00:00:00Z',
        ]);

        $controller = new HealthCheckController($healthService);
        $response = $controller->check();

        $data = $response->getData(true);
        $this->assertFalse($data['checks']['sendgrid']['healthy']);
    }
}
