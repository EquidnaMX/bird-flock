## Bird Flock Package Notes

### Configuration

**Core**
- `BIRD_FLOCK_DEFAULT_QUEUE` (falls back to `MESSAGING_QUEUE`, then `default`) selects the queue connection Bird Flock uses to dispatch jobs.
- `BIRD_FLOCK_TABLE_PREFIX` prepends every package table; override `BIRD_FLOCK_OUTBOUND_TABLE` / `BIRD_FLOCK_DLQ_TABLE` if your schema needs custom names.

**Twilio**
- `TWILIO_ACCOUNT_SID` / `TWILIO_AUTH_TOKEN` authenticate API + webhook signatures.
- `TWILIO_FROM_SMS`, `TWILIO_FROM_WHATSAPP`, and/or `TWILIO_MESSAGING_SERVICE_SID` define the sender identity.
- `TWILIO_STATUS_WEBHOOK_URL` lets outgoing SMS/WhatsApp messages annotate progress callbacks.
- `TWILIO_SANDBOX_MODE` (default `true`) prevents WhatsApp template sends without explicit template keys while you are still testing.

**SendGrid**
- `SENDGRID_API_KEY` is required for any email send.
- `SENDGRID_FROM_EMAIL`, `SENDGRID_FROM_NAME`, `SENDGRID_REPLY_TO` control envelope metadata.
- `SENDGRID_REQUIRE_SIGNED_WEBHOOKS` (default `false`) and `SENDGRID_WEBHOOK_PUBLIC_KEY` govern webhook authentication—set both or the service provider fails fast.

**Logging & Observability**
- `BIRD_FLOCK_LOGGING_ENABLED` toggles structured channel logs; `BIRD_FLOCK_LOG_CHANNEL` can point to any Laravel logging channel.

**Retries & Backoff**
- `BIRD_FLOCK_{SMS|WHATSAPP|EMAIL}_MAX_ATTEMPTS` cap retries per channel.
- `BIRD_FLOCK_{SMS|WHATSAPP|EMAIL}_BASE_DELAY_MS` / `BIRD_FLOCK_{SMS|WHATSAPP|EMAIL}_MAX_DELAY_MS` define jittered backoff windows. Jobs read these at construction time, so deploy after changing them.

**Dead Letters**
- `BIRD_FLOCK_DLQ_ENABLED` (default `true`) controls whether exhausted jobs persist for replay.
- `BIRD_FLOCK_DLQ_TABLE` overrides the default `<prefix>dead_letters` table.

### Observability

- Message dispatches, retries, sender requests, and webhook callbacks emit structured log entries with the `bird-flock.*` namespace so alerting can be built around provider responses or repeated failures.
- The package uses a container binding (`bird-flock.logger`) so downstream applications can swap in their own PSR-3 logger/channel if desired.

## Usage

### Quick Start

1. **Install & publish assets**
    ```bash
    composer require equidna/bird-flock
    php artisan vendor:publish --tag=bird-flock-config
    php artisan vendor:publish --tag=bird-flock-migrations
    php artisan migrate
    ```
2. **Set baseline environment variables**
   ```env
   BIRD_FLOCK_DEFAULT_QUEUE=messaging
    BIRD_FLOCK_TABLE_PREFIX=bird_flock_
    BIRD_FLOCK_SMS_MAX_ATTEMPTS=5
    BIRD_FLOCK_SMS_BASE_DELAY_MS=1000
    BIRD_FLOCK_SMS_MAX_DELAY_MS=60000
    BIRD_FLOCK_DLQ_ENABLED=true
    TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
    TWILIO_AUTH_TOKEN=secret
    TWILIO_FROM_SMS=+15555550100
    TWILIO_STATUS_WEBHOOK_URL=https://example.com/bird-flock/webhooks/twilio/status
    SENDGRID_API_KEY=SG.secret
    SENDGRID_FROM_EMAIL=ops@example.com
    SENDGRID_REQUIRE_SIGNED_WEBHOOKS=false
   ```
3. **Wire the provided webhook routes**

    ```php
    use Equidna\BirdFlock\Http\Controllers\SendgridWebhookController;
    use Equidna\BirdFlock\Http\Controllers\TwilioWebhookController;

    Route::post('/bird-flock/webhooks/twilio', TwilioWebhookController::class);
    Route::post('/bird-flock/webhooks/sendgrid', SendgridWebhookController::class);
    ```

