# Monitoring

This document covers logging, metrics, health checks, troubleshooting, and recommended monitoring practices for **Bird Flock**.

---

## Logging

### Log Channels

Bird Flock uses Laravel's logging system. Configure via `.env`:

```dotenv
# Enable/disable Bird Flock logging
BIRD_FLOCK_LOGGING_ENABLED=true

# Specify log channel (leave empty for default)
BIRD_FLOCK_LOG_CHANNEL=stack
```

**Default Behavior**:

- If `BIRD_FLOCK_LOG_CHANNEL` is empty, uses Laravel's default channel (`LOG_CHANNEL` in `.env`)
- If `BIRD_FLOCK_LOGGING_ENABLED=false`, uses `NullLogger` (no logs)

### Log Destinations

Configured in `config/logging.php`:

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'stderr'],
    ],
    'single' => [
        'driver' => 'single',
        'path' => storage_path('logs/laravel.log'),
    ],
    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'days' => 14,
    ],
],
```

**Recommendations**:

- **Local Development**: Use `single` or `stack` channels
- **Production**: Use `daily` with rotation, or external log aggregation (see below)

### Key Log Events

Bird Flock logs structured messages with context:

| Event                             | Log Level | Context Fields                                          |
| --------------------------------- | --------- | ------------------------------------------------------- |
| Message dispatched                | `info`    | `channel`, `idempotency_key`, `to` (masked)             |
| Message queued                    | `info`    | `message_id`, `queue`, `scheduled`                      |
| Message sending                   | `info`    | `message_id`, `channel`, `provider`                     |
| Message sent successfully         | `info`    | `message_id`, `provider_message_id`, `channel`          |
| Message delivered (webhook)       | `info`    | `message_id`, `status`, `provider_message_id`           |
| Message failed                    | `error`   | `message_id`, `error_code`, `error_message`, `attempts` |
| Dead-lettered                     | `warning` | `message_id`, `channel`, `attempts`                     |
| Circuit breaker opened            | `warning` | `provider`, `failure_count`                             |
| Circuit breaker closed            | `info`    | `provider`                                              |
| Idempotency conflict detected     | `info`    | `existing_message_id`, `idempotency_key`                |
| Duplicate message skipped         | `info`    | `message_id`, `status`                                  |
| Webhook received                  | `info`    | `provider`, `event_type`, `message_id`                  |
| Webhook signature validation fail | `warning` | `provider`, `remote_ip`                                 |
| Payload too large                 | `error`   | `size`, `max`, `channel`                                |

**Log Entry Example**:

```json
{
  "timestamp": "2025-11-30T10:23:45.123Z",
  "level": "info",
  "message": "bird-flock.dispatch.queued",
  "context": {
    "message_id": "01HQRS1234567890ABCDEF",
    "queue": "default",
    "channel": "sms",
    "scheduled": false
  }
}
```

### Masking Sensitive Data

Bird Flock automatically masks sensitive fields:

- **Phone Numbers**: Last 4 digits shown (e.g., `****7890`)
- **Email Addresses**: Local part masked (e.g., `t***@example.com`)

Implemented via `Equidna\BirdFlock\Support\Masking` class.

### Centralized Logging

**Recommended for Production**: Send logs to external aggregation services:

- **AWS CloudWatch Logs** (via `aws/aws-sdk-php-laravel`)
- **Loggly** (via `loggly/monolog-loggly`)
- **Papertrail** (via `syslog` channel)
- **ELK Stack** (Elasticsearch, Logstash, Kibana)
- **Datadog Logs** (via Datadog agent)

**Example**: CloudWatch Logs via Monolog

```php
// config/logging.php
'cloudwatch' => [
    'driver' => 'monolog',
    'handler' => \Aws\CloudWatchLogs\Handler\CloudWatchLogsHandler::class,
    'with' => [
        'logGroupName' => env('AWS_CLOUDWATCH_LOG_GROUP', '/aws/laravel/bird-flock'),
        'logStreamName' => env('AWS_CLOUDWATCH_LOG_STREAM', 'production'),
    ],
],
```

---

## Metrics Collection

### Built-in Metrics Collector

Bird Flock includes a `MetricsCollectorInterface` for tracking key metrics:

**Interface**: `Equidna\BirdFlock\Contracts\MetricsCollectorInterface`  
**Default Implementation**: `Equidna\BirdFlock\Support\MetricsCollector` (logs metrics as structured logs)

**Metrics Tracked**:

| Metric                         | Type      | Tags                                | Description                         |
| ------------------------------ | --------- | ----------------------------------- | ----------------------------------- |
| `bird_flock.dispatched`        | Counter   | `channel`                           | Messages dispatched                 |
| `bird_flock.queued`            | Counter   | `channel`                           | Messages queued                     |
| `bird_flock.sent`              | Counter   | `channel`, `provider`               | Messages sent successfully          |
| `bird_flock.delivered`         | Counter   | `channel`, `provider`               | Messages delivered (via webhook)    |
| `bird_flock.failed`            | Counter   | `channel`, `provider`, `error_code` | Messages failed (permanent)         |
| `bird_flock.retried`           | Counter   | `channel`, `attempt`                | Retry attempts                      |
| `bird_flock.dead_lettered`     | Counter   | `channel`                           | Messages moved to DLQ               |
| `bird_flock.duplicate_skipped` | Counter   | `channel`                           | Duplicate dispatches skipped        |
| `bird_flock.create_conflict`   | Counter   | `channel`                           | Idempotency key conflicts           |
| `bird_flock.circuit_opened`    | Counter   | `provider`                          | Circuit breaker opened              |
| `bird_flock.circuit_closed`    | Counter   | `provider`                          | Circuit breaker closed              |
| `bird_flock.send_duration_ms`  | Histogram | `channel`, `provider`               | Time to send message (milliseconds) |

### Custom Metrics Collector

To integrate with external metrics systems (Prometheus, Datadog, New Relic), implement `MetricsCollectorInterface` and bind in your service provider:

```php
// app/Providers/AppServiceProvider.php
use App\Services\DatadogMetricsCollector;
use Equidna\BirdFlock\Contracts\MetricsCollectorInterface;

