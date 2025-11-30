# Tests Documentation

This document explains the test suite structure, how to run tests, coverage overview, and guidelines for adding new tests.

---

## Test Framework

**Bird Flock** uses **PHPUnit 10.x** for testing.

**Test Framework**: PHPUnit  
**PHP Version**: 8.3+  
**Test Types**: Unit tests only (per TestingScope instructions)

---

## Running Tests

### Run All Tests

```bash
./vendor/bin/phpunit
```

Or via Composer script (if configured):

```bash
composer test
```

### Run Specific Test Suite

PHPUnit configuration (`phpunit.xml.dist`) defines test suites:

```bash
# Run only unit tests
./vendor/bin/phpunit --testsuite Unit

# Run specific test file
./vendor/bin/phpunit tests/Unit/BirdFlockDispatchTest.php
```

### Run with Coverage

```bash
./vendor/bin/phpunit --coverage-html coverage
```

Open `coverage/index.html` in a browser to view coverage report.

### Run with Verbose Output

```bash
./vendor/bin/phpunit --verbose
```

---

## Test Structure

### Directory Layout

```
tests/
├── TestCase.php                       # Base test case for all unit tests
├── README-integration-plan.md          # Integration test plan (not implemented)
├── Feature/
│   ├── FeatureTestCase.php             # Feature test base (minimal usage)
│   └── BatchDispatchFeatureTest.php    # Batch dispatch feature test
├── Integration/                        # Empty (reserved for future)
├── Messaging/                          # Webhook and messaging tests
│   ├── TwilioWebhookControllerTest.php
│   └── Support/
│       ├── TwilioSignatureValidatorTest.php
│       └── PayloadNormalizerTest.php
├── Support/                            # Test support classes
│   └── ResponseFactoryFake.php         # Fake response factory for tests
└── Unit/                               # Unit tests (primary focus)
    ├── BirdFlockDispatchTest.php       # Core dispatch logic
    ├── ConcurrentIdempotencyTest.php   # Idempotency race conditions
    ├── IdempotencyTest.php             # Idempotency logic
    ├── Config/
    │   └── ConfigValidatorTest.php
    ├── DTO/
    │   └── FlightPlanValidationTest.php
    ├── Http/
    │   └── Controllers/
    │       └── HealthCheckControllerTest.php
    ├── Jobs/
    │   ├── SendEmailJobTest.php
    │   ├── SendSmsJobTest.php
    │   └── SendWhatsappJobTest.php
    ├── Repositories/
    │   └── EloquentOutboundMessageRepositoryTest.php
    ├── Senders/
    │   ├── SendgridEmailSenderTest.php
    │   ├── TwilioSandboxTest.php
    │   ├── TwilioSmsSenderTest.php
    │   └── TwilioWhatsappSenderTest.php
    └── Support/
        ├── BackoffStrategyTest.php
        ├── CircuitBreakerConcurrencyTest.php
        ├── CircuitBreakerTest.php
        ├── PayloadNormalizerExtraTest.php
        └── PayloadNormalizerTest.php
```

---

## Base Test Classes

### `TestCase` (`tests/TestCase.php`)

**Purpose**: Base class for all unit tests; sets up minimal Laravel container with config and event dispatcher.

**Key Setup**:

- Registers minimal Laravel container (`Container::getInstance()`)
- Provides config repository with Bird Flock defaults
- Registers response factory fake (for HTTP tests)
- Sets up event dispatcher (for event-driven tests)

**Usage**:

```php
namespace Equidna\BirdFlock\Tests\Unit;

use Equidna\BirdFlock\Tests\TestCase;

final class MyTest extends TestCase
{
    public function test_something(): void
    {
        // Test code
    }
}
```

**Helper Methods**:

- `setConfigValue(string $key, mixed $value): void` — Override config values in tests

---

### `FeatureTestCase` (`tests/Feature/FeatureTestCase.php`)

**Purpose**: Base class for feature tests (currently minimal usage; most tests are unit tests).

---

## Coverage Overview

### Well-Tested Areas

- ✅ **Core Dispatch Logic** (`BirdFlock::dispatch()`, `BirdFlock::dispatchBatch()`)

  - `BirdFlockDispatchTest.php`
  - `IdempotencyTest.php`
  - `ConcurrentIdempotencyTest.php`

- ✅ **Jobs** (SendSmsJob, SendWhatsappJob, SendEmailJob)

  - `SendSmsJobTest.php`
  - `SendWhatsappJobTest.php`
  - `SendEmailJobTest.php`

- ✅ **Senders** (Twilio, SendGrid provider abstractions)

  - `TwilioSmsSenderTest.php`
  - `TwilioWhatsappSenderTest.php`
  - `SendgridEmailSenderTest.php`
  - `TwilioSandboxTest.php`

- ✅ **Support Classes**

  - `CircuitBreakerTest.php` (including concurrency tests)
  - `PayloadNormalizerTest.php`
  - `ConfigValidatorTest.php`

- ✅ **DTOs & Validation**

  - `FlightPlanValidationTest.php`

- ✅ **Repositories**

  - `EloquentOutboundMessageRepositoryTest.php`

- ✅ **Health Checks**

  - `HealthCheckControllerTest.php`

- ✅ **Webhook Processing**
  - `TwilioWebhookControllerTest.php`
  - `TwilioSignatureValidatorTest.php`

