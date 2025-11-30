# API Documentation

**Bird Flock** does not expose traditional REST APIs for message creation. Instead, messages are dispatched programmatically via the `BirdFlock` facade or class within your Laravel application.

However, the package **does** expose HTTP endpoints for:

1. **Webhooks** — receiving delivery receipts and status updates from messaging providers
2. **Health Checks** — monitoring package health and circuit breaker status

All webhook endpoints are **POST** requests from external providers; they are not intended for direct consumer use.

---

## Base URL

All routes are prefixed with `/bird-flock`:

```
https://yourdomain.com/bird-flock/*
```

---

## Health Check Endpoints

### 1. General Health Check

**Endpoint**: `GET /bird-flock/health`  
**Route Name**: `bird-flock.health`  
**Controller**: `Equidna\BirdFlock\Http\Controllers\HealthCheckController@check`  
**Authentication**: None  
**Rate Limit**: None

**Purpose**: Returns basic health status and configuration summary.

**Response** (200 OK):

```json
{
  "status": "ok",
  "package": "equidna/bird-flock",
  "configured_channels": ["sms", "whatsapp", "email"],
  "queue": "default",
  "dlq_enabled": true
}
```

**Response** (503 Service Unavailable) — if critical config missing:

```json
{
  "status": "error",
  "message": "No messaging providers configured"
}
```

**Example Request**:

```bash
curl https://yourdomain.com/bird-flock/health
```

---

### 2. Circuit Breaker Status

**Endpoint**: `GET /bird-flock/health/circuit-breakers`  
**Route Name**: `bird-flock.health.circuits`  
**Controller**: `Equidna\BirdFlock\Http\Controllers\HealthCheckController@circuitBreakers`  
**Authentication**: None  
**Rate Limit**: None

**Purpose**: Returns current circuit breaker state for all providers.

**Response** (200 OK):

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

**States**:

- `closed` — Normal operation
- `open` — Circuit tripped; requests fail fast
- `half_open` — Testing recovery

**Example Request**:

```bash
curl https://yourdomain.com/bird-flock/health/circuit-breakers
```

---

## Webhook Endpoints

All webhook endpoints:

- Use **POST** method
- Require provider-specific signatures (validated by middleware/controller)
- Are rate-limited to **60 requests per minute per IP** (configurable via `BIRD_FLOCK_WEBHOOK_RATE_LIMIT`)

### 1. Twilio Status Webhook

**Endpoint**: `POST /bird-flock/webhooks/twilio/status`  
**Route Name**: `bird-flock.twilio.status`  
**Controller**: `Equidna\BirdFlock\Http\Controllers\TwilioWebhookController@status`  
**Authentication**: Twilio signature validation (if `TWILIO_AUTH_TOKEN` configured)  
**Rate Limit**: 60/min

**Purpose**: Receives SMS and WhatsApp delivery status updates from Twilio.

**Request Headers**:

```
X-Twilio-Signature: <signature>
Content-Type: application/x-www-form-urlencoded
```

**Request Body** (form data):

```
MessageSid=SM1234567890abcdef
MessageStatus=delivered
To=+1234567890
From=+0987654321
```

**Response** (200 OK):

```xml
<?xml version="1.0" encoding="UTF-8"?>
<Response />
```

**Events Dispatched**:

- `WebhookReceived` event with payload

**Notes**:

- Configure this URL in Twilio Console or API when sending messages
- Twilio signature validation uses `TWILIO_AUTH_TOKEN`

---

### 2. Twilio Inbound Webhook

**Endpoint**: `POST /bird-flock/webhooks/twilio/inbound`  
**Route Name**: `bird-flock.twilio.inbound`  
**Controller**: `Equidna\BirdFlock\Http\Controllers\TwilioWebhookController@inbound`  
**Authentication**: Twilio signature validation  
**Rate Limit**: 60/min

**Purpose**: Receives inbound SMS and WhatsApp messages.

**Request Body** (form data):

```
MessageSid=SM1234567890abcdef
From=+1234567890
To=+0987654321
Body=Hello!
```

**Response** (200 OK):

```xml
<?xml version="1.0" encoding="UTF-8"?>
<Response />
```

**Events Dispatched**:

- `WebhookReceived` event

---

### 3. SendGrid Event Webhook

**Endpoint**: `POST /bird-flock/webhooks/sendgrid/events`  
**Route Name**: `bird-flock.sendgrid.events`  
**Controller**: `Equidna\BirdFlock\Http\Controllers\SendgridWebhookController@events`  
**Authentication**: SendGrid signature verification (if `SENDGRID_REQUIRE_SIGNED_WEBHOOKS=true`)  
**Rate Limit**: 60/min

**Purpose**: Receives email event notifications (delivered, bounced, opened, clicked, etc.).

**Request Headers**:

```
X-Twilio-Email-Event-Webhook-Signature: <signature>
X-Twilio-Email-Event-Webhook-Timestamp: <timestamp>
Content-Type: application/json
```

**Request Body** (JSON array):

```json
[
  {
    "email": "recipient@example.com",
    "event": "delivered",
    "sg_message_id": "abc123.filter0001.1234.5678.0",
    "timestamp": 1638360000
  },
  {
    "email": "other@example.com",
    "event": "bounce",
    "sg_message_id": "def456.filter0001.1234.5678.0",
    "timestamp": 1638360010,
    "reason": "550 5.1.1 User unknown"
  }
]
```

