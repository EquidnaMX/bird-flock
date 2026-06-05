# Artisan Commands

Commands are registered by `BirdFlockServiceProvider` when running in console.

## Command List

- `bird-flock:config:validate`
- `bird-flock:dead-letter {action} {message_id?} {--limit=50}`
- `bird-flock:dead-letter:stats {--days=7} {--top=10}`
- `bird-flock:send-sms {to} {text?} {--idempotency=}`
- `bird-flock:send-whatsapp {to} {text?} {--media=*} {--idempotency=}`
- `bird-flock:send-email {to} {subject?} {--text=} {--html=} {--idempotency=}`

## Command Details

### bird-flock:config:validate

- Validates core/Twilio/SendGrid package config via `ConfigValidator`.
- Exit code `0` on success, `2` on validation failure exception.

Example:

```bash
php artisan bird-flock:config:validate
```

### bird-flock:dead-letter

Actions:

- `list`: list recent DLQ entries.
- `replay`: replay one entry by id.
- `purge`: delete one entry by id or all entries with confirmation.

Examples:

```bash
php artisan bird-flock:dead-letter list --limit=50
php artisan bird-flock:dead-letter replay 01H...
php artisan bird-flock:dead-letter purge 01H...
```

### bird-flock:dead-letter:stats

Shows DLQ analytics for a lookback window (`--days`) and top errors (`--top`).

Example:

```bash
php artisan bird-flock:dead-letter:stats --days=14 --top=15
```

### bird-flock:send-sms

Queues a test SMS `FlightPlan`.

Example:

```bash
php artisan bird-flock:send-sms +10000000000 "Smoke" --idempotency="smoke:sms"
```

### bird-flock:send-whatsapp

Queues a test WhatsApp `FlightPlan` (supports repeated `--media`).

Example:

```bash
php artisan bird-flock:send-whatsapp +10000000000 "Smoke" --media="https://example.com/a.jpg"
```

### bird-flock:send-email

Queues a test email `FlightPlan`.

Example:

```bash
php artisan bird-flock:send-email dev@example.com "Smoke" --text="body"
```

## Operational Note

Send commands queue messages asynchronously. Command success means queued, not delivered.
