# Release v1.5.0 "Osprey"

Bird Flock v1.5.0 delivers database flexibility and query performance improvements. The Osprey — famous for precision diving and environmental adaptability — reflects a release that lets the package adapt to any database topology while sharpening query speed.

**Release Date**: 2026-06-16
**Codename**: Osprey
**Version**: 1.5.0

## Highlights

- 🗄️ **Configurable database connection**: Run Bird Flock against any named Laravel database connection via `BIRD_FLOCK_DB_CONNECTION`. Perfect for split-database or multi-tenant architectures.
- ⚡ **Performance indexes**: 6 new composite indexes on `outbound_messages` and 2 on `dead_letter_entries` accelerate the most common monitoring, retry, and analytics queries.
- 🔧 **`DatabaseConnection` helper**: A clean, centralized utility replaces scattered `DB::` / `Schema::` facade calls throughout migrations, models, repositories, services, and commands.
- 🧪 **Full test coverage**: Feature and unit tests verify the configurable-connection path end-to-end.
- ✅ **Fully backward-compatible**: Zero migration required — omitting `BIRD_FLOCK_DB_CONNECTION` preserves existing behavior exactly.

## Added

- `src/Support/DatabaseConnection.php` — static helper with `connection()`, `schema()`, `table()`, `transaction()`, `raw()`, and `name()` methods.
- `bird-flock.database.connection` config key (env: `BIRD_FLOCK_DB_CONNECTION`).
- New indexes on `outbound_messages`:
  - `idx_channel_status_queued_at` on `(channel, status, queuedAt)`
  - `idx_channel_created_at` on `(channel, createdAt)`
  - `idx_template_created_at` on `(templateKey, createdAt)`
  - `idx_recipient_channel_created` on `(to, channel, createdAt)`
- New indexes on `dead_letter_entries`:
  - `idx_dlq_error_created` on `(error_code, created_at)`
  - `idx_dlq_channel_error_created` on `(channel, error_code, created_at)`
- Tests: `ConfiguredDatabaseConnectionFeatureTest`, `DatabaseConnectionTest`, `DatabaseConnectionModelTest`.

## Changed

- Migrations, models, repositories, services, console command, and core `BirdFlock` class all route DB operations through `DatabaseConnection`.
- `config/bird-flock.php` extended with `database.connection`.
- `.env.example` documents the new env variable.
- `phpunit.bootstrap.php` updated for the configurable connection test setup.
- `deployment-instructions.md` and `README.md` updated.

## Fixed

- N/A

## Security

No security-related changes in this release.

## Breaking Changes

None. See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for the full policy and v1.5.0 migration notes.

## Links

- Full history: [CHANGELOG.md](CHANGELOG.md)
- Migration guide: [BREAKING_CHANGES.md](BREAKING_CHANGES.md)

---

# Release v1.4.0 "Hawk"

Bird Flock v1.4.0 introduces a flexible, vendor-agnostic sender architecture with support for multiple SMS and email providers per channel, plus a brand-new LabsMobile integration.

**Release Date**: 2026-06-15  
**Codename**: Hawk  
**Version**: 1.4.0

## Highlights

- 🔀 **Multi-vendor routing**: Configure multiple senders per channel (SMS, email) with automatic selection via round-robin or random strategies.
- 📦 **Modular vendor configs**: Per-vendor config files make it easy to enable/disable providers and switch routing strategies.
- 🚀 **LabsMobile SMS**: New LabsMobile provider with full webhook support and HTTP client integration.
- 🏗️ **Better organization**: Senders reorganized by vendor with cleaner contracts and definitions.
- 🔧 **SenderDefinitionInterface**: Custom vendor definitions can now validate configuration and construct senders with type safety.
- 🎯 **Backward compatible**: Existing single-vendor setups continue to work unchanged.

## Major Features

### Vendor-Based Sender Configuration

Define multiple senders per channel in `config/bird-flock.php`:

