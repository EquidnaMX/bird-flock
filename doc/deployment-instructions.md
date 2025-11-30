# Deployment Instructions

This guide covers local development, staging, and production deployment for the **Bird Flock** Laravel package.

---

## System Requirements

### PHP & Extensions

- **PHP Version**: 8.3 or higher
- **Required Extensions**:
  - `ext-json`
  - `ext-mbstring`
  - `ext-pdo` (for database connections)
  - `ext-curl` (for HTTP requests to provider APIs)
  - `ext-openssl` (for webhook signature validation)

### Database

- **Supported Databases**:

  - MySQL 5.7+ / MariaDB 10.3+
  - PostgreSQL 11+
  - SQLite 3.26+

- **Tables Created**:
  - `bird_flock_outbound_messages` — message log and status tracking
  - `bird_flock_dead_letters` — failed messages (if DLQ enabled)

### Queue System

- **Required**: Laravel queue driver configured and running
- **Supported Drivers**:
  - Redis (recommended for production)
  - Database
  - Amazon SQS
  - Beanstalkd
  - Sync (local development only)

### Cache

- **Recommended**: Redis or Memcached for circuit breaker state
- **Fallback**: File or database cache drivers work but are less performant

### External Services

At least one messaging provider must be configured:

- **Twilio** (SMS & WhatsApp)
- **SendGrid** (Email)
- **Vonage** (SMS)
- **Mailgun** (Email)

---

## Environment Configuration

### Core Bird Flock Settings

Add to your application's `.env`:

```dotenv
# Queue Configuration
BIRD_FLOCK_DEFAULT_QUEUE=default
MESSAGING_QUEUE=messaging  # Alternative queue name

# Table Prefix (optional)
BIRD_FLOCK_TABLE_PREFIX=bird_flock_

# Logging
BIRD_FLOCK_LOGGING_ENABLED=true
BIRD_FLOCK_LOG_CHANNEL=  # Leave empty to use default Laravel channel

# Dead-Letter Queue
BIRD_FLOCK_DLQ_ENABLED=true

# Circuit Breaker
BIRD_FLOCK_CIRCUIT_BREAKER_FAILURE_THRESHOLD=5
BIRD_FLOCK_CIRCUIT_BREAKER_TIMEOUT=60
BIRD_FLOCK_CIRCUIT_BREAKER_SUCCESS_THRESHOLD=2

# Payload Size Limits (bytes)
BIRD_FLOCK_MAX_PAYLOAD_SIZE=262144  # 256KB default
BIRD_FLOCK_BATCH_INSERT_CHUNK_SIZE=500

# Webhook Rate Limiting (requests per minute per IP)
BIRD_FLOCK_WEBHOOK_RATE_LIMIT=60
```

### Provider Credentials

#### Twilio (SMS & WhatsApp)

```dotenv
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=your_auth_token_here
TWILIO_FROM_SMS=+1234567890
TWILIO_FROM_WHATSAPP=whatsapp:+1234567890
TWILIO_MESSAGING_SERVICE_SID=  # Optional: overrides from_* values
TWILIO_STATUS_WEBHOOK_URL=https://yourdomain.com/bird-flock/webhooks/twilio/status
TWILIO_SANDBOX_MODE=false  # Set true for development
TWILIO_SANDBOX_FROM=  # Sandbox number for testing
TWILIO_TIMEOUT=30
TWILIO_CONNECT_TIMEOUT=10
```

> **Note**: If `TWILIO_MESSAGING_SERVICE_SID` is set, Twilio will select the sender automatically; `from_*` values are ignored.

#### SendGrid (Email)

```dotenv
SENDGRID_API_KEY=SG.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
SENDGRID_FROM_EMAIL=noreply@yourdomain.com
SENDGRID_FROM_NAME="Your Application"
SENDGRID_REPLY_TO=support@yourdomain.com
SENDGRID_WEBHOOK_PUBLIC_KEY=  # For signature verification
SENDGRID_REQUIRE_SIGNED_WEBHOOKS=true
SENDGRID_TIMEOUT=30
SENDGRID_CONNECT_TIMEOUT=10
```

