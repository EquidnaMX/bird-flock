# Routes Documentation

This document details all HTTP routes registered by the **Bird Flock** package.

---

## Route Registration

Routes are automatically registered when the package is installed via the `BirdFlockServiceProvider`:

```php
// File: src/BirdFlockServiceProvider.php
public function boot(): void
{
    $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    // ...
}
```

Routes are loaded from `routes/web.php` and prefixed with `/bird-flock`.

---

## Route Groups

### Health Check Routes (No Rate Limit)

**Prefix**: `/bird-flock`  
**Middleware**: None

| Method | URI                                   | Route Name                   | Controller Method                       | Purpose                             |
| ------ | ------------------------------------- | ---------------------------- | --------------------------------------- | ----------------------------------- |
| GET    | `/bird-flock/health`                  | `bird-flock.health`          | `HealthCheckController@check`           | General health status               |
| GET    | `/bird-flock/health/circuit-breakers` | `bird-flock.health.circuits` | `HealthCheckController@circuitBreakers` | Circuit breaker state for providers |

---

### Webhook Routes (Rate Limited)

**Prefix**: `/bird-flock/webhooks`  
**Middleware**: `throttle:60,1` (60 requests per minute)  
**Rate Limit Configurable**: Via `BIRD_FLOCK_WEBHOOK_RATE_LIMIT` in `.env`

| Method | URI                                            | Route Name                   | Controller Method                         | Purpose                            |
| ------ | ---------------------------------------------- | ---------------------------- | ----------------------------------------- | ---------------------------------- |
| POST   | `/bird-flock/webhooks/twilio/status`           | `bird-flock.twilio.status`   | `TwilioWebhookController@status`          | Twilio delivery status updates     |
| POST   | `/bird-flock/webhooks/twilio/inbound`          | `bird-flock.twilio.inbound`  | `TwilioWebhookController@inbound`         | Twilio inbound SMS/WhatsApp        |
| POST   | `/bird-flock/webhooks/sendgrid/events`         | `bird-flock.sendgrid.events` | `SendgridWebhookController@events`        | SendGrid email event notifications |
| POST   | `/bird-flock/webhooks/vonage/delivery-receipt` | `bird-flock.vonage.dlr`      | `VonageWebhookController@deliveryReceipt` | Vonage SMS delivery receipts       |
| POST   | `/bird-flock/webhooks/vonage/inbound`          | `bird-flock.vonage.inbound`  | `VonageWebhookController@inbound`         | Vonage inbound SMS                 |
| POST   | `/bird-flock/webhooks/mailgun/events`          | `bird-flock.mailgun.events`  | `MailgunWebhookController@events`         | Mailgun email event notifications  |

---

## Route Details

### Health Check: General

**Route Definition**:

```php
Route::get('/health', [HealthCheckController::class, 'check'])
    ->name('bird-flock.health');
```

**Controller**: `Equidna\BirdFlock\Http\Controllers\HealthCheckController` (`src/Http/Controllers/HealthCheckController.php`)  
**Method**: `check`  
**Middleware**: None  
**Purpose**: Returns package health, configured channels, queue name, and DLQ status.

**Example Usage**:

```bash
curl https://yourdomain.com/bird-flock/health
```

---

### Health Check: Circuit Breakers

**Route Definition**:

```php
Route::get('/health/circuit-breakers', [HealthCheckController::class, 'circuitBreakers'])
    ->name('bird-flock.health.circuits');
```

**Controller**: `Equidna\BirdFlock\Http\Controllers\HealthCheckController`  
**Method**: `circuitBreakers`  
**Middleware**: None  
**Purpose**: Returns current state of all circuit breakers (open, closed, half-open).

**Example Usage**:

```bash
curl https://yourdomain.com/bird-flock/health/circuit-breakers
```

---

### Webhook: Twilio Status

**Route Definition**:

```php
Route::post('/webhooks/twilio/status', [TwilioWebhookController::class, 'status'])
    ->name('bird-flock.twilio.status')
    ->middleware('throttle:60,1');
```

**Controller**: `Equidna\BirdFlock\Http\Controllers\TwilioWebhookController` (`src/Http/Controllers/TwilioWebhookController.php`)  
**Method**: `status`  
**Middleware**: `throttle:60,1` (60 requests per minute per IP)  
**Purpose**: Receives SMS and WhatsApp delivery status updates from Twilio.  
**Authentication**: Validates `X-Twilio-Signature` header using `TWILIO_AUTH_TOKEN`.

**Expected Request**:

- Content-Type: `application/x-www-form-urlencoded`
- Headers: `X-Twilio-Signature`

**Response**: `200 OK` with XML `<Response />`

---

### Webhook: Twilio Inbound

**Route Definition**:

```php
Route::post('/webhooks/twilio/inbound', [TwilioWebhookController::class, 'inbound'])
    ->name('bird-flock.twilio.inbound')
    ->middleware('throttle:60,1');
```

**Controller**: `Equidna\BirdFlock\Http\Controllers\TwilioWebhookController`  
**Method**: `inbound`  
**Middleware**: `throttle:60,1`  
**Purpose**: Receives inbound SMS and WhatsApp messages.  
**Authentication**: Validates `X-Twilio-Signature`.

---

### Webhook: SendGrid Events

**Route Definition**:

```php
Route::post('/webhooks/sendgrid/events', [SendgridWebhookController::class, 'events'])
    ->name('bird-flock.sendgrid.events')
    ->middleware('throttle:60,1');
```

