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
use Equidna\BirdFlock\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

final class HealthCheckControllerTest extends TestCase
{
    public function testHealthCheckReturnsHealthyWhenAllServicesUp(): void
    {
        // Mock database connection
        DB::shouldReceive('connection->getPdo')->andReturn(true);
        DB::shouldReceive('getSchemaBuilder->hasTable')->andReturn(true);

        $controller = new HealthCheckController();
        $response = $controller->check();

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertSame('healthy', $data['status']);
        $this->assertArrayHasKey('checks', $data);
        $this->assertTrue($data['checks']['database']['healthy']);
    }

    public function testHealthCheckReturnsDegradedWhenDatabaseFails(): void
    {
        // Mock database failure
        DB::shouldReceive('connection->getPdo')->andThrow(new \Exception('Connection failed'));

        $controller = new HealthCheckController();
        $response = $controller->check();

        $this->assertSame(503, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertSame('degraded', $data['status']);
        $this->assertFalse($data['checks']['database']['healthy']);
    }

    public function testHealthCheckIncludesCircuitBreakerStates(): void
    {
        // Mock database and schema
        DB::shouldReceive('connection->getPdo')->andReturn(true);
        DB::shouldReceive('getSchemaBuilder->hasTable')->andReturn(true);

        // Set up cache for circuit states
        Cache::shouldReceive('get')
            ->with('circuit_breaker:twilio_sms:state', 'closed')
            ->andReturn('closed');
        Cache::shouldReceive('get')
            ->with('circuit_breaker:twilio_whatsapp:state', 'closed')
            ->andReturn('closed');
        Cache::shouldReceive('get')
            ->with('circuit_breaker:sendgrid_email:state', 'closed')
            ->andReturn('closed');

        $controller = new HealthCheckController();
        $response = $controller->check();

        $data = $response->getData(true);
        $this->assertArrayHasKey('circuits', $data['checks']);
        $this->assertTrue($data['checks']['circuits']['healthy']);
        $this->assertArrayHasKey('states', $data['checks']['circuits']);
        $this->assertSame('closed', $data['checks']['circuits']['states']['twilio_sms']);
    }

    public function testHealthCheckDetectsOpenCircuit(): void
    {
        DB::shouldReceive('connection->getPdo')->andReturn(true);
        DB::shouldReceive('getSchemaBuilder->hasTable')->andReturn(true);

        // One circuit is open
        Cache::shouldReceive('get')
            ->with('circuit_breaker:twilio_sms:state', 'closed')
            ->andReturn('open');
        Cache::shouldReceive('get')
            ->with('circuit_breaker:twilio_whatsapp:state', 'closed')
            ->andReturn('closed');
        Cache::shouldReceive('get')
            ->with('circuit_breaker:sendgrid_email:state', 'closed')
            ->andReturn('closed');

        $controller = new HealthCheckController();
        $response = $controller->check();

        $this->assertSame(503, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertSame('degraded', $data['status']);
        $this->assertFalse($data['checks']['circuits']['healthy']);
        $this->assertSame('open', $data['checks']['circuits']['states']['twilio_sms']);
    }

    public function testHealthCheckValidatesTwilioConfig(): void
    {
        DB::shouldReceive('connection->getPdo')->andReturn(true);
        DB::shouldReceive('getSchemaBuilder->hasTable')->andReturn(true);

        config([
            'bird-flock.twilio.account_sid' => null,
            'bird-flock.twilio.auth_token' => 'test',
        ]);

        $controller = new HealthCheckController();
        $response = $controller->check();

        $data = $response->getData(true);
        $this->assertFalse($data['checks']['twilio']['healthy']);
        $this->assertStringContainsString('credentials', $data['checks']['twilio']['message']);
    }

    public function testHealthCheckValidatesSendgridConfig(): void
    {
        DB::shouldReceive('connection->getPdo')->andReturn(true);
        DB::shouldReceive('getSchemaBuilder->hasTable')->andReturn(true);

        config([
            'bird-flock.sendgrid.api_key' => null,
        ]);

        $controller = new HealthCheckController();
        $response = $controller->check();

        $data = $response->getData(true);
        $this->assertFalse($data['checks']['sendgrid']['healthy']);
    }
}
