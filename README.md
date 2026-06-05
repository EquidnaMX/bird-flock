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

- Database: package uses Eloquent models and migrations; supported by host Laravel DB driver (commonly MySQL/PostgreSQL/SQLite).
- Cache: package uses Laravel cache for circuit-breaker state.
- Queue: package dispatches Laravel queue jobs; host queue driver is used.

External integrations:

- Twilio (`twilio/sdk`) for SMS and WhatsApp senders.
- Vonage (`vonage/client`) available as SMS provider implementation.
- SendGrid (`sendgrid/sendgrid`) available as email provider implementation.
- Mailgun (`mailgun/mailgun-php`) available as email provider implementation.

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
