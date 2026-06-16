# Changelog

All notable changes to **Bird Flock** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

**IMPORTANT**: This changelog is the authoritative record of all changes. AI agents and contributors MUST respect and update this file for all future releases.

---

## [Unreleased]

No unreleased changes.

## [1.5.0] - 2026-06-16 - "Osprey"

### Added

- **`DatabaseConnection` helper** (`src/Support/DatabaseConnection.php`): Centralized static helper that routes all package database operations through a configurable connection. Exposes `connection()`, `schema()`, `table()`, `transaction()`, and `raw()` methods.
- **Configurable database connection**: New `bird-flock.database.connection` config key and `BIRD_FLOCK_DB_CONNECTION` environment variable allow the package to operate against a non-default database connection, enabling multi-tenancy and split-database architectures.
- **Performance indexes on `outbound_messages` table**: Six new composite and single-column indexes added to improve query performance for common access patterns:
  - `idx_channel_status_queued_at` on `(channel, status, queuedAt)`
  - `idx_channel_created_at` on `(channel, createdAt)`
  - `idx_template_created_at` on `(templateKey, createdAt)`
  - `idx_recipient_channel_created` on `(to, channel, createdAt)`
- **Performance indexes on `dead_letter_entries` table**: Two new composite indexes:
  - `idx_dlq_error_created` on `(error_code, created_at)`
  - `idx_dlq_channel_error_created` on `(channel, error_code, created_at)`
- **New test coverage**: `ConfiguredDatabaseConnectionFeatureTest` (feature), `DatabaseConnectionTest` (unit), and `DatabaseConnectionModelTest` (unit) verify configurable-connection and migration behavior.

### Changed

- **Migrations**: Both `create_outbound_messages_table.php` and `create_dead_letter_entries_table.php` now use `DatabaseConnection::schema()` instead of `Illuminate\Support\Facades\Schema` to respect the configured connection.
- **Models**: `OutboundMessage` and `DeadLetterEntry` now resolve their connection name via `DatabaseConnection::name()`.
- **`EloquentOutboundMessageRepository`**: Uses `DatabaseConnection::table()` and `DatabaseConnection::transaction()`.
- **`HealthService`**: Uses `DatabaseConnection::connection()` for health-check queries.
- **`DeadLetterStatsCommand`**: Uses `DatabaseConnection` for statistics queries.
- **`BirdFlock` core**: Uses `DatabaseConnection::transaction()` for atomic operations.
- **`config/bird-flock.php`**: Added `database.connection` key (defaults to `null` = Laravel default connection).
- **`.env.example`**: Added `BIRD_FLOCK_DB_CONNECTION` example entry.
- **`phpunit.bootstrap.php`**: Updated to set up the configured connection for test runs.
- **Documentation**: `deployment-instructions.md` and `README.md` updated with database connection configuration guidance.

### Fixed

- N/A

### Security

- No security-related changes in this release.

### Breaking Changes

- None. This release is **fully backward-compatible**. When `BIRD_FLOCK_DB_CONNECTION` is not set (the default), the package continues to use Laravel's default database connection — identical behavior to v1.4.0 and earlier.

---

## [1.4.0] - 2026-06-15 - "Hawk"

### Added

- **Vendor-based sender configuration**: New per-vendor config files (`bird-flock-twilio.php`, `bird-flock-vonage.php`, `bird-flock-sendgrid.php`, `bird-flock-mailgun.php`, `bird-flock-labsmobile.php`) for modular vendor setup.
- **Multi-sender support per channel**: `channels.*.senders` configuration with vendor keys allows multiple providers for the same channel (SMS, email).
- **Sender selection strategies**: New `channels.*.strategy` setting supporting `round_robin` (default) and `random` vendor selection.
- **SenderDefinitionInterface and SenderConfigValidatorInterface**: New contracts for vendor sender definitions with optional config validation.
- **LabsMobile SMS provider**: Complete LabsMobile HTTP client integration, `LabsmobileSmsSender`, webhook controller, and route handler.
- **Vendor-specific webhook routes**: Separate route files per provider (`routes/twilio.php`, `routes/vonage.php`, etc.) for cleaner organization and targeted handling.
- **SenderResolver and VendorSelector services**: New support classes for runtime sender construction and vendor selection logic.
- **Enhanced HealthService**: Extended diagnostics including per-vendor circuit-breaker state and sender availability checks.
- **Batch FlightPlan deduplication**: BirdFlock now deduplicates idempotency keys in batch mode before enqueue.

