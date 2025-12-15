# Bird Flock — Multi-Channel Messaging Bus

**Bird Flock** is a Laravel package for reliable, multi-channel outbound messaging (SMS, WhatsApp, Email) with built-in idempotency, dead-letter queue support, circuit breakers, and provider abstraction.

This documentation follows the project's Coding Standards and PHPDoc Style Guide.

---

## Project Overview

**Bird Flock** orchestrates outbound message delivery across multiple channels and providers:

- **SMS**: Twilio, Vonage (Nexmo)
- **WhatsApp**: Twilio
- **Email**: SendGrid, Mailgun

### Key Features

- **Idempotency**: Prevent duplicate sends via configurable idempotency keys
- **Dead-Letter Queue (DLQ)**: Capture and replay failed messages
- **Circuit Breakers**: Automatically suspend failing providers
- **Queue-Based**: Asynchronous delivery with configurable retry policies
- **Webhook Handlers**: Process delivery receipts and status updates from providers
- **Multi-Provider Support**: Seamlessly switch or extend providers
- **Rate Limiting**: Built-in webhook rate limiting
- **Monitoring**: Health checks and circuit breaker status endpoints

### Primary Use Cases

- Transactional notifications (OTPs, receipts, alerts)
- Marketing campaigns (scheduled bulk sends)
- Multi-channel user engagement
- Reliable messaging with automatic retries and failure tracking

---

## Project Type & Tech Summary

**Type**: Laravel Package (Library)

**Tech Stack**:

- **PHP**: 8.3+
- **Laravel Framework**: 10.x–12.x (supports Illuminate components 10.x–12.x)
- **Database**: MySQL, PostgreSQL, SQLite (Eloquent-based; driver-agnostic)
- **Cache**: Any Laravel-supported driver (Redis, Memcached, File, etc.)
- **Queue**: Any Laravel queue driver (Redis, Database, SQS, Beanstalkd, etc.)
- **External Services**:
  - Twilio SDK (`twilio/sdk ^6.0`)
  - SendGrid (`sendgrid/sendgrid ^7.0`)
  - Vonage Client (`vonage/client ^4.2`)
  - Mailgun PHP (`mailgun/mailgun-php ^4.3`)
  - Guzzle HTTP (`guzzlehttp/guzzle ^7.0`)

---

## Quick Start

### 1. Install via Composer

```bash
composer require equidna/bird-flock
```

### 2. Publish Configuration and Migrations

```bash
php artisan vendor:publish --tag=bird-flock-config
php artisan vendor:publish --tag=bird-flock-migrations
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Configure Environment

Add provider credentials to `.env`:

```dotenv
# Twilio (SMS & WhatsApp)
TWILIO_ACCOUNT_SID=your_account_sid
TWILIO_AUTH_TOKEN=your_auth_token
TWILIO_FROM_SMS=+1234567890
TWILIO_FROM_WHATSAPP=whatsapp:+1234567890

# SendGrid (Email)
SENDGRID_API_KEY=your_api_key
SENDGRID_FROM_EMAIL=noreply@example.com
SENDGRID_FROM_NAME="Example App"

# Vonage (SMS)
VONAGE_API_KEY=your_api_key
VONAGE_API_SECRET=your_api_secret
VONAGE_FROM_SMS=YourBrand

# Mailgun (Email)
MAILGUN_API_KEY=your_api_key
MAILGUN_DOMAIN=mg.example.com
MAILGUN_FROM_EMAIL=noreply@example.com
```

### 5. Start Queue Worker

```bash
php artisan queue:work --queue=default
```

### 6. Dispatch Your First Message

#### Option A: Using FlightPlan (Direct Message)

```php
use Equidna\BirdFlock\BirdFlock;
use Equidna\BirdFlock\DTO\FlightPlan;

$plan = new FlightPlan(
    channel: 'sms',
    to: '+1234567890',
    text: 'Hello from Bird Flock!',
    idempotencyKey: 'user:123:welcome-sms'
);

$messageId = BirdFlock::dispatch($plan);
```

#### Option B: Using Laravel Mailables (Email)

```php
use Equidna\BirdFlock\BirdFlock;
use App\Mail\WelcomeEmail;

// Create your Laravel Mailable as usual
$mailable = new WelcomeEmail($user);

// Dispatch it through Bird Flock
$messageId = BirdFlock::dispatchMailable(
    mailable: $mailable,
    to: 'user@example.com',
    idempotencyKey: 'user:123:welcome-email'
);
```

**Benefits of using Mailables:**

- Use familiar Laravel Mailable classes
- Leverage Blade templates and view rendering
- Automatic HTML-to-text conversion
- Support for attachments
- Full idempotency and retry logic
- Dead-letter queue support

### 7. Monitor Health Status

**For Centralized Dashboards**:

```php
use Equidna\BirdFlock\Services\HealthService;

class DashboardController extends Controller
{
    public function __construct(private HealthService $healthService)
    {
    }

    public function index()
    {
        // Get complete health status
        $health = $this->healthService->getHealthStatus();

        // Check if system is healthy
        if ($health['status'] !== 'healthy') {
            // Alert on degraded status
        }

        return view('dashboard', compact('health'));
    }
}
```

**Or via HTTP endpoint**:

```bash
curl https://yourdomain.com/bird-flock/health
```

See the [Health API Integration Guide](doc/health-api-integration.md) for complete dashboard integration examples.

---

## Documentation Index

Detailed guides and references:

- **[Deployment Instructions](doc/deployment-instructions.md)**  
  Environment setup, system requirements, configuration, deployment workflows

- **[API Documentation](doc/api-documentation.md)**  
  Public HTTP API endpoints, request/response formats

- **[Routes Documentation](doc/routes-documentation.md)**  
  All HTTP routes (webhooks, health checks), middleware, and route registration

- **[Artisan Commands](doc/artisan-commands.md)**  
  Custom CLI commands for testing, dead-letter management, and config validation

- **[Tests Documentation](doc/tests-documentation.md)**  
  Test suite structure, how to run tests, coverage overview

- **[Architecture Diagrams](doc/architecture-diagrams.md)**  
  System context, container, and component diagrams (Mermaid)

- **[Monitoring](doc/monitoring.md)**  
  Logging, metrics, health checks, troubleshooting, recommended alerts

- **[Health API Integration](doc/health-api-integration.md)**  
  Guide for integrating Bird Flock health monitoring into centralized dashboards

- **[Business Logic & Core Processes](doc/business-logic-and-core-processes.md)**  
  Message dispatch flow, idempotency, retry logic, dead-letter handling, webhook processing

- **[Open Questions & Assumptions](doc/open-questions-and-assumptions.md)**  
  Unresolved items and clarifications needed from maintainers

---

## License

MIT License. See `LICENSE` file for details.

---

## Author

**Gabriel Ruelas** — [gruelas@gruelas.com](mailto:gruelas@gruelas.com)