public function register(): void
{
    $this->app->bind(
        MetricsCollectorInterface::class,
        DatadogMetricsCollector::class
    );
}
```

**Example Implementation** (Prometheus):

```php
namespace App\Services;

use Equidna\BirdFlock\Contracts\MetricsCollectorInterface;
use Prometheus\CollectorRegistry;

class PrometheusMetricsCollector implements MetricsCollectorInterface
{
    public function __construct(private CollectorRegistry $registry) {}

    public function increment(string $metric, int $value = 1, array $tags = []): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            'bird_flock',
            str_replace('.', '_', $metric),
            'Bird Flock metrics',
            array_keys($tags)
        );
        $counter->incBy($value, array_values($tags));
    }

    // Implement other methods...
}
```

---

## Health Checks

### Endpoints

1. **General Health Check**  
   **URL**: `GET /bird-flock/health`  
   **Purpose**: Returns package status, configured channels, queue name, DLQ status  
   **Response**:

   ```json
   {
     "status": "ok",
     "package": "equidna/bird-flock",
     "configured_channels": ["sms", "whatsapp", "email"],
     "queue": "default",
     "dlq_enabled": true
   }
   ```

2. **Circuit Breaker Status**  
   **URL**: `GET /bird-flock/health/circuit-breakers`  
   **Purpose**: Returns circuit breaker state for all providers  
   **Response**:
   ```json
   {
     "circuit_breakers": {
       "twilio_sms": {
         "state": "closed",
         "failure_count": 0,
         "last_failure_at": null
       },
       "sendgrid_email": {
         "state": "open",
         "failure_count": 5,
         "last_failure_at": "2025-11-30T10:23:45Z"
       }
     }
   }
   ```

### Monitoring Health Checks

**Recommendations**:

- Integrate with **Uptime Monitoring** (Pingdom, UptimeRobot, Datadog Synthetics)
- Alert on `status: "error"` or circuit breaker `state: "open"`
- Check every 1–5 minutes

---

## Recommended Metrics & Alerts

### Key Performance Indicators (KPIs)

| Metric                         | Target / Threshold | Description                             |
| ------------------------------ | ------------------ | --------------------------------------- |
| **Message Send Success Rate**  | > 99%              | `sent / (sent + failed)`                |
| **Average Send Latency**       | < 2 seconds        | Time from dispatch to provider API call |
| **Queue Depth**                | < 1000 messages    | Number of pending jobs in queue         |
| **Dead-Letter Queue Size**     | < 10 messages/hour | Growth rate of DLQ                      |
| **Circuit Breaker Open Count** | 0 (alert if > 0)   | Number of open circuit breakers         |
| **Webhook Processing Latency** | < 500ms            | Time to process webhook callback        |
| **Duplicate Skipped Rate**     | Monitor baseline   | Idempotency effectiveness               |

### Suggested Alerts

| Alert                          | Condition                                | Severity | Action                               |
| ------------------------------ | ---------------------------------------- | -------- | ------------------------------------ |
| **Provider Outage**            | Circuit breaker open for > 5 minutes     | Critical | Check provider status, notify team   |
| **High Failure Rate**          | Failure rate > 5% over 10 minutes        | Warning  | Investigate logs, check credentials  |
| **Dead-Letter Queue Growing**  | DLQ size increased by > 50 in 10 minutes | Warning  | Review failed messages, replay       |
| **Queue Worker Down**          | No jobs processed in 5 minutes           | Critical | Restart queue workers                |
| **Large Payload Rejected**     | `payload_too_large` errors               | Info     | Review message content, adjust limit |
| **Webhook Signature Failures** | > 10 failures in 5 minutes               | Warning  | Check signature keys, IP whitelist   |
| **Send Latency Spike**         | P95 latency > 5 seconds                  | Warning  | Check provider API performance       |

---

## Monitoring Tools

### Laravel Telescope (Development)

**Purpose**: Real-time debugging for local development

**Installation**:

```bash
composer require --dev laravel/telescope
php artisan telescope:install
php artisan migrate
```

**Features**:

- View queued jobs
- Monitor exceptions
- Trace HTTP requests and webhooks
- Inspect logs and events

**Access**: `http://localhost/telescope`

