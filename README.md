# Bird Flock

Lightweight message dispatching and DLQ tooling for Laravel apps. Supports SMS, WhatsApp and email channels with pluggable providers, retry/backoff, and observability hooks.

## Configuration

**Core**

- `BIRD_FLOCK_DEFAULT_QUEUE` queue connection used to dispatch jobs. Falls back to `MESSAGING_QUEUE`, then `default`.
- `BIRD_FLOCK_TABLE_PREFIX` prefix prepended to every package table name (default: `bird_flock_`). To change table names, set this prefix table names are derived from it. The package no longer uses per-table environment variables like `BIRD_FLOCK_OUTBOUND_TABLE` or `BIRD_FLOCK_DLQ_TABLE`.

**Twilio**

How Twilio sender selection works

- `TWILIO_MESSAGING_SERVICE_SID` (Messaging Service) is a Twilio-managed logical service that can own a pool of phone numbers and apply routing, number pooling, carrier selection and other features. When you provide a Messaging Service SID to Twilio, it will pick the outbound `From` for you and ignore any explicit `From` value.
- `TWILIO_FROM_SMS` / `TWILIO_FROM_WHATSAPP` are explicit sender addresses (a single phone number or WhatsApp sender). Use these when you want a fixed `From` value (common in small installs or development).

Recommendation and fallback pattern

- For production or multi-number setups prefer `TWILIO_MESSAGING_SERVICE_SID` so Twilio can manage routing and scale. For simple or sandbox setups, provide `TWILIO_FROM_SMS` and/or `TWILIO_FROM_WHATSAPP`.
- Common pattern: use the Messaging Service when present and fall back to explicit `From` values when it is not set. For WhatsApp, ensure the `whatsapp:` prefix is used (for example, `TWILIO_FROM_WHATSAPP=whatsapp:+1415...`) and that the number is provisioned.

Example `.env` pattern

```
TWILIO_MESSAGING_SERVICE_SID=MGxxxxxxxxxxxxxxxxxxxx
# or fallback to single-number mode
TWILIO_FROM_SMS=+15555550100
TWILIO_FROM_WHATSAPP=whatsapp:+1415xxxxxxx
```

Note: If both Messaging Service SID and explicit `From` are provided, the Messaging Service takes precedence.

**SendGrid**

- `SENDGRID_API_KEY` required to send email.
- `SENDGRID_FROM_EMAIL`, `SENDGRID_FROM_NAME`, `SENDGRID_REPLY_TO` envelope metadata helpers.
- `SENDGRID_REQUIRE_SIGNED_WEBHOOKS` (default `false`) and `SENDGRID_WEBHOOK_PUBLIC_KEY` controls webhook signature validation.

**Logging & Observability**

- `BIRD_FLOCK_LOGGING_ENABLED` enable/disable structured logs (default: `true`).
- `BIRD_FLOCK_LOG_CHANNEL` optional Laravel log channel to use.

**Retries & Backoff**

- Per-channel retry settings are configurable via environment variables: `BIRD_FLOCK_{SMS|WHATSAPP|EMAIL}_MAX_ATTEMPTS`, `*_BASE_DELAY_MS`, `*_MAX_DELAY_MS`.

**Dead Letters**

- `BIRD_FLOCK_DLQ_ENABLED` (default `true`) controls whether exhausted messages are persisted to the dead-letter table.
- Dead-letter table name is derived from the prefix: `<prefix>dead_letters` (for example, `bird_flock_dead_letters`).

## Quick Start

1. Install the package and publish config & migrations:

```powershell
composer require equidna/bird-flock; php artisan vendor:publish --tag=bird-flock-config; php artisan vendor:publish --tag=bird-flock-migrations
```

2. Run migrations:

```powershell
php artisan migrate
```

3. Add minimal environment variables (example `.env` snippet):

```env
BIRD_FLOCK_DEFAULT_QUEUE=messaging
BIRD_FLOCK_TABLE_PREFIX=bird_flock_
BIRD_FLOCK_DLQ_ENABLED=true
# Retry knobs
BIRD_FLOCK_SMS_MAX_ATTEMPTS=5
BIRD_FLOCK_SMS_BASE_DELAY_MS=1000
BIRD_FLOCK_SMS_MAX_DELAY_MS=60000
# Twilio
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=secret
TWILIO_FROM_SMS=+15555550100
TWILIO_STATUS_WEBHOOK_URL=https://example.com/bird-flock/webhooks/twilio/status
# SendGrid
SENDGRID_API_KEY=SG.secret
SENDGRID_FROM_EMAIL=ops@example.com
SENDGRID_REQUIRE_SIGNED_WEBHOOKS=false
```

4. Wire the published webhook routes in your `routes/web.php` (or equivalent):

```php
use Equidna\BirdFlock\Http\Controllers\SendgridWebhookController;
use Equidna\BirdFlock\Http\Controllers\TwilioWebhookController;

Route::post('/bird-flock/webhooks/twilio', TwilioWebhookController::class);
Route::post('/bird-flock/webhooks/sendgrid', SendgridWebhookController::class);
```

5. Start queue workers using the configured queue:

```powershell
php artisan queue:work --queue=messaging
```

## Dispatching Messages

