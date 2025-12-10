# Health API Integration Guide

This document explains how to integrate Bird Flock health monitoring into your host application's centralized dashboard.

---

## Overview

Bird Flock exposes health information in two ways:

1. **HTTP Endpoints** — For external monitoring systems
2. **Programmatic Interface** — For internal dashboard integration

---

## HTTP Endpoints

### General Health Check

**Endpoint**: `GET /bird-flock/health`

Returns comprehensive health status including all checks and metrics.

**Example Request**:

```bash
curl https://yourdomain.com/bird-flock/health
```

**Example Response**:

```json
{
  "status": "healthy",
  "version": "1.0.0",
  "checks": {
    "database": {
      "healthy": true,
      "message": "Database connected and table exists"
    },
    "twilio": {
      "healthy": true,
      "message": "Twilio configured"
    },
    "sendgrid": {
      "healthy": true,
      "message": "SendGrid configured"
    },
    "queue": {
      "healthy": true,
      "message": "Queue configured: default"
    },
    "circuits": {
      "healthy": true,
      "message": "All circuits closed",
      "states": {
        "twilio_sms": "closed",
        "twilio_whatsapp": "closed",
        "sendgrid_email": "closed"
      }
    }
  },
  "metrics": {
    "dlq": {
      "count": 0,
      "by_channel": {}
    },
    "queue": {
      "pending": 0,
      "queue_name": "default"
    },
    "performance": {
      "avg_sender_duration_ms": null,
      "recent_samples": 0
    }
  },
  "timestamp": "2025-12-10T22:37:00Z"
}
```

### Circuit Breaker Status

**Endpoint**: `GET /bird-flock/health/circuit-breakers`

Returns detailed circuit breaker information for all providers.

**Example Request**:

```bash
curl https://yourdomain.com/bird-flock/health/circuit-breakers
```

**Example Response**:

```json
{
  "status": "healthy",
  "circuits": {
    "twilio_sms": {
      "state": "closed",
      "healthy": true,
      "failure_count": 0,
      "success_count": 125,
      "trial_count": 0,
      "configuration": {
        "failure_threshold": 5,
        "timeout_seconds": 60,
        "success_threshold": 2
      },
      "status_message": "Circuit closed - normal operation"
    },
    "sendgrid_email": {
      "state": "open",
      "healthy": false,
      "failure_count": 5,
      "success_count": 0,
      "trial_count": 0,
      "last_failure_at": "2025-12-10T22:30:00Z",
      "seconds_since_failure": 420,
      "recovery_in_seconds": 0,
      "estimated_recovery_at": "2025-12-10T22:31:00Z",
      "configuration": {
        "failure_threshold": 5,
        "timeout_seconds": 60,
        "success_threshold": 2
      },
      "status_message": "Circuit open - blocking requests to protect service"
    }
  },
  "timestamp": "2025-12-10T22:37:00Z"
}
```

---

## Programmatic Interface

For internal dashboard integration, use the `HealthService` class directly in your Laravel application.

### Basic Usage

```php
use Equidna\BirdFlock\Services\HealthService;

class DashboardController extends Controller
{
    public function __construct(private HealthService $healthService)
    {
    }

    public function index()
    {
        // Get complete health status
        $health = $this->healthService->getHealthStatus();
        
        // Get circuit breaker status
        $circuits = $this->healthService->getCircuitBreakerStatus();
        
        return view('dashboard', [
            'bird_flock_health' => $health,
            'bird_flock_circuits' => $circuits,
        ]);
    }
}
```

### Health Service API

#### Get Complete Health Status

```php
$health = $healthService->getHealthStatus();
```

Returns:

```php
[
    'status' => 'healthy',  // or 'degraded'
    'version' => '1.0.0',
    'checks' => [
        'database' => ['healthy' => true, 'message' => '...'],
        'twilio' => ['healthy' => true, 'message' => '...'],
        'sendgrid' => ['healthy' => true, 'message' => '...'],
        'queue' => ['healthy' => true, 'message' => '...'],
        'circuits' => [
            'healthy' => true,
            'message' => '...',
            'states' => [...]
        ],
    ],
    'metrics' => [
        'dlq' => ['count' => 0, 'by_channel' => []],
        'queue' => ['pending' => 0, 'queue_name' => 'default'],
        'performance' => ['avg_sender_duration_ms' => null, 'recent_samples' => 0],
    ],
    'timestamp' => '2025-12-10T22:37:00Z',
]
```

#### Get Circuit Breaker Status

```php
$circuits = $healthService->getCircuitBreakerStatus();
```

Returns:

```php
[
    'status' => 'healthy',  // or 'degraded'
    'circuits' => [
        'twilio_sms' => [
            'state' => 'closed',
            'healthy' => true,
            'failure_count' => 0,
            'success_count' => 125,
            // ... additional details
        ],
        // ... other providers
    ],
    'timestamp' => '2025-12-10T22:37:00Z',
]
```