---

### Laravel Horizon (Queue Monitoring)

**Purpose**: Monitor and manage Redis queues

**Installation**:

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan migrate
```

**Configuration**:

```php
// config/horizon.php
'defaults' => [
    'supervisor-1' => [
        'connection' => 'redis',
        'queue' => ['default', 'messaging'],
        'balance' => 'auto',
        'processes' => 4,
        'tries' => 3,
    ],
],
```

**Features**:

- Real-time queue metrics
- Failed job retry management
- Job throughput graphs
- Worker process monitoring

**Access**: `http://yourdomain.com/horizon`

---

### Sentry (Error Tracking)

**Purpose**: Production error tracking and alerting

**Installation**:

```bash
composer require sentry/sentry-laravel
```

**Configuration**:

```dotenv
SENTRY_LARAVEL_DSN=https://...@sentry.io/...
SENTRY_TRACES_SAMPLE_RATE=0.1
```

**Features**:

- Real-time error alerts
- Stack trace and context
- Performance monitoring
- Release tracking

---

### New Relic / Datadog APM

**Purpose**: Application performance monitoring

**Key Metrics to Track**:

- Queue job duration
- HTTP webhook response time
- Database query performance
- External API call latency (Twilio, SendGrid, etc.)

**Integration**: Install agent per vendor docs; metrics automatically collected.

---

## Troubleshooting

### Common Issues

#### 1. Messages Not Sending

**Symptoms**:

- Messages stuck in `queued` status
- No errors in logs

**Diagnosis**:

1. Check queue worker status:
   ```bash
   ps aux | grep 'queue:work'
   ```