**Controller**: `Equidna\BirdFlock\Http\Controllers\SendgridWebhookController` (`src/Http/Controllers/SendgridWebhookController.php`)  
**Method**: `events`  
**Middleware**: `throttle:60,1`  
**Purpose**: Receives email event notifications (delivered, bounced, opened, clicked, etc.).  
**Authentication**: Validates signature using `SENDGRID_WEBHOOK_PUBLIC_KEY` if `SENDGRID_REQUIRE_SIGNED_WEBHOOKS=true`.

**Expected Request**:

- Content-Type: `application/json`
- Headers: `X-Twilio-Email-Event-Webhook-Signature`, `X-Twilio-Email-Event-Webhook-Timestamp`

**Response**: `200 OK` with JSON `{"status": "ok", "processed": <count>}`

---

### Webhook: Vonage Delivery Receipt

**Route Definition**:

```php
Route::post('/webhooks/vonage/delivery-receipt', [VonageWebhookController::class, 'deliveryReceipt'])
    ->name('bird-flock.vonage.dlr')
    ->middleware('throttle:60,1');
```

**Controller**: `Equidna\BirdFlock\Http\Controllers\VonageWebhookController` (`src/Http/Controllers/VonageWebhookController.php`)  
**Method**: `deliveryReceipt`  
**Middleware**: `throttle:60,1`  
**Purpose**: Receives SMS delivery receipts from Vonage.  
**Authentication**: Validates signature using `VONAGE_SIGNATURE_SECRET` if `VONAGE_REQUIRE_SIGNED_WEBHOOKS=true`.

**Expected Request**:

- Content-Type: `application/json`

**Response**: `200 OK` with JSON `{"status": "ok"}`

---

### Webhook: Vonage Inbound

**Route Definition**:

```php
Route::post('/webhooks/vonage/inbound', [VonageWebhookController::class, 'inbound'])
    ->name('bird-flock.vonage.inbound')
    ->middleware('throttle:60,1');
```

**Controller**: `Equidna\BirdFlock\Http\Controllers\VonageWebhookController`  
**Method**: `inbound`  
**Middleware**: `throttle:60,1`  
**Purpose**: Receives inbound SMS messages from Vonage.  
**Authentication**: Validates signature using `VONAGE_SIGNATURE_SECRET`.

---

### Webhook: Mailgun Events

**Route Definition**:

```php
Route::post('/webhooks/mailgun/events', [MailgunWebhookController::class, 'events'])
    ->name('bird-flock.mailgun.events')
    ->middleware('throttle:60,1');
```

**Controller**: `Equidna\BirdFlock\Http\Controllers\MailgunWebhookController` (`src/Http/Controllers/MailgunWebhookController.php`)  
**Method**: `events`  
**Middleware**: `throttle:60,1`  
**Purpose**: Receives email event notifications from Mailgun (delivered, bounced, opened, clicked, etc.).  
**Authentication**: Validates signature using `MAILGUN_WEBHOOK_SIGNING_KEY` if `MAILGUN_REQUIRE_SIGNED_WEBHOOKS=true`.

**Expected Request**:

- Content-Type: `application/x-www-form-urlencoded`
- Form fields: `signature`, `token`, `timestamp`, `event-data` (JSON string)

**Response**: `200 OK` with JSON `{"status": "ok"}`

---

## Route Middleware Summary

| Middleware      | Applied To               | Purpose                                     |
| --------------- | ------------------------ | ------------------------------------------- |
| None            | `/bird-flock/health/*`   | Public health checks, no restrictions       |
| `throttle:60,1` | `/bird-flock/webhooks/*` | Rate limit to 60 requests per minute per IP |

**Rate Limit Configuration**:  
Adjust via `.env`:

```dotenv
BIRD_FLOCK_WEBHOOK_RATE_LIMIT=60
```

Then modify route registration if custom logic needed (package uses Laravel's built-in `throttle` middleware).

---

## Generating Route URLs in Application Code

Use Laravel's `route()` helper:

```php
// Health check
$healthUrl = route('bird-flock.health');
// https://yourdomain.com/bird-flock/health

// Twilio status webhook
$twilioStatusUrl = route('bird-flock.twilio.status');
// https://yourdomain.com/bird-flock/webhooks/twilio/status

// SendGrid events webhook
$sendgridEventsUrl = route('bird-flock.sendgrid.events');
// https://yourdomain.com/bird-flock/webhooks/sendgrid/events
```

---

## Integration with Host Application

### No Namespace Conflicts

Bird Flock routes are prefixed with `/bird-flock` to avoid conflicts with host application routes.

### Custom Route Registration (Advanced)

If you need to customize route registration (e.g., different prefix, additional middleware), you can:

1. **Disable auto-loading** by not using the package's service provider route registration.
2. **Manually register routes** in your host application's `routes/web.php` or `routes/api.php`:

```php
Route::prefix('custom-prefix')->group(function () {
    Route::get('/health', [\Equidna\BirdFlock\Http\Controllers\HealthCheckController::class, 'check']);
    // Add other routes as needed
});
```

> **Note**: This is not recommended unless you have specific routing requirements.

---

## Assumptions

- **HTTPS Required**: All webhook URLs must use HTTPS in production for signature validation.
- **Rate Limiting**: Default 60 requests/min per IP; providers may retry failed webhooks, so ensure rate limits accommodate retry bursts.
- **Signature Validation**: All webhook controllers validate provider signatures; configure secrets/keys in `.env`.
- **No Authentication Middleware**: Webhooks rely on signature validation, not Laravel's `auth` middleware.

For unresolved routing questions, see [Open Questions & Assumptions](open-questions-and-assumptions.md).
