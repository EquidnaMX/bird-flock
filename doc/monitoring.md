# Monitoring

## Logging

Package logging uses `Support\Logger` and the `bird-flock.logger` binding.

Config:

```dotenv
BIRD_FLOCK_LOGGING_ENABLED=true
BIRD_FLOCK_LOG_CHANNEL=
```

Typical log namespaces:

- `bird-flock.dispatch.*`
- `bird-flock.batch.*`
- `bird-flock.job.*`
- `bird-flock.sender.*`
- `bird-flock.webhook.*`
- `bird-flock.circuit_breaker.*`

## Health Monitoring

Endpoints:

- `GET /bird-flock/health`
- `GET /bird-flock/health/circuit-breakers`

Programmatic:

- `Services\HealthService`

Health data includes checks for DB, provider config, queue config, circuit states, and DLQ/queue metrics.

## Recommended Metrics

- Queue depth for Bird Flock queue.
- Message status transition counts.
- DLQ count and growth rate.
- Circuit breaker state counts.
- Webhook 4xx/5xx rates.

## Suggested Alerts

- Health endpoint returning `503` over threshold window.
- Any circuit stuck `open` beyond timeout window.
- DLQ growth spikes.
- Queue backlog sustained above threshold.
- Repeated webhook signature failures.

## Troubleshooting

### Messages stay queued

Check worker process health, queue backend connectivity, and queue name alignment.

### Repeated failures

Check provider credentials, sender identity config, circuit state, and DLQ error patterns.

### Webhook status not updating

Check webhook URL registration, signature secrets/keys, and webhook logs for validation failures.

## Minimal Setup Recommendation

If no monitoring stack exists yet:

1. Aggregate app logs centrally.
2. Poll `/bird-flock/health` with uptime checks.
3. Add a periodic report for DLQ count and open circuits.