### Changed

- **Sender directory structure**: Senders reorganized into vendor-specific folders (`src/Senders/Twilio/`, `src/Senders/Vonage/`, etc.) for improved organization.
- **MessageFactory**: Refactored to delegate vendor selection and sender instantiation to `SenderResolver`.
- **BirdFlockServiceProvider**: Now merges and publishes per-vendor config files and binds vendor-specific HTTP clients.
- **ConfigValidator**: Extended to validate `channels.*.senders` configuration, strategies, and sender definitions.
- **Documentation**: Updated README, deployment instructions, API documentation, and routes documentation to reflect vendor-based routing and multi-sender strategies.
- **.env.example**: Added vendor environment variable examples for all providers.

### Fixed

- Webhook controller routing is now vendor-specific and less prone to conflicts.
- Sender construction is more testable through `SenderDefinitionInterface` implementations.
- Configuration validation catches misconfigured strategies early.

### Security

- No security-related changes in this release.

### Breaking Changes

- None. This release is **fully backward-compatible**. Existing single-sender configurations continue to work unchanged.

---

## [1.3.0] - 2026-06-05 - "Falcon"

### Added

- Official compatibility with Laravel 13.x.
- Inline email attachment support (CID/`inline`) across Mailgun and SendGrid senders, including `MailableConverter` support for embedded assets.

### Changed

- Widened Composer constraints to support Laravel/Illuminate 10, 11, 12, and 13.
- Removed direct `symfony/uid` dependency because it is not used directly by this package.
- Relaxed `psr/log` from exact `3.0` to `^3.0`.
- Refreshed root and package documentation for current architecture, routes, commands, monitoring, and testing guidance.

### Fixed

- Added stricter email attachment validation for invalid dispositions and missing `content_id` in inline payloads.
- Updated test support fakes and command-related tests for Laravel 13 contract compatibility.

### Security

- No security-related changes in this release.

### Breaking Changes

- None. This release is backward-compatible.

## [1.2.0] - 2025-12-15 - "Condor"

### Added

- Official compatibility with Laravel 12.x.

### Changed

- Widened Composer constraints to allow `laravel/framework ^10 || ^11 || ^12` and `illuminate/database ^10 || ^11 || ^12`.
- Updated README to state support for Laravel 10–12.

### Fixed

- Documentation consistency around supported framework versions.

### Security

- No security-related changes in this release.

### Breaking Changes

- None. This is a backward-compatible compatibility update.

## [1.1.0] - 2025-12-11 - "Albatross"

### ✨ Added

- **Laravel Mailable Support**: Send Laravel Mailable classes through Bird Flock
  - New `BirdFlock::dispatchMailable()` method for easy Mailable dispatching
  - `MailableConverter` class converts Mailables to FlightPlan DTOs
  - Automatic HTML-to-text conversion for plain text fallback
  - Support for Blade template rendering
  - Attachment support with base64 encoding
  - Full idempotency, retry logic, and DLQ support for Mailables
  - Added `illuminate/mail` and `illuminate/view` dependencies
  - Comprehensive documentation in `doc/mailable-usage.md`
  - Complete working examples in `doc/examples/` directory

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
[1.1.0]: https://github.com/EquidnaMX/bird-flock/releases/tag/v1.1.0
[1.2.0]: https://github.com/EquidnaMX/bird-flock/releases/tag/v1.2.0
