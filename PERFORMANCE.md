# Bird Flock - Performance Optimization Guide

## Database Indexes

Bird Flock includes comprehensive indexes for high-performance message processing:

### Primary Indexes (create_outbound_messages_table)

- `PRIMARY KEY` on `id_outboundMessage` - Fast lookups by message ID
- `UNIQUE INDEX` on `idempotencyKey` - Enforces deduplication, enables fast idempotency checks
- `INDEX(providerMessageId, status)` - Fast webhook updates and status queries
- `INDEX(channel, status)` - Channel-specific reporting and monitoring

### Performance Indexes (add_performance_indexes)

- `INDEX(createdAt)` - Time-based queries for archival and reporting
- `INDEX(status, attempts, createdAt)` - Failed message analysis and retry scheduling
- `INDEX(providerMessageId)` - Fast provider lookups from webhooks
- `INDEX(status, queuedAt)` - Scheduled message dispatch queries

### DLQ Indexes

- `INDEX(message_id)` - Fast correlation with outbound messages
- `INDEX(channel)` - Channel-specific DLQ analysis
- `INDEX(created_at)` - Time-based DLQ queries
- `INDEX(channel, created_at)` - Combined channel/time reporting

## Archival Strategy

### Recommended Approach

Archive messages older than 90 days to maintain query performance:

```php
<?php
namespace App\Console\Commands;

use Equidna\BirdFlock\Models\OutboundMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ArchiveOldMessages extends Command
{
    protected $signature = 'bird-flock:archive {--days=90 : Days to retain}';
    protected $description = 'Archive old outbound messages to cold storage';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);

        // Option 1: Move to archive table
        DB::transaction(function () use ($cutoff) {
            DB::statement('
                INSERT INTO bird_flock_outbound_messages_archive
                SELECT * FROM bird_flock_outbound_messages
                WHERE createdAt < ? AND status IN ("sent", "delivered", "undeliverable")
            ', [$cutoff]);

            $count = DB::table('bird_flock_outbound_messages')
                ->where('createdAt', '<', $cutoff)
                ->whereIn('status', ['sent', 'delivered', 'undeliverable'])
                ->delete();

            $this->info("Archived {$count} messages");
        });

        // Option 2: Export to S3/GCS and delete
        // $this->exportToCloudStorage($cutoff);

        return self::SUCCESS;
    }
}
```

Schedule the command in `app/Console/Kernel.php`:

```php
$schedule->command('bird-flock:archive --days=90')->daily();
```

### Archival Best Practices

1. **Only archive terminal states**: `sent`, `delivered`, `undeliverable`
2. **Keep failed messages longer**: They may need manual intervention
3. **Retain DLQ entries**: Critical for debugging recurring issues
4. **Test restores**: Ensure archived data is accessible when needed
5. **Monitor table size**: Alert when growth exceeds expected rate

## Circuit Breaker State Caching

### Redis Configuration

For high-traffic deployments, use Redis for circuit breaker state:

```php
// config/bird-flock.php
'circuit_breaker' => [
    'cache_store' => env('BIRD_FLOCK_CIRCUIT_CACHE', 'redis'),
    'failure_threshold' => env('BIRD_FLOCK_CIRCUIT_BREAKER_FAILURE_THRESHOLD', 5),
    'timeout' => env('BIRD_FLOCK_CIRCUIT_BREAKER_TIMEOUT', 60),
    'success_threshold' => env('BIRD_FLOCK_CIRCUIT_BREAKER_SUCCESS_THRESHOLD', 2),
],
```

Configure Redis in `.env`:

```env
BIRD_FLOCK_CIRCUIT_CACHE=redis
CACHE_DRIVER=redis
REDIS_CLIENT=predis  # or phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Benefits of Redis for Circuit Breakers

- **Atomic operations**: Prevents race conditions in high-concurrency scenarios
- **Shared state**: Circuit breaker state consistent across multiple app servers
- **Persistence**: State survives application restarts
- **Performance**: Sub-millisecond lookups for circuit availability checks

## Load Testing

### Recommended Tools

- **Apache Bench (ab)**: Quick baseline tests
- **wrk**: High-performance HTTP benchmarking
- **Locust**: Python-based load testing with scenarios
- **K6**: Modern load testing tool with scripting

### Test Scenarios

#### 1. Single Message Dispatch

```bash
# Baseline: 100 requests, 10 concurrent
ab -n 100 -c 10 -H "Content-Type: application/json" \
   -p message.json \
   http://localhost/api/messages/send
```

#### 2. Batch Dispatch (100 messages/request)

Target: 10,000+ messages/second sustained

```javascript
// k6-batch-test.js
import http from "k6/http";

export let options = {
  stages: [
    { duration: "2m", target: 100 }, // Ramp-up
    { duration: "5m", target: 100 }, // Sustained
    { duration: "2m", target: 0 }, // Ramp-down
  ],
};

