# README (Root of the Project)

## Project Overview

Bird Flock is a Laravel package for outbound multi-channel messaging with queue-first delivery, idempotency, retries, dead-letter handling, provider webhooks, and health endpoints.

Primary audience:

- Internal development teams integrating reliable messaging into Laravel applications.
- Maintainers and contributors extending providers, routing, and operational tooling.

Main value:

- One API (`Equidna\BirdFlock\BirdFlock`) to dispatch SMS, WhatsApp, and email workloads.
- Operational safety via idempotency keys, retry policies, and dead-letter replay commands.
- Visibility via events, structured logs, and health/circuit-breaker diagnostics.

## Project Type & Tech Summary

Project type:

- Laravel package/library (`composer.json` has `"type": "library"`).

Version/runtime summary:

- PHP: `>=8.3`.
- Laravel/Illuminate: `^10 || ^11 || ^12 || ^13`.

Infrastructure model:

- Database: package uses Eloquent models and migrations; supported by host Laravel DB driver (commonly MySQL/PostgreSQL/SQLite). Set `BIRD_FLOCK_DB_CONNECTION` to store package tables on a non-default Laravel connection.
- Cache: package uses Laravel cache for circuit-breaker state.
- Queue: package dispatches Laravel queue jobs; host queue driver is used.

External integrations:

- Twilio (`twilio/sdk`) for SMS and WhatsApp senders.
- Vonage (`vonage/client`) available as SMS provider implementation.
- LabsMobile HTTP/POST API available as SMS provider implementation.
- SendGrid (`sendgrid/sendgrid`) available as email provider implementation.
- Mailgun (`mailgun/mailgun-php`) available as email provider implementation.

## Config-Driven Senders

Bird Flock resolves outbound senders from `config/bird-flock.php`. Each channel has a `senders`
map keyed by vendor id; that key is used for routing, logs, health checks, and circuit labels.

Simple external sender:

```php
'channels' => [
    'sms' => [
        'strategy' => 'round_robin',
        'retry' => [
            'max_attempts' => env('BIRD_FLOCK_SMS_MAX_ATTEMPTS', 3),
            'base_delay_ms' => env('BIRD_FLOCK_SMS_BASE_DELAY_MS', 1000),
            'max_delay_ms' => env('BIRD_FLOCK_SMS_MAX_DELAY_MS', 60000),
        ],
        'senders' => [
            'acme' => App\Messaging\AcmeSmsSender::class,
        ],
    ],
],
```

Typed constructor dependencies are resolved through the Laravel container. For constructor scalars or
package-specific validation, use a sender definition class:

```php
'acme' => App\Messaging\AcmeSmsSenderDefinition::class
```

Definition classes implement `Equidna\BirdFlock\Contracts\SenderDefinitionInterface` and return the
sender class, constructor arguments, and optional config validator. Argument values prefixed with
`config:` are read from Laravel config; raw values are passed as-is.

Definitions can also implement `Equidna\BirdFlock\Contracts\SenderConfigValidatorInterface`; in that
case `validator()` can return `self::class` so metadata and config validation stay together.

`strategy` controls which sender key is selected when a channel has multiple senders. The default is
`round_robin`, which rotates through the configured sender keys in order using Laravel cache.
`random` selects one configured sender with `random_int`. Any other value fails configuration at
runtime with an `InvalidArgumentException`.

For complex SDK construction, bind the SDK or sender in the host app and keep the same config shape:

```php
$this->app->singleton(App\Messaging\AcmeClient::class, function () {
    return new App\Messaging\AcmeClient(config('services.acme.api_key'));
});
```

External sender classes must implement `Equidna\BirdFlock\Contracts\MessageSenderInterface`.
External webhooks remain the host app's responsibility.

## Quick Start (High-Level)

1. Install the package in your host Laravel app:
   - `composer require equidna/bird-flock`
2. Publish package assets:
   - `php artisan vendor:publish --tag=bird-flock-config`
   - `php artisan vendor:publish --tag=bird-flock-migrations`
3. Configure provider credentials and package settings in `.env`.
4. Validate configuration:
   - `php artisan bird-flock:config:validate`
5. Run migrations:
   - `php artisan migrate`
6. Start queue workers for the target queue:
   - `php artisan queue:work --queue=default`
7. Dispatch messages via `BirdFlock::dispatch()`, `BirdFlock::dispatchBatch()`, or `BirdFlock::dispatchMailable()`.

Detailed deployment and operations guidance is in `doc/deployment-instructions.md`.

## Documentation Index

- [Deployment Instructions](doc/deployment-instructions.md)
- [API Documentation](doc/api-documentation.md)
- [Routes Documentation](doc/routes-documentation.md)
- [Artisan Commands](doc/artisan-commands.md)
- [Tests Documentation](doc/tests-documentation.md)
- [Architecture Diagrams](doc/architecture-diagrams.md)
- [Monitoring](doc/monitoring.md)
- [Business Logic & Core Processes](doc/business-logic-and-core-processes.md)
- [Open Questions & Assumptions](doc/open-questions-and-assumptions.md)

## Note About Standards

This documentation follows the project's Coding Standards Guide and PHPDoc Style Guide.
Where guide files are not visible in this repository snapshot, naming and examples were aligned to the existing package code style and namespace conventions.