```php
'channels' => [
    'sms' => [
        'strategy' => 'round_robin',  // or 'random'
        'senders' => [
            'twilio' => \Equidna\BirdFlock\Senders\Twilio\TwilioSmsSenderDefinition::class,
            'vonage' => \Equidna\BirdFlock\Senders\Vonage\VonageSmsSenderDefinition::class,
            'labsmobile' => \Equidna\BirdFlock\Senders\Labsmobile\LabsmobileSmsSenderDefinition::class,
        ],
    ],
    'email' => [
        'strategy' => 'round_robin',
        'senders' => [
            'mailgun' => \Equidna\BirdFlock\Senders\Mailgun\MailgunEmailSenderDefinition::class,
            'sendgrid' => \Equidna\BirdFlock\Senders\Sendgrid\SendgridEmailSenderDefinition::class,
        ],
    ],
],
```

### Per-Vendor Configuration Files

Each vendor now has its own config file for cleaner setup:

- `config/bird-flock-twilio.php`
- `config/bird-flock-vonage.php`
- `config/bird-flock-sendgrid.php`
- `config/bird-flock-mailgun.php`
- `config/bird-flock-labsmobile.php`

Publish them with: `php artisan vendor:publish --tag=bird-flock-config`

### LabsMobile SMS Provider

Full-featured LabsMobile integration:

- `LabsmobileSmsSender`: HTTP-based SMS dispatcher
- Webhook controller with request validation
- Complete test coverage
- Integrated with circuit breaker and retry logic

### New Contracts

- **SenderDefinitionInterface**: Enables custom vendor definitions with constructor dependency injection.
- **SenderConfigValidatorInterface**: Optional validation of vendor-specific configuration at boot time.

## Added

- Vendor-based sender configuration with multiple senders per channel.
- `round_robin` and `random` selection strategies for multi-vendor setups.
- Per-vendor config files and centralized vendor binding in service provider.
- **LabsMobile SMS provider** with full webhook integration.
- `SenderResolver` service for runtime sender instantiation.
- `VendorSelector` service for strategy-based vendor selection.
- Sender-specific definitions and validators.
- Enhanced `HealthService` with per-vendor circuit-breaker diagnostics.
- `MessageFactoryVendorRoutingTest` and comprehensive sender/strategy tests.

## Changed

- **Sender directory structure**: Senders now organized into vendor folders (`Senders/Twilio/`, `Senders/Vonage/`, etc.).
- **MessageFactory**: Simplified to delegate to `SenderResolver` for vendor-agnostic sender creation.
- **BirdFlockServiceProvider**: Extended to merge vendor configs and bind vendor-specific clients.
- **ConfigValidator**: Enhanced to validate `channels.*.senders` and sender definitions.
- **Documentation**: README and deployment guides updated with vendor-based routing examples.
- **Webhook routes**: Split into vendor-specific files for better isolation and maintainability.

## Fixed

- Batch FlightPlan deduplication now properly checks for existing idempotency keys before dispatch.
- Webhook routing is cleaner and less prone to conflicts.
- Configuration validation catches strategy misconfigurations at startup.

## Security

- No security-related changes in this release.

## Compatibility

- PHP: `>=8.3`
- Laravel/Illuminate: `^10 || ^11 || ^12 || ^13`

## Migration Guide

**No breaking changes** — existing configurations work as-is. To adopt vendor-based routing:

1. Publish vendor config files: `php artisan vendor:publish --tag=bird-flock-config`
2. Update your `channels` configuration to define multiple senders (optional).
3. Set your preferred `strategy` (`round_robin` or `random`).
4. Gradual migration is supported; old and new configs can coexist.

## References

- Full change history: [CHANGELOG.md](CHANGELOG.md)
- Breaking changes and migration guidance: [BREAKING_CHANGES.md](BREAKING_CHANGES.md)
- Deployment instructions: [doc/deployment-instructions.md](doc/deployment-instructions.md)
- API documentation: [doc/api-documentation.md](doc/api-documentation.md)
