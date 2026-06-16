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