---

## Dashboard Integration Examples

### Laravel Blade Template

```blade
<!-- resources/views/dashboard.blade.php -->
<div class="card">
    <div class="card-header">
        <h3>Bird Flock Messaging Status</h3>
        <span class="badge badge-{{ $bird_flock_health['status'] === 'healthy' ? 'success' : 'danger' }}">
            {{ ucfirst($bird_flock_health['status']) }}
        </span>
    </div>
    <div class="card-body">
        <h5>Service Checks</h5>
        <ul class="list-group">
            @foreach($bird_flock_health['checks'] as $name => $check)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    {{ ucfirst($name) }}
                    <span class="badge badge-{{ $check['healthy'] ? 'success' : 'danger' }}">
                        {{ $check['healthy'] ? 'OK' : 'Error' }}
                    </span>
                </li>
            @endforeach
        </ul>

        <h5 class="mt-4">Metrics</h5>
        <div class="row">
            <div class="col-md-4">
                <div class="metric">
                    <h6>Dead Letter Queue</h6>
                    <p class="metric-value">{{ $bird_flock_health['metrics']['dlq']['count'] }}</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric">
                    <h6>Pending Messages</h6>
                    <p class="metric-value">{{ $bird_flock_health['metrics']['queue']['pending'] }}</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric">
                    <h6>Queue Name</h6>
                    <p class="metric-value">{{ $bird_flock_health['metrics']['queue']['queue_name'] }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
```

### Vue.js Component

```vue
<!-- components/BirdFlockHealth.vue -->
<template>
  <div class="bird-flock-health">
    <div class="header">
      <h3>Bird Flock Messaging</h3>
      <span :class="['badge', statusClass]">{{ health.status }}</span>
    </div>
    
    <div class="checks">
      <div
        v-for="(check, name) in health.checks"
        :key="name"
        class="check-item"
      >
        <span class="check-name">{{ name }}</span>
        <span :class="['check-status', check.healthy ? 'healthy' : 'unhealthy']">
          {{ check.healthy ? '✓' : '✗' }}
        </span>
      </div>
    </div>
    
    <div class="metrics">
      <div class="metric">
        <span class="metric-label">DLQ Count</span>
        <span class="metric-value">{{ health.metrics.dlq.count }}</span>
      </div>
      <div class="metric">
        <span class="metric-label">Pending Messages</span>
        <span class="metric-value">{{ health.metrics.queue.pending }}</span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      health: null,
    };
  },
  computed: {
    statusClass() {
      return this.health.status === 'healthy' ? 'badge-success' : 'badge-danger';
    },
  },
  async mounted() {
    const response = await fetch('/bird-flock/health');
    this.health = await response.json();
    
    // Refresh every 30 seconds
    setInterval(async () => {
      const response = await fetch('/bird-flock/health');
      this.health = await response.json();
    }, 30000);
  },
};
</script>
```

### React Component

```jsx
// components/BirdFlockHealth.jsx
import React, { useState, useEffect } from 'react';

export default function BirdFlockHealth() {
  const [health, setHealth] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchHealth = async () => {
      try {
        const response = await fetch('/bird-flock/health');
        const data = await response.json();
        setHealth(data);
      } catch (error) {
        console.error('Failed to fetch health data:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchHealth();
    const interval = setInterval(fetchHealth, 30000); // Refresh every 30 seconds

    return () => clearInterval(interval);
  }, []);

  if (loading) return <div>Loading...</div>;
  if (!health) return <div>Failed to load health data</div>;

  return (
    <div className="bird-flock-health">
      <div className="header">
        <h3>Bird Flock Messaging</h3>
        <span className={`badge ${health.status === 'healthy' ? 'badge-success' : 'badge-danger'}`}>
          {health.status}
        </span>
      </div>
      
      <div className="checks">
        {Object.entries(health.checks).map(([name, check]) => (
          <div key={name} className="check-item">
            <span className="check-name">{name}</span>
            <span className={`check-status ${check.healthy ? 'healthy' : 'unhealthy'}`}>
              {check.healthy ? '✓' : '✗'}
            </span>
          </div>
        ))}
      </div>
      
      <div className="metrics">
        <div className="metric">
          <span className="metric-label">DLQ Count</span>
          <span className="metric-value">{health.metrics.dlq.count}</span>
        </div>
        <div className="metric">
          <span className="metric-label">Pending Messages</span>
          <span className="metric-value">{health.metrics.queue.pending}</span>
        </div>
      </div>
    </div>
  );
}
```

---

## Monitoring Integration

### Grafana Dashboard

You can create a Grafana dashboard by polling the health endpoint:

