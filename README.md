# Bird Flock

Lightweight message dispatching and DLQ tooling for Laravel apps. Supports SMS, WhatsApp and email channels with pluggable providers, retry/backoff, and observability hooks.

## Configuration

### CSRF Exemption (Required)

**Important:** You must exclude Bird Flock webhook routes from CSRF protection in your application.

Add the following to your `app/Http/Middleware/VerifyCsrfToken.php`:

```php
protected $except = [
    'bird-flock/webhooks/*',
];
```

This is required because Twilio and SendGrid webhooks cannot include CSRF tokens. Security is ensured through cryptographic signature validation instead (HMAC-SHA1 for Twilio, Ed25519 for SendGrid).

**Core**

- `BIRD_FLOCK_DEFAULT_QUEUE` queue connection used to dispatch jobs. Falls back to `MESSAGING_QUEUE`, then `default`.
- `BIRD_FLOCK_TABLE_PREFIX` prefix prepended to every package table name (default: `bird_flock_`). To change table names, set this prefix table names are derived from it. The package no longer uses per-table environment variables like `BIRD_FLOCK_OUTBOUND_TABLE` or `BIRD_FLOCK_DLQ_TABLE`.

**Twilio**

How Twilio sender selection works

- `TWILIO_MESSAGING_SERVICE_SID` (Messaging Service) is a Twilio-managed logical service that can own a pool of phone numbers and apply routing, number pooling, carrier selection and other features. When you provide a Messaging Service SID to Twilio, it will pick the outbound `From` for you and ignore any explicit `From` value.
- `TWILIO_FROM_SMS` / `TWILIO_FROM_WHATSAPP` are explicit sender addresses (a single phone number or WhatsApp sender). Use these when you want a fixed `From` value (common in small installs or development).

Sandbox mode

- `TWILIO_SANDBOX_MODE` enables softer validation for WhatsApp templates and can be used to allow free-text test sends in development.
- `TWILIO_SANDBOX_FROM` (optional) forces the `From` used while sandbox mode is enabled; useful for pinned sandbox numbers or provider test harnesses.

Sandbox details and examples

- When `TWILIO_SANDBOX_MODE` is enabled the package will relax template enforcement and may infer the sandbox `From` address if `TWILIO_SANDBOX_FROM` is not configured. This is convenient for local/dev use where you may only have a single test number.
- Use `TWILIO_SANDBOX_FROM` to explicitly pin the number used in sandbox mode. For WhatsApp ensure the value contains the `whatsapp:` prefix (for example `whatsapp:+15550000001`). The package will auto-prefix an un-prefixed value when necessary, but it's clearer to set the canonical value.
- Recommended sandbox workflow:
  - In CI/local dev enable `TWILIO_SANDBOX_MODE=true`.
  - Set `TWILIO_SANDBOX_FROM` when you want a stable sandbox number, or omit it to have the package infer the `From` from the configured `TWILIO_FROM_SMS` / `TWILIO_FROM_WHATSAPP`.
  - Keep sandbox mode disabled in production to ensure template enforcement and stricter validation.

Examples:

```env
# Sandbox - infer from configured from value
TWILIO_SANDBOX_MODE=true
TWILIO_SANDBOX_FROM=

# Sandbox - explicit pinned sandbox from (WhatsApp requires prefix)
TWILIO_SANDBOX_MODE=true
TWILIO_SANDBOX_FROM=whatsapp:+15550000001
```

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

Metrics Integration (binding a backend)

Bird Flock ships with a default no‑op metrics collector that logs increments. To integrate a real backend (Prometheus, StatsD, OTEL), bind your implementation of
`\Equidna\BirdFlock\Contracts\MetricsCollectorInterface` in your application service provider:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Equidna\BirdFlock\Contracts\MetricsCollectorInterface;