### Areas with Limited or No Tests

- ⚠️ **Feature Tests**: Only one feature test (`BatchDispatchFeatureTest.php`); most logic tested via unit tests with mocks.

- ⚠️ **Integration Tests**: Not implemented (per `TestingScope.instructions.md`, agents only create unit tests).

- ⚠️ **Webhook Controllers**: Limited test coverage for SendGrid, Vonage, Mailgun webhooks (Twilio has tests).

- ⚠️ **Dead-Letter Service**: `DeadLetterService` logic not fully covered.

- ⚠️ **Console Commands**: Command classes not directly unit tested (would require complex mocking).

- ⚠️ **Event Listeners**: No explicit tests for event listener logic (if any).

- ⚠️ **Metrics Collector**: `MetricsCollector` has minimal test coverage.

### Coverage Report

To generate detailed coverage:

```bash
./vendor/bin/phpunit --coverage-html coverage --coverage-filter src/
```

**Current Coverage Estimate**: ~75–85% (based on well-tested core logic; gaps in webhooks and commands).

---

## Adding New Tests

### Naming Conventions

Follow **Coding Standards Guide** and **TestingScope** instructions:

- **File Name**: `{ClassName}Test.php` (e.g., `BirdFlockTest.php` for `BirdFlock` class)
- **Class Name**: `final class {ClassName}Test extends TestCase`
- **Test Method**: `public function test_describes_behavior(): void`

**Examples**:

```php
// File: tests/Unit/Support/MyHelperTest.php
final class MyHelperTest extends TestCase
{
    public function test_formats_phone_number_correctly(): void
    {
        // Arrange
        $input = '1234567890';

        // Act
        $result = MyHelper::formatPhone($input);

        // Assert
        $this->assertSame('+1234567890', $result);
    }
}
```

### Test Structure (Arrange-Act-Assert)

Use clear **Arrange-Act-Assert** or **Given-When-Then** structure:

```php
public function test_sends_sms_via_twilio(): void
{
    // Arrange
    $twilioClient = $this->createMock(TwilioClient::class);
    $twilioClient->messages = $this->createMock(MessageList::class);
    $twilioClient->messages->expects($this->once())
        ->method('create')
        ->willReturn((object) ['sid' => 'SM123']);

    $sender = new TwilioSmsSender($twilioClient);

    // Act
    $result = $sender->send('+1234567890', 'Test message');

    // Assert
    $this->assertSame('SM123', $result->providerMessageId);
}
```

### Mocking External Dependencies

**MUST** mock all external dependencies (per `TestingScope.instructions.md`):

- **Database**: Mock repository interfaces
- **HTTP Clients**: Mock Guzzle, Twilio, SendGrid, Vonage, Mailgun clients
- **Queue**: Mock queue dispatcher
- **Events**: Use Laravel's `Event::fake()`
- **Time**: Inject `Carbon` instances or use `Carbon::setTestNow()`

**Example**:

```php
use Illuminate\Support\Facades\Event;
use Equidna\BirdFlock\Events\MessageQueued;

public function test_dispatches_message_queued_event(): void
{
    Event::fake();

    // Act
    BirdFlock::dispatch($flightPlan, $mockRepository);

    // Assert
    Event::assertDispatched(MessageQueued::class);
}
```

### Folder Placement

- **Unit tests**: `tests/Unit/**` (organized by namespace: `Jobs/`, `Senders/`, `Support/`, etc.)
- **Feature tests**: `tests/Feature/**` (minimal usage; requires approval per `TestingScope`)

---

## Running Tests in CI

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  phpunit:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: ["8.3"]

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          coverage: xdebug

      - name: Install Dependencies
        run: composer install --prefer-dist --no-interaction

      - name: Run Tests
        run: ./vendor/bin/phpunit --coverage-clover coverage.xml

      - name: Upload Coverage
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
```

---

## Continuous Testing During Development

Use **PHPUnit's watch mode** (requires `phpunit-watcher` package):

```bash
composer require --dev spatie/phpunit-watcher
./vendor/bin/phpunit-watcher watch
```

Or use **PHPUnit's built-in file watcher** (if available in your environment).

---

## Test Configuration

**File**: `phpunit.xml.dist`

Key settings:

```xml
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.0/phpunit.xsd"
         bootstrap="phpunit.bootstrap.php"
         colors="true">

    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
        <testsuite name="Messaging">
            <directory>tests/Messaging</directory>
        </testsuite>
    </testsuites>

    <coverage>
        <include>
            <directory>src</directory>
        </include>
    </coverage>
</phpunit>
```

**Bootstrap File**: `phpunit.bootstrap.php`

Sets up Composer autoloader and minimal environment.

---

## Assumptions & Notes

- **Unit Tests Only**: Per `TestingScope.instructions.md`, agents only create unit tests. Feature, integration, and E2E tests are owned by other teams.
- **No Database**: Unit tests do not touch the database; mock `OutboundMessageRepositoryInterface` instead.
- **No Real HTTP**: Unit tests mock HTTP clients (Twilio, SendGrid, Vonage, Mailgun, Guzzle).
- **Fast Execution**: Unit tests should run in < 100ms each; total suite runtime typically < 10 seconds.

For unresolved test questions, see [Open Questions & Assumptions](open-questions-and-assumptions.md).