**Response** (200 OK):

```json
{
  "status": "ok",
  "processed": 2
}
```

**Events Dispatched**:

- `WebhookReceived` event for each event in batch

**Notes**:

- Configure in SendGrid Dashboard → Mail Settings → Event Webhook
- Enable signature verification; add `SENDGRID_WEBHOOK_PUBLIC_KEY` to `.env`

---

### 4. Vonage Delivery Receipt Webhook

**Endpoint**: `POST /bird-flock/webhooks/vonage/delivery-receipt`  
**Route Name**: `bird-flock.vonage.dlr`  
**Controller**: `Equidna\BirdFlock\Http\Controllers\VonageWebhookController@deliveryReceipt`  
**Authentication**: Vonage signature validation (if `VONAGE_REQUIRE_SIGNED_WEBHOOKS=true`)  
**Rate Limit**: 60/min

**Purpose**: Receives SMS delivery receipts from Vonage.

**Request Body** (JSON):

```json
{
  "messageId": "0C0000001234ABCD",
  "to": "1234567890",
  "status": "delivered",
  "err-code": "0",
  "message-timestamp": "2025-11-30 10:23:45"
}
```

**Response** (200 OK):

```json
{
  "status": "ok"
}
```

**Events Dispatched**:

- `WebhookReceived` event

---

### 5. Vonage Inbound Webhook

**Endpoint**: `POST /bird-flock/webhooks/vonage/inbound`  
**Route Name**: `bird-flock.vonage.inbound`  
**Controller**: `Equidna\BirdFlock\Http\Controllers\VonageWebhookController@inbound`  
**Authentication**: Vonage signature validation  
**Rate Limit**: 60/min

**Purpose**: Receives inbound SMS messages from Vonage.

**Request Body** (JSON):

```json
{
  "messageId": "0C0000001234ABCD",
  "from": "1234567890",
  "to": "0987654321",
  "text": "Hello!",
  "type": "text",
  "message-timestamp": "2025-11-30 10:23:45"
}
```

**Response** (200 OK):

```json
{
  "status": "ok"
}
```

**Events Dispatched**:

- `WebhookReceived` event

---

### 6. Mailgun Event Webhook

**Endpoint**: `POST /bird-flock/webhooks/mailgun/events`  
**Route Name**: `bird-flock.mailgun.events`  
**Controller**: `Equidna\BirdFlock\Http\Controllers\MailgunWebhookController@events`  
**Authentication**: Mailgun signature validation (if `MAILGUN_REQUIRE_SIGNED_WEBHOOKS=true`)  
**Rate Limit**: 60/min

**Purpose**: Receives email event notifications from Mailgun (delivered, bounced, opened, clicked, etc.).

**Request Headers**:

```
Content-Type: application/x-www-form-urlencoded
```

**Request Body** (form data with JSON in `event-data` field):

```
signature=<signature>
token=<token>
timestamp=<timestamp>
event-data={"event":"delivered","message":{"headers":{"message-id":"<abc@mg.example.com>"}}}
```

**Response** (200 OK):

```json
{
  "status": "ok"
}
```

**Events Dispatched**:

- `WebhookReceived` event

**Notes**:

- Configure in Mailgun Dashboard → Sending → Webhooks
- Enable signature verification; add `MAILGUN_WEBHOOK_SIGNING_KEY` to `.env`

---

## Programmatic Dispatch (Not HTTP)

Messages are dispatched within your Laravel application code, not via HTTP API:

```php
use Equidna\BirdFlock\BirdFlock;
use Equidna\BirdFlock\DTO\FlightPlan;

// Single message
$messageId = BirdFlock::dispatch(
    new FlightPlan(
        channel: 'email',
        to: 'user@example.com',
        subject: 'Welcome!',
        body: 'Welcome to our platform.',
        idempotencyKey: 'user:123:welcome-email'
    )
);

// Batch dispatch
$messageIds = BirdFlock::dispatchBatch([
    new FlightPlan(channel: 'sms', to: '+1111111111', body: 'Message 1'),
    new FlightPlan(channel: 'sms', to: '+2222222222', body: 'Message 2'),
]);
```

See [Business Logic & Core Processes](business-logic-and-core-processes.md) for detailed dispatch flow.

---

## Error Responses

### Webhook Signature Validation Failure

**Status**: 403 Forbidden

```json
{
  "error": "Invalid signature"
}
```

### Rate Limit Exceeded

**Status**: 429 Too Many Requests

```json
{
  "message": "Too Many Attempts."
}
```

### General Webhook Error

**Status**: 500 Internal Server Error

```json
{
  "error": "Webhook processing failed"
}
```

---

## Assumptions

- **Authentication**: Webhooks rely on provider-specific signature validation; no additional authentication middleware required.
- **HTTPS Required**: All webhook URLs must use HTTPS in production for signature security.
- **Idempotency**: Webhooks may be retried by providers; controllers handle duplicate events gracefully.
- **Rate Limiting**: Default 60 requests/min per IP; adjustable via `BIRD_FLOCK_WEBHOOK_RATE_LIMIT`.

For unresolved API questions, see [Open Questions & Assumptions](open-questions-and-assumptions.md).