Examples live in the package DTOs; a quick SMS:

```php
use Equidna\BirdFlock\BirdFlock;
use Equidna\BirdFlock\DTO\FlightPlan;

$payload = new FlightPlan(
    channel: 'sms',
    to: '+15005550006',
    text: 'Your code: 493002',
    idempotencyKey: '2fa-USER123'
);

$messageId = BirdFlock::dispatch($payload);
```

Email and WhatsApp flows support template keys, attachments, and metadata via the same `FlightPlan` DTO.

## Idempotency

This package supports idempotent message dispatching to avoid duplicate sends and to enable safe retries.

- Supply a stable key in the `FlightPlan` as `idempotencyKey` (example: `idempotencyKey: 'order:1234:shipping:sms'`).
- When `BirdFlock::dispatch()` receives a payload with an idempotency key it:
  - looks up an existing outbound message by that key;
  - if a record exists and its status is `queued`, `sending`, `sent`, or `delivered`, the existing message id is returned and no new send is created (duplicate skipped);
  - if a record exists but previously failed, the existing record is reset for retry (status reset to `queued`, attempts cleared) and the same message id is reused;
  - otherwise a new outbound message row is created and queued.

Storage and enforcement

- The `outbound_messages` table stores the `idempotencyKey` and enforces uniqueness with a database unique index; this prevents duplicate rows for the same key.
- Recommendation: build idempotency keys that include scope (tenant, account, channel or business id) to avoid accidental global collisions.

DB-race-safe behavior

- The dispatcher now implements a DB-race-safe create: when two processes attempt to create the same `idempotencyKey` concurrently, the code catches the database unique-constraint error, re-queries the repository for the existing record, and returns the existing message id. This avoids hard failures on concurrent dispatch attempts and ensures a single canonical outbound message is used.
- Note: unit tests for this concurrency behavior are recommended (mocked `QueryException` scenarios). See `todo.md` for the remaining test items and rollout notes.

Best practices

- Use stable, meaningful keys (ULID/UUID or application-level business keys). Example: `account:42:order:1234:shipping-sms`.
- For multi-channel operations include channel/recipient context in the key when appropriate.

## CLI Tools

- List DLQ entries: `php artisan bird-flock:dead-letter list --limit=25`
- Replay one entry: `php artisan bird-flock:dead-letter replay <dlq_ulid>`
- Purge DLQ (with confirmation): `php artisan bird-flock:dead-letter purge`

## CLI Examples

Here are concrete examples showing how to use the package artisan commands.

- List the most recent 25 dead-letter entries:

```powershell
php artisan bird-flock:dead-letter list --limit=25
```

- Replay a single DLQ entry by its ULID (requeues the original payload):

```powershell
php artisan bird-flock:dead-letter replay 01K9PE6YMWM9MZ6PYH24D7132C
```

- Purge all DLQ entries (interactive confirmation will be required):

```powershell
php artisan bird-flock:dead-letter purge
```

- Send a test WhatsApp message from the CLI (with optional media and idempotency key):

```powershell
php artisan bird-flock:send-whatsapp "+14155551234" "Hello from Bird Flock" --media="https://example.com/image.jpg" --idempotency="order-1234-whatsapp"
```

- Send a basic SMS test (if a `send-sms` command exists in your install) or use the `FlightPlan`-based API from your code:

```powershell
# Example using the package API (in PHP):
php -r "require 'vendor/autoload.php'; (new \Equidna\BirdFlock\DTO\FlightPlan('sms', '+15555550100', text: 'Test'))->dispatch();"
```

## Dead-Letter Queue

- When `BIRD_FLOCK_DLQ_ENABLED` is `true`, messages that exhaust retries are persisted to `<prefix>dead_letters` for inspection and replay.
- Each entry stores the serialized payload, provider error context, attempt count, and the last exception to aid triage.

## Events

The package emits domain events you can listen to:

- `MessageQueued`, `MessageSending`, `MessageRetryScheduled`, `MessageDeadLettered`, `MessageFinalized`, `WebhookReceived`.

Register listeners via Laravel's event system to build auditing, alerting, or observability pipelines.

## Operations & Troubleshooting

- Messages not sending: verify queue workers, inspect logs for `bird-flock.job.retry_scheduled` or `bird-flock.job.dead_letter`.
- Webhook 401s: validate provider signatures (`TWILIO_AUTH_TOKEN`, `SENDGRID_WEBHOOK_PUBLIC_KEY`) and ensure proxies/load-balancers are not mutating payloads.
- DLQ growth: use `php artisan bird-flock:dead-letter list` and replay/purge as appropriate.

## Extensibility & Roadmap

- The package supports provider senders via pluggable sender implementations. Additional providers can be added by implementing the package `MessageSenderInterface` and registering bindings.
- Planned: metrics export (Prometheus / OpenTelemetry) and pluggable transport middleware.

## Notes

- The package intentionally removed per-table environment variables (`BIRD_FLOCK_OUTBOUND_TABLE`, `BIRD_FLOCK_DLQ_TABLE`). Use `BIRD_FLOCK_TABLE_PREFIX` to change table names.

---

If you'd like, I can also update `config/bird-flock.php` comments or an `.env.example` file to match these docs.