final class BirdFlockMetricsServiceProvider extends ServiceProvider
{
  public function register(): void
  {
    $this->app->bind(MetricsCollectorInterface::class, function () {
      // Example StatsD/Prometheus adapter
      return new class implements MetricsCollectorInterface {
        public function increment(string $metric, int $by = 1, array $tags = []): void
        {
          // Forward to your metrics backend.
          // For example (pseudo-code):
          // statsd()->increment($metric, $by, $tags);
          // or otel()->counter($metric)->add($by, $tags);
        }
      };
    });
  }
}
```

Metrics emitted by the dispatcher include:

- `bird_flock.duplicate_skipped` with tag `channel`
- `bird_flock.create_conflict` with tag `channel`

**Retries & Backoff**

- Per-channel retry settings are configurable via environment variables: `BIRD_FLOCK_{SMS|WHATSAPP|EMAIL}_MAX_ATTEMPTS`, `*_BASE_DELAY_MS`, `*_MAX_DELAY_MS`.

**Dead Letters**

- `BIRD_FLOCK_DLQ_ENABLED` (default `true`) controls whether exhausted messages are persisted to the dead-letter table.
- Dead-letter table name is derived from the prefix: `<prefix>dead_letters` (for example, `bird_flock_dead_letters`).

## Performance Characteristics

- Throughput (indicative, per worker):
  - SMS/WhatsApp (Twilio): 50–100 msgs/sec depending on content and carrier latency
  - Email (SendGrid): 30–80 msgs/sec depending on template size and API rate
- Latency profiles:
  - Queue dispatch: < 10 ms
  - Twilio send: 100–500 ms typical
  - SendGrid send: 200–800 ms typical
- Scaling guidance:
  - Scale workers linearly with volume; monitor `queue:work` lag and queue depth
  - Use Redis as the queue backend in production for predictable latency
  - Enable circuit breaker (default thresholds) to avoid cascading timeouts during provider incidents

### Circuit Breaker Configuration

Add to `config/bird-flock.php`:

```php
'circuit_breaker' => [
    'failure_threshold' => env('BIRD_FLOCK_CIRCUIT_BREAKER_FAILURE_THRESHOLD', 5),
    'timeout' => env('BIRD_FLOCK_CIRCUIT_BREAKER_TIMEOUT', 60),
    'success_threshold' => env('BIRD_FLOCK_CIRCUIT_BREAKER_SUCCESS_THRESHOLD', 2),
],
```

Environment overrides:

```env
BIRD_FLOCK_CIRCUIT_BREAKER_FAILURE_THRESHOLD=5
BIRD_FLOCK_CIRCUIT_BREAKER_TIMEOUT=60
BIRD_FLOCK_CIRCUIT_BREAKER_SUCCESS_THRESHOLD=2
```

The health check endpoint includes circuit states for Twilio SMS/WhatsApp and SendGrid.

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

- Use structured stable keys combining tenant/account + domain entity + purpose + optional channel.
  - Examples: `tenant:42:order:1234:shipping-sms`, `acct:7:otp:2025-11-28:+15555550100`, `store:9:email:welcome:customer-557`.
- Use lowercase with colon or dash delimiters for readability; avoid spaces.
- Keep total length reasonable (<160 chars) to simplify indexing and logging.
- For multi-channel operations include channel/recipient context when appropriate.
- Prefer business identifiers over raw ULIDs unless collision domain is unclear.

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

- Send a WhatsApp message with multiple media attachments and a stable idempotency key (repeat `--media`):

```powershell
php artisan bird-flock:send-whatsapp "+14155551234" "Hi with attachments" --media="https://example.com/image.jpg" --media="https://example.com/file.pdf" --idempotency="order-1234-whatsapp"
```

- Send a basic SMS test:

```powershell
php artisan bird-flock:send-sms "+14155551234" "Your OTP is 1234"
```

- Send an SMS with idempotency key to deduplicate OTP retries:

```powershell
php artisan bird-flock:send-sms "+14155551234" "Your OTP is 1234" --idempotency="otp-2025-03-01-1234"
```

- Send a simple text email:

```powershell
php artisan bird-flock:send-email "to@example.com" --text="Plain text body"
```

- Send an email with both HTML and text plus an idempotency key:

```powershell
php artisan bird-flock:send-email "to@example.com" --text="Plain" --html="<p>Hello</p>" --idempotency="welcome-2025-03-01"
```

- (Alternative) Use the `FlightPlan` API from inline PHP for ad‑hoc testing:

```powershell
php -r "require 'vendor/autoload.php'; $p=new \Equidna\BirdFlock\DTO\FlightPlan(channel: 'sms', to: '+15555550100', text: 'Test', idempotencyKey: 'demo-1'); \Equidna\BirdFlock\BirdFlock::dispatch($p);"
```

## Dead-Letter Queue

- When `BIRD_FLOCK_DLQ_ENABLED` is `true`, messages that exhaust retries are persisted to `<prefix>dead_letters` for inspection and replay.
- Each entry stores the serialized payload, provider error context, attempt count, and the last exception to aid triage.
- Caution: replaying a dead-letter entry will re-attempt provider sends; ensure idempotency keys are stable to avoid duplicate downstream side effects.

## Events

The package emits domain events you can listen to:

- `MessageQueued`, `MessageSending`, `MessageRetryScheduled`, `MessageDeadLettered`, `MessageFinalized`, `WebhookReceived`.

Register listeners via Laravel's event system to build auditing, alerting, or observability pipelines.

## Operations & Troubleshooting

- Messages not sending: verify queue workers, inspect logs for `bird-flock.job.retry_scheduled` or `bird-flock.job.dead_letter`.
- Webhook 401s: validate provider signatures (`TWILIO_AUTH_TOKEN`, `SENDGRID_WEBHOOK_PUBLIC_KEY`) and ensure proxies/load-balancers are not mutating payloads.
- DLQ growth: use `php artisan bird-flock:dead-letter list` and replay/purge as appropriate.

### Config Validation

- Command: `php artisan bird-flock:config:validate` (package command).
- Purpose: run the package's boot-time validators (Twilio, SendGrid, core settings) without starting the app. Useful in CI or deployment checks.
- What it checks:
  - Twilio: `TWILIO_ACCOUNT_SID` and `TWILIO_AUTH_TOKEN` presence; warns if neither `TWILIO_MESSAGING_SERVICE_SID` nor `TWILIO_FROM_SMS` are configured; warns when WhatsApp `TWILIO_FROM_WHATSAPP` is missing (different guidance in sandbox mode); validates `TWILIO_SANDBOX_FROM` format.
  - SendGrid: `SENDGRID_API_KEY` presence; if webhook signing is required verifies that `SENDGRID_WEBHOOK_PUBLIC_KEY` is set; warns when `SENDGRID_FROM_EMAIL` is missing or invalid.
  - Core: `BIRD_FLOCK_TABLE_PREFIX` presence and `BIRD_FLOCK_DEFAULT_QUEUE` recommendation.
- Exit codes and behavior:
  - Exit code `0` when validation passes (only informational warnings may be emitted).
  - Non-zero exit code when required credentials are missing (for example Twilio or SendGrid credentials), which makes it suitable for CI gate checks.
- Example (CI) usage:

```powershell
# Run config validation in CI and fail the job if invalid
php artisan bird-flock:config:validate; if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
```

- Suggested actions on failures:
  - Add missing environment variables (`TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `SENDGRID_API_KEY`).
  - For SendGrid webhook signing, supply `SENDGRID_WEBHOOK_PUBLIC_KEY` when `SENDGRID_REQUIRE_SIGNED_WEBHOOKS` is enabled.
  - Configure `BIRD_FLOCK_TABLE_PREFIX` and `BIRD_FLOCK_DEFAULT_QUEUE` to match your app's conventions.

If your environment cannot provide the credentials (for example during certain CI stages), consider running validation only in the deploy pipeline or toggle stricter checks via a `BIRD_FLOCK_STRICT_CONFIG` flag (not set by default).

## Extensibility & Roadmap

- The package supports provider senders via pluggable sender implementations. Additional providers can be added by implementing the package `MessageSenderInterface` and registering bindings.
- Planned: metrics export (Prometheus / OpenTelemetry) and pluggable transport middleware.

## Notes

- The package intentionally removed per-table environment variables (`BIRD_FLOCK_OUTBOUND_TABLE`, `BIRD_FLOCK_DLQ_TABLE`). Use `BIRD_FLOCK_TABLE_PREFIX` to change table names.

---

If you'd like, I can also update `config/bird-flock.php` comments or an `.env.example` file to match these docs.