#### Vonage (SMS)

```dotenv
VONAGE_API_KEY=your_api_key
VONAGE_API_SECRET=your_api_secret
VONAGE_FROM_SMS=YourBrand
VONAGE_SIGNATURE_SECRET=your_signature_secret
VONAGE_REQUIRE_SIGNED_WEBHOOKS=true
VONAGE_DELIVERY_RECEIPT_URL=https://yourdomain.com/bird-flock/webhooks/vonage/delivery-receipt
VONAGE_INBOUND_URL=https://yourdomain.com/bird-flock/webhooks/vonage/inbound
VONAGE_TIMEOUT=30
```

#### Mailgun (Email)

```dotenv
MAILGUN_API_KEY=your_api_key
MAILGUN_DOMAIN=mg.yourdomain.com
MAILGUN_FROM_EMAIL=noreply@yourdomain.com
MAILGUN_FROM_NAME="Your Application"
MAILGUN_REPLY_TO=support@yourdomain.com
MAILGUN_ENDPOINT=api.mailgun.net  # Use api.eu.mailgun.net for EU
MAILGUN_WEBHOOK_SIGNING_KEY=your_signing_key
MAILGUN_REQUIRE_SIGNED_WEBHOOKS=true
MAILGUN_WEBHOOK_URL=https://yourdomain.com/bird-flock/webhooks/mailgun/events
MAILGUN_TIMEOUT=30
```

### Retry Policy Configuration

Per-channel retry settings:

```dotenv
# SMS Retry Policy
BIRD_FLOCK_SMS_MAX_ATTEMPTS=3
BIRD_FLOCK_SMS_BASE_DELAY_MS=1000
BIRD_FLOCK_SMS_MAX_DELAY_MS=60000

# WhatsApp Retry Policy
BIRD_FLOCK_WHATSAPP_MAX_ATTEMPTS=3
BIRD_FLOCK_WHATSAPP_BASE_DELAY_MS=1000
BIRD_FLOCK_WHATSAPP_MAX_DELAY_MS=60000

# Email Retry Policy
BIRD_FLOCK_EMAIL_MAX_ATTEMPTS=3
BIRD_FLOCK_EMAIL_BASE_DELAY_MS=1000
BIRD_FLOCK_EMAIL_MAX_DELAY_MS=60000
```

---

## Initial Setup Steps

### 1. Install Package

In your host Laravel application:

```bash
composer require equidna/bird-flock
```

### 2. Publish Assets

```bash
# Publish configuration file
php artisan vendor:publish --tag=bird-flock-config

# Publish migrations (optional; auto-loaded by default)
php artisan vendor:publish --tag=bird-flock-migrations
```

### 3. Configure Environment

Edit `.env` with provider credentials and settings (see above).

### 4. Validate Configuration

```bash
php artisan bird-flock:config-validate
```

This command checks:

- Required credentials are present
- Table names don't conflict
- Retry policies are valid
- Circuit breaker thresholds are sensible

### 5. Run Migrations

```bash
php artisan migrate
```

Creates:

- `bird_flock_outbound_messages` table
- `bird_flock_dead_letters` table

### 6. Test Messaging (Optional)

Send test messages to verify provider integration:

```bash
# Test SMS
php artisan bird-flock:send-test-sms +1234567890

# Test WhatsApp
php artisan bird-flock:send-test-whatsapp +1234567890

# Test Email
php artisan bird-flock:send-test-email recipient@example.com
```

### 7. Start Queue Worker

**Local Development**:

```bash
php artisan queue:work --queue=default
```

**Production** (use Supervisor or similar process manager):

```ini
[program:bird-flock-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=default --tries=3 --timeout=90
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/bird-flock-worker.log
```

---

## Deployment Workflow

### Local Development

1. Clone host application repository
2. Run `composer install`
3. Copy `.env.example` to `.env`
4. Add Bird Flock credentials
5. Run `php artisan migrate`
6. Start queue worker: `php artisan queue:work`
7. Run tests: `composer test` or `./vendor/bin/phpunit`

### Staging / Production

#### Pre-Deployment Checklist

