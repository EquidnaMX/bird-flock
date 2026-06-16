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
use Equidna\BirdFlock\Tests\Support\NoArgSender;
use Equidna\BirdFlock\Tests\TestCase;
use Illuminate\Container\Container;

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
        $this->bindHealthyDatabase();

        config([
            'bird-flock-twilio.account_sid' => 'test_sid',
            'bird-flock-twilio.auth_token' => 'test_token',
            'bird-flock-twilio.from_sms' => '+1234567890',
            'bird-flock-sendgrid.api_key' => 'test_key',
            'bird-flock-sendgrid.from_email' => 'test@example.com',
            'bird-flock.default_queue' => 'default',
            'bird-flock.channels.email.senders' => [
                'sendgrid' => [
                    'sender' => \Equidna\BirdFlock\Senders\Sendgrid\SendgridEmailSender::class,
                ],
            ],
        ]);

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
        $this->bindFailingDatabase();

        config([
            'bird-flock-twilio.account_sid' => 'test_sid',
            'bird-flock-twilio.auth_token' => 'test_token',
            'bird-flock-twilio.from_sms' => '+1234567890',
            'bird-flock-sendgrid.api_key' => 'test_key',
            'bird-flock-sendgrid.from_email' => 'test@example.com',
            'bird-flock.default_queue' => 'default',
            'bird-flock.channels.email.senders' => [
                'sendgrid' => [
                    'sender' => \Equidna\BirdFlock\Senders\Sendgrid\SendgridEmailSender::class,
                ],
            ],
        ]);

        $health = $this->healthService->getHealthStatus();

        $this->assertSame('degraded', $health['status']);
        $this->assertFalse($health['checks']['database']['healthy']);
        $this->assertStringContainsString('Connection failed', $health['checks']['database']['message']);
    }

    public function testGetCircuitBreakerStatusReturnsAllCircuits(): void
    {
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
        app('cache')->put('circuit_breaker:twilio_sms:state', 'open', 300);
        app('cache')->put('circuit_breaker:twilio_sms:failures', 5, 300);
        app('cache')->put('circuit_breaker:twilio_sms:last_failure', time() - 30, 300);

        $circuits = $this->healthService->getCircuitBreakerStatus();

        $this->assertSame('degraded', $circuits['status']);
        $this->assertSame('open', $circuits['circuits']['twilio_sms']['state']);
        $this->assertFalse($circuits['circuits']['twilio_sms']['healthy']);
        $this->assertArrayHasKey('last_failure_at', $circuits['circuits']['twilio_sms']);
        $this->assertArrayHasKey('recovery_in_seconds', $circuits['circuits']['twilio_sms']);
    }

    public function testGetHealthStatusDoesNotDegradeForConfiguredCustomSender(): void
    {
        $this->bindHealthyDatabase();

        config([
            'bird-flock.default_queue' => 'default',
            'bird-flock.channels' => [
                'sms' => [
                    'strategy' => 'round_robin',
                    'senders' => [
                        'acme' => NoArgSender::class,
                    ],
                ],
            ],
        ]);

        $health = $this->healthService->getHealthStatus();

        $this->assertSame('healthy', $health['status']);
        $this->assertTrue($health['checks']['acme']['healthy']);
        $this->assertSame('Custom sender configured for vendor: acme', $health['checks']['acme']['message']);
    }

    private function bindHealthyDatabase(): void
    {
        Container::getInstance()->instance('db', new class {
            public function connection(): object
            {
                return new class {
                    public function getPdo(): bool
                    {
                        return true;
                    }
                };
            }

            public function getSchemaBuilder(): object
            {
                return new class {
                    public function hasTable(string $table): bool
                    {
                        return true;
                    }
                };
            }

            public function table(string $table): object
            {
                return new class {
                    public function count(): int
                    {
                        return 0;
                    }

                    public function select(mixed ...$columns): self
                    {
                        return $this;
                    }

                    public function groupBy(string $column): self
                    {
                        return $this;
                    }

                    public function pluck(string $column, string $key): object
                    {
                        return new class {
                            public function toArray(): array
                            {
                                return [];
                            }
                        };
                    }
                };
            }
        });
    }

    private function bindFailingDatabase(): void
    {
        Container::getInstance()->instance('db', new class {
            public function connection(): object
            {
                return new class {
                    public function getPdo(): void
                    {
                        throw new \Exception('Connection failed');
                    }
                };
            }
        });
    }
}
