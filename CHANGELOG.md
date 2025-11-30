# Changelog

All notable changes to **Bird Flock** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

**IMPORTANT**: This changelog is the authoritative record of all changes. AI agents and contributors MUST respect and update this file for all future releases.

---

## [1.0.0] - 2025-11-30 - "Phoenix"

### 🎉 Initial Stable Release

First stable release of Bird Flock, a production-ready multi-channel messaging bus for Laravel applications.

**Codename**: Phoenix — Symbolizing the birth of a robust, reliable messaging system that rises to handle enterprise-grade communications.

### ✨ Core Features

#### Multi-Channel Support
- **SMS**: Twilio and Vonage (Nexmo) provider support
- **WhatsApp**: Twilio WhatsApp Business API integration
- **Email**: SendGrid and Mailgun provider support
- Provider abstraction layer for easy extension

#### Reliability & Fault Tolerance
- **Idempotency**: Prevent duplicate message sends with configurable idempotency keys
- **Circuit Breakers**: Automatic provider failure detection and fail-fast behavior
  - Configurable failure threshold (default: 5 failures)
  - Configurable timeout period (default: 60 seconds)
  - Half-open state for recovery testing
  - Per-provider circuit isolation
- **Dead-Letter Queue (DLQ)**: Capture permanently failed messages for manual intervention
  - Automatic DLQ entry creation after max retry attempts
  - CLI commands for DLQ inspection and replay
  - Detailed error context and metadata
- **Exponential Backoff**: Intelligent retry strategy with jitter
  - Per-channel retry policies (SMS, WhatsApp, Email)
  - Configurable max attempts (default: 3)
  - Configurable delay bounds (default: 1s–60s)

#### Queue-Based Architecture
- Asynchronous message processing via Laravel queues
- Support for all Laravel queue drivers (Redis, Database, SQS, etc.)
- Channel-specific job routing (SendSmsJob, SendWhatsappJob, SendEmailJob)
- Scheduled message delivery via `sendAt` parameter
- Batch dispatch support with chunked database inserts (500 records per chunk)

#### Webhook Processing
- **Twilio**: SMS and WhatsApp delivery status webhooks, inbound message handling
- **SendGrid**: Email event webhooks (delivered, bounced, opened, clicked, etc.)
- **Vonage**: SMS delivery receipts and inbound message webhooks
- **Mailgun**: Email event webhooks (delivered, failed, opened, clicked, etc.)
- Signature validation for all webhook endpoints
- Rate limiting (60 requests/minute per IP, configurable)
- Automatic message status updates from provider callbacks

#### Developer Experience
- **Artisan Commands**:
  - `bird-flock:config-validate` — Validate package configuration
  - `bird-flock:dead-letter` — Manage dead-letter queue (list, replay, purge)
  - `bird-flock:dead-letter-stats` — View DLQ statistics
  - `bird-flock:send-test-sms` — Send test SMS
  - `bird-flock:send-test-whatsapp` — Send test WhatsApp message
  - `bird-flock:send-test-email` — Send test email
- **Health Check Endpoints**:
  - `GET /bird-flock/health` — General package health
  - `GET /bird-flock/health/circuit-breakers` — Circuit breaker status
- **Events**: Comprehensive event system for extensibility
  - `MessageQueued`, `MessageSending`, `MessageFinalized`
  - `MessageDeadLettered`, `MessageDuplicateSkipped`, `MessageCreateConflict`
  - `MessageRetryScheduled`, `WebhookReceived`

#### Observability
- **Structured Logging**: PII-masked logs with contextual data
- **Metrics Collection**: Built-in `MetricsCollectorInterface` for custom integrations
- **PII Masking**: Automatic masking of phone numbers and email addresses in logs
- **Configuration Validation**: Boot-time validation with clear error messages

#### Security
- **Webhook Signature Validation**: Cryptographic verification for all providers
  - Twilio: HMAC-SHA1 signature validation
  - SendGrid: ECDSA public key verification
  - Vonage: HMAC-SHA256 signature validation
  - Mailgun: HMAC-SHA256 signature validation
- **Rate Limiting**: Webhook endpoint protection against abuse
- **HTTPS Enforcement**: Webhook endpoints require HTTPS in production
- **Secure Credential Storage**: All API keys stored in environment variables

### 📦 Package Structure

#### Core Components
- **BirdFlock**: Main facade for message dispatch
- **FlightPlan**: DTO for message payload definition
- **ProviderSendResult**: DTO for provider API responses
- **OutboundMessage**: Eloquent model for message persistence
- **DeadLetterEntry**: Eloquent model for failed message tracking

#### Infrastructure
- **Jobs**: Channel-specific send jobs with automatic retry handling
- **Senders**: Provider-specific API integrations
- **Repositories**: Eloquent-based persistence layer with interface abstraction
- **Support Classes**: Circuit breakers, backoff strategies, payload normalization
- **HTTP Controllers**: Webhook handlers and health check endpoints
- **Middleware**: Rate limiting for webhook endpoints
- **Console Commands**: CLI tools for operations and testing

### 📚 Documentation

Comprehensive documentation included:
- **Deployment Instructions**: Complete setup and production deployment guide
- **API Documentation**: HTTP endpoints reference
- **Routes Documentation**: All registered routes and middleware
- **Artisan Commands**: CLI reference with examples
- **Architecture Diagrams**: Mermaid diagrams for system architecture
- **Business Logic**: Core processes and domain rules
- **Monitoring**: Logging, metrics, health checks, and troubleshooting
- **Tests Documentation**: Test suite structure and coverage
- **Open Questions**: Assumptions and items requiring clarification