4. **Run queue workers**
    ```bash
    php artisan queue:work --queue=messaging
    ```
5. **Dispatch your first payload**

    ```php
    use Equidna\BirdFlock\BirdFlock;
    use Equidna\BirdFlock\DTO\FlightPlan;

    $messageId = BirdFlock::dispatch(
         new FlightPlan(
             channel: 'sms',
             to: '+15005550006',
             text: 'Two-factor code 493002',
             idempotencyKey: '2fa-USER123'
         )
     );
    ```

### Dispatching Messages

```php
use Equidna\BirdFlock\BirdFlock;
use Equidna\BirdFlock\DTO\FlightPlan;

// SMS with idempotency
$payload = new FlightPlan(
    channel: 'sms',
    to: '+15005550006',
    text: 'Your order #1234 shipped!',
    idempotencyKey: 'order-1234-sms'
);

$messageId = BirdFlock::dispatch($payload);
```

```php
// Email with template + attachment
$payload = new FlightPlan(
    channel: 'email',
    to: 'customer@example.com',
    subject: 'Welcome!',
    templateKey: 'welcome',
    metadata: [
        'attachments' => [
            [
                'content' => base64_encode(file_get_contents(storage_path('docs/welcome.pdf'))),
                'filename' => 'welcome.pdf',
                'type' => 'application/pdf',
            ],
        ],
    ]
);

$messageId = BirdFlock::dispatch($payload);
```

### From Controllers or Jobs

```php
namespace App\Http\Controllers;

use Equidna\BirdFlock\BirdFlock;
use Equidna\BirdFlock\DTO\FlightPlan;

final class ShippingNotificationController
{
    public function __invoke(string $orderId)
    {
        $payload = new FlightPlan(
            channel: 'whatsapp',
            to: request('phone'),
            templateKey: 'order_shipped',
            templateData: [
                'order_id' => $orderId,
                'tracking_link' => request('tracking_url'),
            ],
            idempotencyKey: "order-{$orderId}-whatsapp"
        );

        $messageId = BirdFlock::dispatch($payload);

        return response()->json(['message_id' => $messageId]);
    }
}
```

### Dynamic Payloads from Arrays

```php
$payloadData = request()->validate([
    'channel' => 'required|string|in:sms,whatsapp,email',
    'to' => 'required|string',
    'text' => 'nullable|string',
    'subject' => 'nullable|string',
    'metadata' => 'array',
]);

$payload = FlightPlan::fromArray($payloadData);

BirdFlock::dispatch($payload);
```

### Listening to Events

```php
use Equidna\BirdFlock\Events\MessageFinalized;
use Illuminate\Support\Facades\Event;

Event::listen(MessageFinalized::class, function ($event) {
    if ($event->result->status !== 'sent') {
        audit_logger()->warning('Delivery issue', [
            'message_id' => $event->messageId,
            'channel' => $event->channel,
            'error' => $event->result->errorMessage,
        ]);
    }
});
```

### CLI Tools

- List DLQ entries: `php artisan bird-flock:dead-letter list --limit=25`
- Replay one entry: `php artisan bird-flock:dead-letter replay 01K9PE6YMWM9MZ6PYH24D7132C`
- Purge all (with confirmation): `php artisan bird-flock:dead-letter purge`

### Dead-Letter Queue

- Enable via `BIRD_FLOCK_DLQ_ENABLED` (defaults to `true`). Failed jobs that exhaust their retry budget are persisted to the `bird_flock_dead_letters` table (name overridable via `BIRD_FLOCK_DLQ_TABLE`).
- Inspect and manage entries with artisan as shown above.
- Each DLQ entry retains the serialized payload, provider error context, attempt count, and the last exception so operators can triage or replay confidently.

### Domain Events

- `MessageQueued` fires whenever a payload is persisted and queued (including idempotent replays).
- `MessageSending` is emitted by each channel job right before the provider call so listeners can track attempts.
- `MessageFinalized` signals that a provider response (success/failed/undeliverable) was recorded.
- `MessageRetryScheduled` includes the channel, attempt count, and backoff delay whenever a job will be retried.
- `MessageDeadLettered` fires when a message is moved to the DLQ.
- `WebhookReceived` is dispatched for every Twilio/SendGrid callback with the provider name, logical type, and raw payload.

