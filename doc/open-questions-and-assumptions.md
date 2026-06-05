# Open Questions & Assumptions

## Standards and Documentation Inputs

1. Coding Standards Guide and PHPDoc Style Guide were referenced in prompt context but not found as explicit files in this repository snapshot.
   Why it matters:

- Terminology and formatting choices may need alignment with private/internal standards.
  Needed:
- Canonical guide locations or files.

## Deployment

1. Production topology guidance (single node vs horizontal workers) is not defined in package repo.
   Why it matters:

- Queue throughput and circuit-breaker cache consistency depend on topology.
  Needed:
- Recommended deployment architecture baseline.

## Routes and Security

1. Webhook hardening strategy beyond signature validation is not documented.
   Why it matters:

- Teams need a standard perimeter policy (WAF/IP allowlists/proxy filtering).
  Needed:
- Official security guidance for public webhook endpoints.

2. Rate-limit configuration mismatch.
   Observed:

- Routes use static `throttle:60,1` while custom middleware and config key also exist.
  Why it matters:
- Teams may assume config value is dynamically applied when it currently is not.
  Needed:
- Confirm intended rate-limiting mechanism.

## Provider Routing

1. Sender selection policy is ambiguous.
   Observed:

- Twilio/Vonage and SendGrid/Mailgun sender classes coexist.
- Current factory routing prioritizes Twilio (sms/whatsapp) and Mailgun (email).
  Why it matters:
- Integrators may expect selectable/fallback providers.
  Needed:
- Official provider selection/failover strategy and config contract.

## Data Lifecycle

1. Idempotency retention policy is not explicit.
   Why it matters:

- Long-term table growth and replay semantics.
  Needed:
- Recommended cleanup/retention window policy.

2. DLQ retention automation is not built-in.
   Why it matters:

- Operational storage growth.
  Needed:
- Recommended purge cadence and automation approach.

## Testing

1. PHPUnit suite mapping may omit expected tests by default.
   Observed:

- `Unit` suite targets `tests/Messaging` in current config.
  Why it matters:
- CI may not run all tests in `tests/Unit` unless explicitly configured.
  Needed:
- Maintainer-confirmed CI strategy.

2. Integration test ownership/timeline is unclear.
   Observed:

- Integration plan file exists, but no implemented integration suite in repo.
  Why it matters:
- End-to-end confidence across DB/queue/provider boundaries.
  Needed:
- Owner and implementation plan.
