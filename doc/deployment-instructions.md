# Deployment Instructions

This package runs inside a host Laravel application. Deployment requirements combine Bird Flock settings with host runtime concerns (DB/cache/queue/workers).

## System Requirements

- PHP: `>=8.3`.
- Laravel/Illuminate: compatible with `^10 || ^11 || ^12 || ^13`.
- Database: any host-supported Laravel driver.
- Queue worker infrastructure: required.
- Cache backend: required for circuit-breaker state (Redis recommended).
- Provider accounts as needed: Twilio, SendGrid, Vonage, Mailgun.

Package migrations create:
- `bird_flock_outbound_messages` (prefix configurable).
- `bird_flock_dead_letters` (prefix configurable).

## Environment Configuration

Core package variables:

```dotenv
BIRD_FLOCK_DEFAULT_QUEUE=default
BIRD_FLOCK_TABLE_PREFIX=bird_flock_

BIRD_FLOCK_LOGGING_ENABLED=true
BIRD_FLOCK_LOG_CHANNEL=

BIRD_FLOCK_DLQ_ENABLED=true

BIRD_FLOCK_CIRCUIT_BREAKER_FAILURE_THRESHOLD=5
BIRD_FLOCK_CIRCUIT_BREAKER_TIMEOUT=60
BIRD_FLOCK_CIRCUIT_BREAKER_SUCCESS_THRESHOLD=2

BIRD_FLOCK_MAX_PAYLOAD_SIZE=262144
BIRD_FLOCK_BATCH_INSERT_CHUNK_SIZE=500
BIRD_FLOCK_WEBHOOK_RATE_LIMIT=60
BIRD_FLOCK_HEALTH_ENABLED=true
```

Provider variables (examples):

```dotenv
# Twilio
TWILIO_ACCOUNT_SID=YOUR_TWILIO_ACCOUNT_SID
TWILIO_AUTH_TOKEN=YOUR_TWILIO_AUTH_TOKEN
TWILIO_FROM_SMS=+10000000000
TWILIO_FROM_WHATSAPP=whatsapp:+10000000000
TWILIO_MESSAGING_SERVICE_SID=
TWILIO_STATUS_WEBHOOK_URL=https://your-app.example.com/bird-flock/webhooks/twilio/status
TWILIO_SANDBOX_MODE=true
TWILIO_SANDBOX_FROM=
TWILIO_TIMEOUT=30
TWILIO_CONNECT_TIMEOUT=10

# SendGrid
SENDGRID_API_KEY=YOUR_SENDGRID_API_KEY
SENDGRID_FROM_EMAIL=no-reply@example.com
SENDGRID_FROM_NAME=YourApp
SENDGRID_REPLY_TO=support@example.com
SENDGRID_WEBHOOK_PUBLIC_KEY=YOUR_SENDGRID_PUBLIC_KEY
SENDGRID_REQUIRE_SIGNED_WEBHOOKS=true
SENDGRID_TIMEOUT=30
SENDGRID_CONNECT_TIMEOUT=10

# Vonage
VONAGE_API_KEY=YOUR_VONAGE_API_KEY
VONAGE_API_SECRET=YOUR_VONAGE_API_SECRET
VONAGE_FROM_SMS=YourBrand
VONAGE_SIGNATURE_SECRET=YOUR_VONAGE_SIGNATURE_SECRET
VONAGE_REQUIRE_SIGNED_WEBHOOKS=true
VONAGE_DELIVERY_RECEIPT_URL=https://your-app.example.com/bird-flock/webhooks/vonage/delivery-receipt
VONAGE_INBOUND_URL=https://your-app.example.com/bird-flock/webhooks/vonage/inbound
VONAGE_TIMEOUT=30

# Mailgun
MAILGUN_API_KEY=YOUR_MAILGUN_API_KEY
MAILGUN_DOMAIN=mg.example.com
MAILGUN_FROM_EMAIL=no-reply@example.com
MAILGUN_FROM_NAME=YourApp
MAILGUN_REPLY_TO=support@example.com
MAILGUN_ENDPOINT=api.mailgun.net
MAILGUN_WEBHOOK_SIGNING_KEY=YOUR_MAILGUN_SIGNING_KEY
MAILGUN_REQUIRE_SIGNED_WEBHOOKS=true
MAILGUN_WEBHOOK_URL=https://your-app.example.com/bird-flock/webhooks/mailgun/events
MAILGUN_TIMEOUT=30
```

Retry variables:

```dotenv
BIRD_FLOCK_SMS_MAX_ATTEMPTS=3
BIRD_FLOCK_SMS_BASE_DELAY_MS=1000
BIRD_FLOCK_SMS_MAX_DELAY_MS=60000

BIRD_FLOCK_WHATSAPP_MAX_ATTEMPTS=3
BIRD_FLOCK_WHATSAPP_BASE_DELAY_MS=1000
BIRD_FLOCK_WHATSAPP_MAX_DELAY_MS=60000

BIRD_FLOCK_EMAIL_MAX_ATTEMPTS=3
BIRD_FLOCK_EMAIL_BASE_DELAY_MS=1000
BIRD_FLOCK_EMAIL_MAX_DELAY_MS=60000
```

## Initial Setup Steps

1. Install package:

```bash
composer require equidna/bird-flock
```

2. Publish assets:

```bash
php artisan vendor:publish --tag=bird-flock-config
php artisan vendor:publish --tag=bird-flock-migrations
```

3. Validate config:

```bash
php artisan bird-flock:config:validate
```

4. Run migrations:

```bash
php artisan migrate
```

5. Run worker:

```bash
php artisan queue:work --queue=default
```

## Deployment Workflow

### Local

1. `composer install`
2. configure `.env`
3. `php artisan bird-flock:config:validate`
4. `php artisan migrate`
5. `php artisan queue:work --queue=default`

### Staging/Production

1. Deploy host app code.
2. `composer install --no-dev --optimize-autoloader`
3. `php artisan config:cache`
4. `php artisan migrate --force`
5. Restart queue workers.
6. Verify `/bird-flock/health`.
7. Verify provider webhook URLs.

Queue/scheduler notes:
- Required: queue workers.
- Package delayed sends use queue delay; Laravel scheduler is not required by Bird Flock itself.

## Assumptions

- Host app provides production-grade queue/cache/logging infrastructure.
- Webhook routes are reachable over HTTPS.
