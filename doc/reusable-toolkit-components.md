# Reusable Toolkit Components

This document identifies tools and reusable code from `equidna/bird-flock` that can be extracted into an external package (e.g., `equidna/laravel-toolkit`) for use across other projects.

---

## Overview

The Bird Flock package contains several general-purpose utilities that are not specific to messaging and can be valuable in other Laravel applications. These components follow best practices and can be extracted with minimal modifications.

---

## High Priority Components

### 1. BackoffStrategy

**Location**: `src/Support/BackoffStrategy.php`

**Description**: Provides retry backoff calculations with decorrelated jitter and exponential backoff strategies.

**Why Extract**:
- Completely framework-agnostic
- No external dependencies
- Useful for any retry logic (HTTP clients, queue jobs, database connections)

**API**:
```php
// Decorrelated jitter backoff
$delayMs = BackoffStrategy::decorrelatedJitter(
    attempt: 2,
    baseMs: 1000,
    maxMs: 60000,
    previousMs: 3000
);

// Exponential backoff with jitter
$delayMs = BackoffStrategy::exponentialWithJitter(
    attempt: 2,
    baseMs: 1000,
    maxMs: 60000
);
```

**Extraction Effort**: Low - Copy as-is, update namespace.

---

### 2. CircuitBreaker

**Location**: `src/Support/CircuitBreaker.php`

**Description**: Implements the circuit breaker pattern to prevent cascading failures when calling external services.

**Why Extract**:
- Generic pattern applicable to any external service integration
- Uses Laravel's Cache facade (easy to adapt)
- Configurable thresholds and timeouts

**Features**:
- Three states: Closed, Open, Half-Open
- Configurable failure threshold
- Timeout-based recovery
- Trial limiting in half-open state
- Success threshold for recovery

**API**:
```php
$breaker = new CircuitBreaker(
    service: 'external-api',
    failureThreshold: 5,
    timeout: 60,
    successThreshold: 2,
    maxTrials: 3
);

if ($breaker->isAvailable()) {
    try {
        // Make API call
        $breaker->recordSuccess();
    } catch (Exception $e) {
        $breaker->recordFailure();
    }
}

// Manual reset
$breaker->reset();
```

**Dependencies**: 
- `Illuminate\Support\Facades\Cache`
- Internal `Logger` class (needs abstraction)

**Extraction Effort**: Medium - Needs logging abstraction.

---

### 3. Masking

**Location**: `src/Support/Masking.php`

**Description**: Utilities for masking sensitive data in logs (emails, phone numbers, API keys).

**Why Extract**:
- Essential for any application handling sensitive data
- No dependencies
- Simple, stateless utility methods

**API**:
```php
// Email: j***n@example.com
Masking::maskEmail('john@example.com');

// Phone: +1*****90
Masking::maskPhone('+1234567890');

// API Key: SG.x****xxxx
Masking::maskApiKey('SG.xxxxxxxxxxxxxxxxxx');
```

**Extraction Effort**: Low - Copy as-is, update namespace.

---

### 4. PayloadNormalizer

**Location**: `src/Support/PayloadNormalizer.php`

**Description**: Validates and normalizes phone numbers to E.164 format and validates email addresses.

**Why Extract**:
- Useful for any application handling phone numbers or emails
- Handles edge cases (quotes, prefixes, length validation)
- No external dependencies

**API**:
```php
// Normalize WhatsApp: whatsapp:+1234567890
PayloadNormalizer::normalizeWhatsAppRecipient('+1234567890');

// Normalize phone: +1234567890
PayloadNormalizer::normalizePhoneNumber('(123) 456-7890');

// Validate email
PayloadNormalizer::isValidEmail('user@example.com'); // true
```

**Extraction Effort**: Low - Copy as-is, update namespace.

---

## Medium Priority Components

### 5. RateLimitWebhooks Middleware

**Location**: `src/Http/Middleware/RateLimitWebhooks.php`

**Description**: Rate limiting middleware for webhook endpoints with proper headers.

**Why Extract**:
- Configurable rate limits
- Proper HTTP headers (X-RateLimit-*, Retry-After)
- IP + path based limiting

**Dependencies**:
- `Illuminate\Cache\RateLimiter`
- Configuration system

**Extraction Effort**: Medium - Needs configuration abstraction.

---

### 6. MetricsCollectorInterface

**Location**: `src/Contracts/MetricsCollectorInterface.php`

**Description**: Contract for incrementing metrics counters.

