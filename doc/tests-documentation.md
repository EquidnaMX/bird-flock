# Tests Documentation

## Test Framework

- Framework: PHPUnit (`phpunit/phpunit ^10.0`).
- Bootstrap: `phpunit.bootstrap.php`.
- Base test class: `Equidna\BirdFlock\Tests\TestCase`.

## How to Run Tests

Run all tests:

```bash
./vendor/bin/phpunit
```

Run default suites from `phpunit.xml.dist`:

```bash
./vendor/bin/phpunit --testsuite Unit
./vendor/bin/phpunit --testsuite Feature
```

Run one file:

```bash
./vendor/bin/phpunit tests/Unit/BirdFlockDispatchTest.php
```

## Current PHPUnit Suite Wiring

`phpunit.xml.dist` currently maps:

- `Unit` suite -> `tests/Messaging`
- `Feature` suite -> `tests/Feature`

Important implication:

- A large portion of tests under `tests/Unit` are not selected by the `Unit` suite unless run directly or via an adjusted config.

## Test Layout Overview

- `tests/TestCase.php`: minimal container/config/events setup.
- `tests/Messaging/**`: messaging and webhook-centric tests.
- `tests/Unit/**`: extensive unit tests for DTOs, senders, jobs, repositories, support services, and core dispatch behavior.
- `tests/Feature/**`: currently includes `BatchDispatchFeatureTest`.
- `tests/README-integration-plan.md`: integration-test plan (not implemented in this repository).

## Coverage Snapshot (Code-Based)

Areas with clear automated coverage:

- Core dispatch/idempotency paths (`BirdFlock`, conflicts, duplicate skip behavior).
- Senders (Twilio/SendGrid/Vonage/Mailgun variants in unit or messaging tests).
- Webhook signature and controller behaviors (notably Twilio/SendGrid; partial for others).
- Circuit breaker and payload normalization utilities.
- Health service/controller behavior.

Potentially weaker or indirect coverage:

- Operational command UX output details.
- End-to-end database + queue + provider integration in one flow.
- Real integration timing/race validation (documented as plan, not full integration suite here).

## Adding New Tests

Recommended conventions:

- File name: `{Subject}Test.php`.
- Class: `final class {Subject}Test extends TestCase`.
- Method names: behavior-oriented (`test_it_...`).

Placement guidance:

- Keep unit-level logic under `tests/Unit`.
- Keep HTTP/controller integration style tests under `tests/Messaging` or `tests/Feature` depending on scope.

Pattern:

```php
public function test_it_handles_invalid_payload(): void
{
    // Arrange

    // Act

    // Assert
}
```

## CI/Automation Notes

- Because suite mapping currently points `Unit` to `tests/Messaging`, CI should either:
  - run `./vendor/bin/phpunit` without suite filters and include explicit paths, or
  - update `phpunit.xml.dist` if full `tests/Unit` coverage is intended by default.

## Assumptions

- No dedicated integration environment is configured in this package repository.
- Maintainers may intentionally separate `tests/Messaging` and `tests/Unit` selection behavior; confirm expected CI strategy.