Register listeners via Laravel's event system (e.g., `Event::listen(MessageFinalized::class, fn ($event) => ...)`) to build custom auditing, analytics, or alerting pipelines.

### Metrics & Observability (Future Release)

- Built-in Prometheus/OpenTelemetry instrumentation is planned so installs can export counters/timers without writing custom listeners.
- Example Grafana dashboards and alerting rules will accompany the metrics release to accelerate production adoption.

### Extensibility (Future Release)

- Upcoming releases will ship a pluggable channel manager so additional providers can register without modifying core files.
- We also plan to expose transport-agnostic middleware hooks (e.g., payload transformers, compliance scanners) that run before dispatch.

## Operations Runbook

### Installation & Configuration

1. Publish and run the package migrations (this creates outbound and dead-letter tables):
    ```bash
    php artisan vendor:publish --tag=bird-flock-migrations
    php artisan migrate
    ```
2. Publish the config if you need to override defaults:
    ```bash
    php artisan vendor:publish --tag=bird-flock-config
    ```
3. Configure environment variables:
   - Core queues/tables: `BIRD_FLOCK_DEFAULT_QUEUE`, `BIRD_FLOCK_TABLE_PREFIX`, `BIRD_FLOCK_OUTBOUND_TABLE`/`BIRD_FLOCK_DLQ_TABLE` (if diverging from prefix).
   - Twilio: `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_FROM_SMS`, `TWILIO_FROM_WHATSAPP`, `TWILIO_MESSAGING_SERVICE_SID`, `TWILIO_STATUS_WEBHOOK_URL`, `TWILIO_SANDBOX_MODE`.
   - SendGrid: `SENDGRID_API_KEY`, `SENDGRID_FROM_EMAIL`, `SENDGRID_FROM_NAME`, `SENDGRID_REPLY_TO`, `SENDGRID_REQUIRE_SIGNED_WEBHOOKS`, `SENDGRID_WEBHOOK_PUBLIC_KEY`.
   - Retry knobs: `BIRD_FLOCK_{SMS|WHATSAPP|EMAIL}_MAX_ATTEMPTS`, `*_BASE_DELAY_MS`, `*_MAX_DELAY_MS`.
   - Observability: `BIRD_FLOCK_LOGGING_ENABLED`, `BIRD_FLOCK_LOG_CHANNEL`.
4. Configure provider webhooks to hit the published routes (`/bird-flock/webhooks/...`) and verify signatures with the same token/key.
5. Ensure queue workers are running (`php artisan queue:work --queue=default`). Each job uses the queue configured via `MESSAGING_QUEUE`.
6. (Optional) Register event listeners or metrics collectors for lifecycle events.

### Managing Dead Letters

- List stuck messages: `php artisan bird-flock:dead-letter list`
- Replay a specific entry: `php artisan bird-flock:dead-letter replay dlq_entry_ulid`
- Purge all DLQ entries after a large incident (with confirmation): `php artisan bird-flock:dead-letter purge`
- When replaying, the original `id_outboundMessage` is reused, so downstream systems keep continuity.
- Listening to `MessageDeadLettered` lets you push alerts/Slack notifications when new entries appear.

### Troubleshooting

- **Messages not sending**: Check queue workers, then inspect logs for `bird-flock.job.retry_scheduled` or `bird-flock.job.dead_letter`. Use the DLQ tooling to replay after resolving provider issues.
- **Webhook 401s**: Verify signatures (`TWILIO_AUTH_TOKEN`, `SENDGRID_WEBHOOK_PUBLIC_KEY`) and ensure middleware such as load balancers are not mutating the payloads.
- **Config boot failures**: Service provider validates required keys; review `.env` for missing Twilio/SendGrid credentials.
- **High retry counts**: Adjust per-channel retry config or enable provider-specific throttling upstream.
- **DLQ growth**: `php artisan bird-flock:dead-letter list` to inspect. Replay once the upstream provider is recovered, or purge if the payload is no longer valid.

### Optimization Tips

- Tune retry windows per channel: SMS traffic typically tolerates shorter delays than email; adjust `*_BASE_DELAY_MS` and `*_MAX_DELAY_MS` accordingly.
- Offload DLQ analytics by streaming `MessageDeadLettered` events into your observability platform.
- Use the domain events to build idempotency dashboards (e.g., count of `MessageRetryScheduled` per account) and proactively detect upstream degradation.
