# Breaking Changes

This document tracks all breaking changes across versions of **Bird Flock** and provides migration guides.

**IMPORTANT**: This document MUST be updated by AI agents and contributors for any release containing breaking changes.

---

## Version 1.0.0 - "Phoenix" (2025-11-30)

### Initial Stable Release

**Status**: No breaking changes (initial release)

This is the first stable release of Bird Flock. As this is the initial version, there are no breaking changes from previous versions. All future breaking changes will be documented here.

---

## Breaking Changes Policy

### What Constitutes a Breaking Change?

A breaking change is any modification that:

1. **Removes or renames public API methods, classes, or interfaces**

   - Example: Removing `BirdFlock::send()` method
   - Example: Renaming `FlightPlan` class to `MessagePayload`

2. **Changes method signatures in non-backward-compatible ways**

   - Example: Changing required parameter order
   - Example: Removing optional parameters
   - Example: Changing parameter types without union type support

3. **Modifies database schema without migration path**

   - Example: Renaming table columns
   - Example: Changing column types incompatibly
   - Example: Removing columns

4. **Changes configuration structure incompatibly**

   - Example: Renaming config keys
   - Example: Changing config value formats (string → array)
   - Example: Removing config options

5. **Changes event payload structures**

   - Example: Removing fields from `MessageQueued` event
   - Example: Changing event constructor signatures

6. **Changes minimum PHP or Laravel version requirements**

   - Example: Upgrading from PHP 8.3 to PHP 8.4
   - Example: Requiring Laravel 12.x minimum

7. **Changes behavior of idempotency keys**

   - Example: Changing key format requirements
   - Example: Altering deduplication logic

8. **Removes or changes webhook endpoints**
   - Example: Changing webhook URL structure
   - Example: Modifying webhook payload formats

### What is NOT a Breaking Change?

The following are **not** considered breaking changes:

1. **Adding new optional parameters** (with defaults)
2. **Adding new methods to classes** (without removing existing)
3. **Adding new configuration options** (with sensible defaults)
4. **Adding new events** (without changing existing event payloads)
5. **Adding new webhook endpoints** (without removing existing)
6. **Improving error messages** (text changes)
7. **Performance optimizations** (without behavior changes)
8. **Bug fixes** (restoring documented behavior)
9. **Documentation updates**
10. **Internal refactoring** (without public API changes)

---

## Migration Guide Template

For all future breaking changes, use this template:

````markdown
## Version X.Y.Z - "Codename" (YYYY-MM-DD)

### Summary

Brief description of breaking changes in this release.

### Breaking Change 1: [Title]

**Category**: API Change / Database Schema / Configuration / Event Structure / Behavior Change / Dependency Upgrade

**Reason**: Why this change was necessary.

**Impact**: Who is affected and how.

#### Before (Old Code)

```php
// Example of old code that will break
```
````

#### After (New Code)

```php
// Example of new code to migrate to
```

#### Migration Steps

1. Step 1: Specific action to take
2. Step 2: Update code references
3. Step 3: Run migrations (if applicable)
4. Step 4: Test your implementation

#### Rollback Plan

If you cannot migrate immediately:

- Temporary workaround or compatibility layer
- Timeline for forced upgrade

---

````

---

## Deprecation Policy

### Deprecation Timeline

Before introducing breaking changes, features will be deprecated:

1. **Minor Release (0.X.0)**: Feature marked as deprecated
   - `@deprecated` PHPDoc tag added
   - Trigger `E_USER_DEPRECATED` notice
   - Documentation updated with migration path

2. **Next Minor Release (0.X+1.0)**: Deprecation notice continues
   - Repeated warnings in logs
   - Documentation emphasizes urgency

3. **Next Major Release (X.0.0)**: Feature removed
   - Breaking change documented here
   - Migration guide provided

### Example Deprecation Notice

```php
/**
 * @deprecated since 1.5.0, use FlightPlan::fromArray() instead
 * @see FlightPlan::fromArray()
 */
public function createFromArray(array $data): FlightPlan
{
    trigger_error(
        'Method ' . __METHOD__ . ' is deprecated, use FlightPlan::fromArray() instead',
        E_USER_DEPRECATED
    );

    return $this->fromArray($data);
}
````

---

## Version Compatibility Matrix

| Bird Flock Version | PHP Version | Laravel Version | Breaking Changes |
| ------------------ | ----------- | --------------- | ---------------- | --- | --- | --- | ---- |
| 1.0.0 (Phoenix)    | ≥8.3        | ^11.0           | None (initial)   |
| 1.1.0 (Albatross)  | ≥8.3        | ^11.0           | None             |
| 1.2.0 (Condor)     | ≥8.3        | ^10             |                  | ^11 |     | ^12 | None |

---

## Emergency Breaking Changes

In rare cases (security vulnerabilities, critical bugs), breaking changes may be introduced in patch releases:

1. Security vulnerability requires immediate API change
2. Data corruption risk requires schema change
3. Provider API deprecation forces adaptation

Such changes will:

- Be clearly marked as **EMERGENCY BREAKING CHANGE** in changelog
- Include detailed justification
- Provide automated migration tools when possible
- Be communicated via all channels (GitHub, email, documentation)

---

## Future Breaking Changes Under Consideration

See `doc/open-questions-and-assumptions.md` for features that may introduce breaking changes in future versions:

- **Multi-tenancy support**: May require adding `tenant_id` to database schema
- **Multi-provider routing**: May change how providers are configured
- **Idempotency key expiration**: May change deduplication behavior
- **Message cancellation**: May require new database fields

---

## Contact

For questions about breaking changes or migration assistance:

**Maintainer**: Gabriel Ruelas <gruelas@gruelas.com>

**Repository**: https://github.com/EquidnaMX/bird-flock

**Issue Tracker**: https://github.com/EquidnaMX/bird-flock/issues