**Why Extract**:
- Provides abstraction for metrics backends (Prometheus, StatsD, OTEL)
- Simple, focused interface
- Includes default no-op implementation

**API**:
```php
interface MetricsCollectorInterface
{
    public function increment(
        string $metric,
        int $by = 1,
        array $tags = []
    ): void;
}
```

**Extraction Effort**: Low - Copy interface and default implementation.

---

### 7. Webhook Signature Validators

**Location**: `src/Support/*SignatureValidator.php`

**Description**: HMAC signature validation for webhook security.

#### Generic Patterns Extractable:
- **MailgunSignatureValidator**: HMAC-SHA256 with timestamp+token
- **VonageSignatureValidator**: SHA256 with sorted params + timestamp validation
- **TwilioSignatureValidator**: HMAC-SHA1 with URL + params

**Why Extract**:
- Security patterns applicable to custom webhook implementations
- Timestamp validation prevents replay attacks
- Hash comparison using `hash_equals()` for timing-attack prevention

**Dependencies**:
- `Illuminate\Http\Request`
- Provider-specific logic (may need abstraction)

**Extraction Effort**: Medium-High - Need to create generic base classes.

---

## Low Priority Components

### 8. ConfigValidator

**Location**: `src/Support/ConfigValidator.php`

**Description**: Boot-time configuration validation with warnings/errors.

**Pattern to Extract**: The general pattern of configuration validation with different severity levels.

**Extraction Effort**: High - Very application-specific.

---

### 9. Logger Wrapper

**Location**: `src/Support/Logger.php`

**Description**: Structured logging helper with enable/disable support.

**Pattern to Extract**: The pattern of conditional logging with a configurable channel.

**Extraction Effort**: Medium - Needs generalization.

---

## Recommended Package Structure

```
equidna/laravel-toolkit/
├── src/
│   ├── Contracts/
│   │   ├── MetricsCollectorInterface.php
│   │   └── SignatureValidatorInterface.php
│   ├── Resilience/
│   │   ├── BackoffStrategy.php
│   │   └── CircuitBreaker.php
│   ├── Security/
│   │   ├── Masking.php
│   │   └── HmacSignatureValidator.php
│   ├── Validation/
│   │   ├── PhoneNormalizer.php
│   │   └── EmailValidator.php
│   ├── Http/
│   │   └── Middleware/
│   │       └── RateLimitWebhooks.php
│   └── Metrics/
│       └── LoggingMetricsCollector.php
├── config/
│   └── laravel-toolkit.php
├── tests/
└── composer.json
```

---

## Migration Path

### Phase 1: Extract Core Utilities (Low Risk)
1. `BackoffStrategy` → `Equidna\LaravelToolkit\Resilience\BackoffStrategy`
2. `Masking` → `Equidna\LaravelToolkit\Security\Masking`
3. `PayloadNormalizer` → Split into:
   - `Equidna\LaravelToolkit\Validation\PhoneNormalizer`
   - `Equidna\LaravelToolkit\Validation\EmailValidator`
4. `MetricsCollectorInterface` → `Equidna\LaravelToolkit\Contracts\MetricsCollectorInterface`

### Phase 2: Extract Laravel-Integrated Components
1. `CircuitBreaker` → Needs logging abstraction via interface
2. `RateLimitWebhooks` → Needs configuration prefix customization

### Phase 3: Update Bird Flock
1. Add `equidna/laravel-toolkit` as a dependency
2. Update imports to use toolkit namespace
3. Remove extracted code from bird-flock

---

## Benefits of Extraction

1. **Reduced Duplication**: Other Equidna projects can use the same utilities
2. **Improved Testing**: Isolated testing of generic utilities
3. **Easier Maintenance**: Single source of truth for common patterns
4. **Smaller Package Footprint**: Bird Flock becomes more focused on messaging

---

## Estimated Effort

| Component | Effort | Risk |
|-----------|--------|------|
| BackoffStrategy | 1-2 hours | Low |
| Masking | 1 hour | Low |
| PayloadNormalizer | 2-3 hours | Low |
| MetricsCollectorInterface | 1 hour | Low |
| CircuitBreaker | 3-4 hours | Medium |
| RateLimitWebhooks | 2-3 hours | Medium |
| Signature Validators | 4-6 hours | Medium |

**Total Estimated Effort**: 14-20 hours for full extraction

---

## Next Steps

1. Create `equidna/laravel-toolkit` repository
2. Start with Phase 1 components (low risk)
3. Add comprehensive tests
4. Update Bird Flock to use the toolkit
5. Identify other Equidna projects that can benefit