- [ ] All provider credentials configured in `.env`
- [ ] Database migrated (`php artisan migrate --force`)
- [ ] Queue workers configured (Supervisor/systemd)
- [ ] Cron scheduled for `schedule:run` (if using scheduled sends)
- [ ] Webhook URLs registered with providers
- [ ] HTTPS enforced for webhook endpoints
- [ ] Firewall rules allow outbound HTTPS to provider APIs

#### Deployment Steps

1. **Pull Latest Code**

   ```bash
   git pull origin main
   ```

2. **Install Dependencies**

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. **Run Migrations**

   ```bash
   php artisan migrate --force
   ```

4. **Clear & Cache Config**

   ```bash
   php artisan config:clear
   php artisan config:cache
   php artisan route:cache
   ```

5. **Restart Queue Workers**

   ```bash
   sudo supervisorctl restart bird-flock-worker:*
   ```

6. **Verify Health**
   ```bash
   curl https://yourdomain.com/bird-flock/health
   ```

#### Rolling Back

If issues occur:

1. Revert code to previous tag/commit
2. Run `composer install --no-dev --optimize-autoloader`
3. Rollback migrations if schema changed: `php artisan migrate:rollback`
4. Restart queue workers

---

## Queue Worker Configuration

### Recommended Supervisor Config

**File**: `/etc/supervisor/conf.d/bird-flock.conf`

```ini
[program:bird-flock-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --queue=default --sleep=3 --tries=3 --max-time=3600 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/supervisor/bird-flock-worker.log
stopwaitsecs=3600
```

**Apply Changes**:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start bird-flock-worker:*
```

### Horizon (Laravel Horizon)

If using **Laravel Horizon** for queue management, configure in `config/horizon.php`:

```php
'defaults' => [
    'supervisor-1' => [
        'connection' => 'redis',
        'queue' => ['default', 'messaging'],
        'balance' => 'auto',
        'processes' => 4,
        'tries' => 3,
        'timeout' => 90,
    ],
],
```

### Scheduled Commands (Laravel Scheduler)

If using scheduled message sends (`sendAt` in `FlightPlan`), ensure the Laravel scheduler is running:

**Crontab Entry**:

```cron
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

---

## Webhook Setup with Providers

Bird Flock includes webhook handlers for delivery receipts and status updates.

### Twilio

**Status Callback URL**:

```
https://yourdomain.com/bird-flock/webhooks/twilio/status
```

Configure in Twilio Console or via API when sending messages.

**Inbound Messages**:

```
https://yourdomain.com/bird-flock/webhooks/twilio/inbound
```

### SendGrid

**Event Webhook URL**:

```
https://yourdomain.com/bird-flock/webhooks/sendgrid/events
```

Configure in SendGrid Dashboard → Settings → Mail Settings → Event Webhook.

**Enable Signature Verification** and add public key to `.env`.

### Vonage

**Delivery Receipt URL**:

```
https://yourdomain.com/bird-flock/webhooks/vonage/delivery-receipt
```

**Inbound Message URL**:

```
https://yourdomain.com/bird-flock/webhooks/vonage/inbound
```

Configure in Vonage Dashboard → Your Applications → Webhooks.

### Mailgun

**Event Webhook URL**:

```
https://yourdomain.com/bird-flock/webhooks/mailgun/events
```

Configure in Mailgun Dashboard → Sending → Webhooks.

Enable **Signature Verification** and add signing key to `.env`.

---

## Assumptions & Notes

- **Database**: Assumes Eloquent ORM with standard Laravel migrations. Custom table names can be configured via `BIRD_FLOCK_TABLE_PREFIX`.
- **Queue Driver**: Any Laravel-supported driver works; Redis recommended for production.
- **HTTPS Required**: Webhooks must use HTTPS in production to validate signatures.
- **Timezone**: All timestamps stored in UTC (Laravel default).
- **Payload Size**: Default max 256KB; adjust `BIRD_FLOCK_MAX_PAYLOAD_SIZE` if needed (ensure queue backend supports larger payloads).

For unresolved deployment questions, see [Open Questions & Assumptions](open-questions-and-assumptions.md).