1. Add a JSON API data source pointing to `/bird-flock/health`
2. Create panels for:
   - Overall health status (gauge)
   - Individual check statuses (table)
   - DLQ count (time series graph)
   - Pending messages count (time series graph)
   - Circuit breaker states (table)

### Datadog

```php
use Equidna\BirdFlock\Services\HealthService;
use DataDog\DogStatsd;

class BirdFlockMonitor
{
    public function __construct(
        private HealthService $healthService,
        private DogStatsd $statsd
    ) {
    }

    public function reportMetrics(): void
    {
        $health = $this->healthService->getHealthStatus();
        
        // Report overall status
        $this->statsd->gauge(
            'bird_flock.health.status',
            $health['status'] === 'healthy' ? 1 : 0
        );
        
        // Report DLQ count
        $this->statsd->gauge(
            'bird_flock.dlq.count',
            $health['metrics']['dlq']['count']
        );
        
        // Report pending messages
        $this->statsd->gauge(
            'bird_flock.queue.pending',
            $health['metrics']['queue']['pending']
        );
        
        // Report circuit breaker states
        $circuits = $this->healthService->getCircuitBreakerStatus();
        foreach ($circuits['circuits'] as $service => $circuit) {
            $this->statsd->gauge(
                "bird_flock.circuit.{$service}.healthy",
                $circuit['healthy'] ? 1 : 0
            );
        }
    }
}
```

### Prometheus

```php
use Equidna\BirdFlock\Services\HealthService;
use Prometheus\CollectorRegistry;

class BirdFlockPrometheusExporter
{
    public function __construct(
        private HealthService $healthService,
        private CollectorRegistry $registry
    ) {
    }

    public function export(): string
    {
        $health = $this->healthService->getHealthStatus();
        
        // Register gauge metrics
        $healthGauge = $this->registry->getOrRegisterGauge(
            'bird_flock',
            'health_status',
            'Overall health status (1 = healthy, 0 = degraded)',
            []
        );
        $healthGauge->set($health['status'] === 'healthy' ? 1 : 0);
        
        $dlqGauge = $this->registry->getOrRegisterGauge(
            'bird_flock',
            'dlq_count',
            'Number of messages in dead letter queue',
            []
        );
        $dlqGauge->set($health['metrics']['dlq']['count']);
        
        $queueGauge = $this->registry->getOrRegisterGauge(
            'bird_flock',
            'queue_pending',
            'Number of pending messages in queue',
            []
        );
        $queueGauge->set($health['metrics']['queue']['pending']);
        
        // Circuit breaker metrics
        $circuits = $this->healthService->getCircuitBreakerStatus();
        $circuitGauge = $this->registry->getOrRegisterGauge(
            'bird_flock',
            'circuit_healthy',
            'Circuit breaker health status (1 = closed, 0 = open)',
            ['service']
        );
        
        foreach ($circuits['circuits'] as $service => $circuit) {
            $circuitGauge->set($circuit['healthy'] ? 1 : 0, [$service]);
        }
        
        return $this->registry->render();
    }
}
```

---

## Alerting

### Example Alert Conditions

1. **Overall Health Degraded**
   - Condition: `status !== 'healthy'`
   - Action: Send notification to operations team

2. **Circuit Breaker Open**
   - Condition: Any circuit state is `'open'`
   - Action: Alert immediately, investigate provider

3. **DLQ Growing**
   - Condition: `metrics.dlq.count > 100`
   - Action: Review failed messages, check provider credentials

4. **High Queue Depth**
   - Condition: `metrics.queue.pending > 1000`
   - Action: Scale queue workers, investigate processing delays

---

## Best Practices

1. **Polling Frequency**: Poll the health endpoint every 30-60 seconds for dashboard updates
2. **Caching**: Consider caching health data for 10-30 seconds to reduce load
3. **Error Handling**: Always handle HTTP errors gracefully in your dashboard
4. **Historical Data**: Store health metrics over time for trend analysis
5. **Alerting**: Set up alerts for critical conditions (circuit breakers, high DLQ)

---

## Configuration

Health check endpoints can be disabled via configuration:

```dotenv
# .env
BIRD_FLOCK_HEALTH_ENABLED=true
```

```php
// config/bird-flock.php
'health' => [
    'enabled' => env('BIRD_FLOCK_HEALTH_ENABLED', true),
],
```

---

## Security Considerations

1. **Access Control**: Consider adding middleware to restrict access to health endpoints
2. **IP Whitelisting**: Restrict health endpoint access to internal IPs or monitoring systems
3. **Authentication**: For sensitive environments, add authentication to health endpoints

Example middleware:

```php
Route::prefix('bird-flock')->middleware(['ip_whitelist'])->group(function () {
    Route::get('/health', [HealthCheckController::class, 'check']);
    Route::get('/health/circuit-breakers', [HealthCheckController::class, 'circuitBreakers']);
});
```