2. Verify queue connection:
   ```bash
   php artisan queue:work --once
   ```
3. Check database for queued messages:
   ```sql
   SELECT * FROM bird_flock_outbound_messages WHERE status = 'queued' ORDER BY queued_at DESC LIMIT 10;
   ```

**Solutions**:

- Restart queue workers: `sudo supervisorctl restart bird-flock-worker:*`
- Check queue driver configuration (`QUEUE_CONNECTION` in `.env`)
- Ensure Redis/database is accessible

---

#### 2. High Failure Rate

**Symptoms**:

- Many messages in `failed` status
- Circuit breaker opening frequently

**Diagnosis**:

1. Check dead-letter queue:
   ```bash
   php artisan bird-flock:dead-letter list --limit=50
   ```
2. Review logs for error patterns:
   ```bash
   tail -f storage/logs/laravel.log | grep 'bird-flock.send.failed'
   ```
3. Check circuit breaker status:
   ```bash
   curl https://yourdomain.com/bird-flock/health/circuit-breakers
   ```

**Solutions**:

- **Invalid Credentials**: Verify provider API keys in `.env`
- **Invalid Phone Numbers**: Check recipient format (E.164)
- **Provider Outage**: Wait for circuit breaker to close; monitor provider status
- **Rate Limiting**: Reduce dispatch rate or increase provider limits

---

#### 3. Webhooks Not Processed

**Symptoms**:

- Messages stuck in `sent` status
- No delivery receipts

**Diagnosis**:

1. Check webhook URL configuration with providers
2. Verify HTTPS and signature validation:
   ```bash
   tail -f storage/logs/laravel.log | grep 'webhook.signature'
   ```
3. Test webhook endpoint:
   ```bash
   curl -X POST https://yourdomain.com/bird-flock/webhooks/twilio/status \
     -d "MessageSid=SM123&MessageStatus=delivered"
   ```

**Solutions**:

- **URL Not Configured**: Add webhook URL in provider dashboard
- **HTTPS Required**: Ensure application uses HTTPS in production
- **Signature Validation**: Verify signing keys in `.env` match provider settings
- **Firewall Blocking**: Whitelist provider IPs

---

#### 4. Dead-Letter Queue Growing

**Symptoms**:

- DLQ size increasing rapidly
- Repeated failures for same messages

**Diagnosis**:

1. View DLQ statistics:
   ```bash
   php artisan bird-flock:dead-letter-stats
   ```
2. Identify error patterns (invalid recipients, expired credentials, etc.)

**Solutions**:

- **Fix Root Cause**: Update credentials, validate recipients before dispatch
- **Replay Valid Messages**: After fixing, replay messages:
  ```bash
  php artisan bird-flock:dead-letter replay <message_id>
  ```
- **Purge Invalid Messages**: Remove unrecoverable messages:
  ```bash
  php artisan bird-flock:dead-letter purge
  ```

---

#### 5. Slow Queue Processing

**Symptoms**:

- High queue depth
- Long send latency

**Diagnosis**:

1. Check queue worker count:
   ```bash
   ps aux | grep 'queue:work' | wc -l
   ```
2. Monitor job processing rate (Horizon dashboard)
3. Check database performance (slow queries)

**Solutions**:

- **Increase Workers**: Add more queue worker processes in Supervisor config
- **Optimize Jobs**: Review job logic for bottlenecks
- **Scale Infrastructure**: Add more application servers or queue workers

---

## Assumptions

- **Logging Enabled by Default**: To disable, set `BIRD_FLOCK_LOGGING_ENABLED=false`.
- **Metrics Collection**: Default implementation logs metrics; integrate with external systems for production-grade monitoring.
- **Health Check Availability**: Health endpoints are publicly accessible (no authentication); consider IP whitelisting if needed.
- **Timezone**: All timestamps in logs are UTC (Laravel default).

For unresolved monitoring questions, see [Open Questions & Assumptions](open-questions-and-assumptions.md).