### 🧪 Testing

- **Unit Tests**: Comprehensive unit test coverage (~75-85%)
  - Core dispatch logic and idempotency
  - Circuit breaker behavior and concurrency
  - Job processing and retry logic
  - Provider sender implementations
  - Webhook processing and signature validation
  - Support utilities (backoff, normalization, validation)
- **Test Framework**: PHPUnit 10.x
- **Testing Standards**: Follows TestingScope.instructions.md (unit tests only)

### 🔧 Configuration

#### Environment Variables
- Provider credentials (Twilio, SendGrid, Vonage, Mailgun)
- Queue configuration
- Dead-letter queue toggle
- Circuit breaker thresholds
- Retry policies per channel
- Webhook rate limiting
- Logging configuration
- Payload size limits

#### Provider-Specific Settings
- Twilio: Sandbox mode, messaging service SID, status callback URLs
- SendGrid: Webhook signature verification, reply-to addresses
- Vonage: Signature validation, delivery receipt URLs
- Mailgun: Webhook signing keys, API endpoints (US/EU)

### 🐛 Bug Fixes

#### Job Delay Calculation Fix
- Fixed `DispatchMessageJob` to properly convert millisecond delays to seconds
- Prevents job scheduling errors when using exponential backoff with Laravel's queue delay mechanism
- Ensures retry delays are correctly applied (minimum 1 second)

### 💅 Code Quality

#### PHPDoc Standards
- Complete file-level DocBlocks for all 48+ PHP files in `src/`
- Aligned PHPDoc tags following PHPDocStyle.instructions.md
- Added missing `@throws` documentation for all public methods
- Fixed constructor DocBlocks for promoted properties
- Removed redundant `@param` documentation from constructors

#### Code Style
- Consistent trailing commas in multi-line arrays and parameter lists
- Anonymous class spacing normalization
- Empty constructor body formatting fixes
- PHPStan compliance improvements (26 → 13 errors)

### 🔒 Security Enhancements

- **Webhook Signature Validation**: Enabled by default for all providers
  - `SENDGRID_REQUIRE_SIGNED_WEBHOOKS=true` (default)
  - `VONAGE_REQUIRE_SIGNED_WEBHOOKS=true` (default)
  - `MAILGUN_REQUIRE_SIGNED_WEBHOOKS=true` (default)
- **PII Masking**: Automatic masking in all log statements
- **Circuit Breaker Cache**: Replaced `Cache::forever()` with 24-hour TTL to prevent cache bloat

### 📋 Requirements

- **PHP**: 8.3 or higher
- **Laravel Framework**: 11.x
- **Illuminate Components**: 10.x, 11.x, or 12.x
- **Database**: MySQL 5.7+, PostgreSQL 11+, or SQLite 3.26+
- **Cache**: Any Laravel-supported driver (Redis recommended for production)
- **Queue**: Any Laravel queue driver (Redis recommended for production)

### 🚀 Performance

- **Batch Insert Chunking**: Large batch dispatches automatically chunked (500 records per transaction)
- **Lazy Loading**: Minimal boot-time overhead
- **Fast Unit Tests**: Average test runtime < 100ms per test
- **Circuit Breaker Caching**: State stored in cache for fast availability checks

### 🔮 Future Considerations

See `doc/open-questions-and-assumptions.md` for:
- Multi-tenancy support strategies
- Multi-provider routing and failover
- Message cancellation for scheduled sends
- Rate limiting per recipient
- Idempotency key expiration policies
- Template management for dynamic content
- GDPR/PII compliance helpers

### 📦 Dependencies

#### Production
- `php: >=8.3`
- `laravel/framework: ^11.0`
- `illuminate/*: ^10 || ^11 || ^12` (support, queue, events, http, config, routing)
- `symfony/uid: ^7.0`
- `psr/log: 3.0`
- `guzzlehttp/guzzle: ^7.0`
- `twilio/sdk: ^6.0`
- `sendgrid/sendgrid: ^7.0`
- `vonage/client: ^4.2`
- `mailgun/mailgun-php: ^4.3`

#### Development
- `phpunit/phpunit: ^10.0`
- `squizlabs/php_codesniffer: ^4.0`
- `illuminate/database: ^11.0`
- `phpstan/phpstan: ^1.10`
- `phpstan/extension-installer: ^1.3`
- `phpstan/phpstan-phpunit: ^1.3`

### 🙏 Credits

**Author**: Gabriel Ruelas <gruelas@gruelas.com>

**Organization**: Equidna

**License**: MIT

---

## Release Notes Format for Future Releases

All future releases MUST follow this structure:

```markdown
## [X.Y.Z] - YYYY-MM-DD - "Codename"

### Added
- New features

### Changed
- Changes to existing functionality

### Deprecated
- Features marked for future removal

### Removed
- Features removed in this release

### Fixed
- Bug fixes

### Security
- Security improvements and vulnerability patches
```

### Versioning Rules

- **MAJOR (X.0.0)**: Breaking changes, incompatible API changes
- **MINOR (0.X.0)**: New features, backward-compatible additions
- **PATCH (0.0.X)**: Backward-compatible bug fixes

---

[1.0.0]: https://github.com/EquidnaMX/bird-flock/releases/tag/v1.0.0