export default function () {
  const messages = Array.from({ length: 100 }, (_, i) => ({
    channel: "sms",
    to: `+150055500${String(i).padStart(2, "0")}`,
    text: "Load test message",
  }));

  http.post(
    "http://localhost/api/messages/batch",
    JSON.stringify({ messages }),
    { headers: { "Content-Type": "application/json" } }
  );
}
```

Run: `k6 run k6-batch-test.js`

#### 3. Circuit Breaker Recovery

Test circuit breaker behavior under provider failures:

```python
# locustfile.py
from locust import HttpUser, task, between

class MessageUser(HttpUser):
    wait_time = between(0.1, 0.5)

    @task
    def send_message(self):
        self.client.post("/api/messages/send", json={
            "channel": "sms",
            "to": "+15005550006",
            "text": "Test"
        })

    @task(2)
    def check_health(self):
        self.client.get("/bird-flock/health/circuit-breakers")
```

Run: `locust -f locustfile.py --host=http://localhost`

### Performance Targets

| Metric                    | Target        | Notes                        |
| ------------------------- | ------------- | ---------------------------- |
| Single dispatch latency   | < 50ms p95    | Includes queue insertion     |
| Batch dispatch (100 msgs) | < 200ms p95   | Parallel processing          |
| Throughput                | 10,000+ msg/s | With optimized queue backend |
| Circuit breaker check     | < 1ms         | Redis-backed                 |
| Database write            | < 10ms        | With proper indexes          |
| Queue job execution       | < 500ms p95   | Including provider API call  |

### Optimization Checklist

- [ ] Database connection pooling configured
- [ ] Redis persistence enabled (AOF or RDB)
- [ ] Queue workers scaled (multiple processes)
- [ ] Provider API timeouts tuned (10-30s)
- [ ] Circuit breaker thresholds calibrated
- [ ] Indexes verified with `EXPLAIN` queries
- [ ] Database statistics updated (`ANALYZE TABLE`)
- [ ] Application-level caching for config
- [ ] CDN/edge caching for webhook endpoints (if applicable)

## Monitoring Queries

### High-Volume Message Status

```sql
SELECT
    channel,
    status,
    COUNT(*) as count,
    AVG(totalAttempts) as avg_attempts
FROM bird_flock_outbound_messages
WHERE createdAt >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY channel, status;
```

### Slow Messages (Excessive Retries)

```sql
SELECT
    id_outboundMessage,
    channel,
    to,
    attempts,
    errorCode,
    errorMessage,
    createdAt
FROM bird_flock_outbound_messages
WHERE attempts >= 3
  AND status IN ('queued', 'sending')
ORDER BY createdAt DESC
LIMIT 100;
```

### Provider Performance

```sql
SELECT
    DATE(sentAt) as date,
    channel,
    COUNT(*) as total_sent,
    AVG(TIMESTAMPDIFF(SECOND, queuedAt, sentAt)) as avg_send_time_seconds
FROM bird_flock_outbound_messages
WHERE sentAt IS NOT NULL
  AND sentAt >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(sentAt), channel
ORDER BY date DESC, channel;
```

### Table Growth Rate

```sql
SELECT
    DATE(createdAt) as date,
    COUNT(*) as messages_created,
    SUM(OCTET_LENGTH(payload)) / 1024 / 1024 as payload_mb
FROM bird_flock_outbound_messages
WHERE createdAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(createdAt)
ORDER BY date DESC;
```

## Scaling Recommendations

### Up to 1M messages/day

- Single app server
- Moderate queue workers (4-8)
- Standard MySQL/PostgreSQL
- Optional Redis for cache

### 1M - 10M messages/day

- Load-balanced app servers (2-4)
- Dedicated queue workers (16-32)
- Database read replicas
- Redis cluster for cache + circuit breakers

### 10M+ messages/day

- Auto-scaling app tier
- Dedicated queue cluster (50+ workers)
- Sharded database or Aurora/Cloud SQL
- Redis Cluster with persistence
- Consider message broker (RabbitMQ/SQS) instead of database queue

## Cache Warming

Warm frequently accessed config and circuit breaker states on deployment:

```php
Artisan::command('bird-flock:cache-warm', function () {
    $services = ['twilio_sms', 'twilio_whatsapp', 'sendgrid_email', 'vonage_sms', 'mailgun_email'];

    foreach ($services as $service) {
        Cache::remember("circuit_breaker:{$service}:state", 3600, fn() => 'closed');
    }

    Cache::remember('bird-flock:config', 3600, fn() => config('bird-flock'));

    $this->info('Cache warmed');
})->purpose('Warm Bird Flock caches after deployment');
```

Add to deployment: `php artisan bird-flock:cache-warm`
