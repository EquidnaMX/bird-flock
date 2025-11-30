# Open Questions & Assumptions

This document lists unresolved questions, assumptions, and areas requiring clarification from package maintainers.

---

## Deployment & Infrastructure

### Question 1: Multi-Tenancy Support

**Status**: Assumption made

**Question**: Is Bird Flock designed for multi-tenant applications? If so, how should tenant isolation be implemented?

**Current Assumption**:

- Package is tenant-agnostic; idempotency keys should include tenant ID (e.g., `tenant:42:order:1234:sms`)
- No built-in tenant scoping in queries or models

**Required Clarification**:

- Should repository queries automatically scope by tenant?
- Should migrations include `tenant_id` column?
- How to prevent cross-tenant message access?

**Impact**: High (affects security and isolation in multi-tenant deployments)

---

### Question 2: Horizontal Scaling Strategy

**Status**: Assumption made

**Question**: What is the recommended horizontal scaling strategy for high-volume deployments?

**Current Assumption**:

- Multiple queue workers can process jobs in parallel
- Circuit breaker state is shared via cache (Redis recommended)
- No built-in distributed locking for batch operations

**Required Clarification**:

- Should batch dispatch use distributed locks?
- Are there any known concurrency issues with SQLite or file-based cache?
- Recommended worker count per server?

**Impact**: Medium (affects performance tuning and capacity planning)

---

### Question 3: Database Transaction Isolation

**Status**: Assumption made

**Question**: What transaction isolation level is recommended for the `outbound_messages` table?

**Current Assumption**:

- Default Laravel/database isolation level (typically READ COMMITTED)
- Idempotency relies on unique constraint enforcement

**Required Clarification**:

- Should `SERIALIZABLE` isolation be used for idempotency operations?
- Are there known race conditions in PostgreSQL vs MySQL?

**Impact**: Low (works with defaults, but clarification improves reliability)

---

## Configuration & Providers

### Question 4: Multi-Provider Routing

**Status**: Not implemented

**Question**: How should Bird Flock handle multiple providers for the same channel (e.g., Twilio and Vonage for SMS)?

**Current State**:

- Only first configured provider is used per channel
- No failover or load balancing between providers

**Desired Clarification**:

- Is multi-provider routing planned?
- Should it be round-robin, weighted, or failover-based?
- How to configure provider priority?

**Impact**: High (affects reliability and cost optimization)

---

### Question 5: Template Management

**Status**: Minimal support

**Question**: How should dynamic email templates be managed (SendGrid, Mailgun templates)?

**Current State**:

- `FlightPlan` accepts `templateKey` and `templateData`
- No built-in template versioning or validation
- Config file has empty `templates` array

**Desired Clarification**:

- Should package include template management UI or migrations?
- How to handle template updates without breaking idempotency?
- Validation strategy for template variables?

**Impact**: Medium (affects ease of use for marketing campaigns)

---

### Question 6: Webhook Authentication Strategies

**Status**: Signature validation only

**Question**: Should Bird Flock support alternative webhook authentication methods?

**Current State**:

- All webhooks use signature validation (HMAC, public key)
- No support for IP whitelisting, OAuth, or basic auth

**Desired Clarification**:

- Should IP whitelisting be built-in?
- How to handle providers with multiple webhook IPs (CDN, load balancers)?

**Impact**: Low (signature validation is industry standard)

---

## Business Logic & Domain Rules

### Question 7: Idempotency Key Expiration

**Status**: No expiration

**Question**: Should idempotency keys expire after a certain period (e.g., 30 days)?

**Current State**:

- Idempotency keys are unique forever
- No automatic cleanup

**Desired Clarification**:

- Is permanent retention acceptable?
- If expiration is needed, what is the safe window (7/30/90 days)?
- Should expiration be configurable per use case?

**Impact**: Medium (affects database growth and long-term maintenance)

---

### Question 8: Message Cancellation

**Status**: Not supported

**Question**: Should Bird Flock support cancelling scheduled messages before they are sent?

**Current State**:

- Once dispatched, messages cannot be cancelled
- Scheduled messages (via `sendAt`) cannot be revoked

**Desired Clarification**:

- Is cancellation a planned feature?
- How to handle in-flight jobs (already picked up by worker)?

**Impact**: Medium (common feature request for scheduled campaigns)

---

### Question 9: Rate Limiting per Recipient

**Status**: Not implemented

**Question**: Should Bird Flock enforce rate limits per recipient (e.g., max 5 SMS per hour to same number)?

**Current State**:

- No recipient-level rate limiting
- Idempotency prevents exact duplicates, but not similar messages

**Desired Clarification**:

- Should rate limiting be built into package or left to application logic?
- What is the recommended pattern for frequency capping?

**Impact**: High (affects compliance and user experience)

---

### Question 10: Dead-Letter Retention Policy

**Status**: No automatic cleanup

**Question**: Should dead-letter entries be automatically purged after a retention period?

**Current State**:

- DLQ entries persist forever
- Manual purge via `bird-flock:dead-letter purge`

**Desired Clarification**:

- What is the recommended retention period (30/60/90 days)?
- Should cleanup be scheduled via artisan command?

**Impact**: Low (manual purge is workable, but automation would improve ops)

---

## Testing & Quality

### Question 11: Integration Test Strategy

**Status**: No integration tests (per TestingScope)

**Question**: Who is responsible for integration tests, and when will they be implemented?

**Current State**:

- Unit tests only (per `TestingScope.instructions.md`)
- Feature, integration, and E2E tests not implemented

**Desired Clarification**:

- Is there a QA team responsible for integration tests?
- What is the timeline for full test coverage?
- Should agents contribute integration test plans?

**Impact**: Medium (affects confidence in production deployments)

---

### Question 12: Provider Sandbox Testing

**Status**: Basic sandbox support for Twilio

**Question**: How should developers test against provider sandboxes without production credentials?

**Current State**:

- Twilio sandbox mode configurable via `TWILIO_SANDBOX_MODE`
- No built-in sandbox support for SendGrid, Vonage, Mailgun

**Desired Clarification**:

- Should package include mock/fake providers for local development?
- How to seed test data in sandbox environments?

**Impact**: Low (workaround: use test credentials)

---

## Monitoring & Observability

### Question 13: Metrics Backend Integration

**Status**: Default implementation logs metrics

**Question**: What is the recommended production metrics backend?

**Current State**:

- `MetricsCollectorInterface` implemented, but default logs metrics as structured logs
- No built-in integration with Prometheus, Datadog, New Relic

**Desired Clarification**:

- Should package include official integrations (composer packages)?
- Which metrics backends are prioritized?

**Impact**: Medium (affects production monitoring setup)

---

### Question 14: Alerting Thresholds

**Status**: Recommendations provided, no enforcement

**Question**: Should Bird Flock include pre-configured alerting rules (e.g., Prometheus alerts, Datadog monitors)?

**Current State**:

- Documentation includes suggested alert conditions
- No pre-built alert definitions

**Desired Clarification**:

- Should package ship with alert templates?
- What format (Prometheus YAML, Terraform, etc.)?

**Impact**: Low (operators can create alerts from docs)

---

## Security & Compliance

### Question 15: PII/GDPR Compliance

**Status**: Minimal built-in support

**Question**: How should Bird Flock handle PII (phone numbers, emails) for GDPR/CCPA compliance?

**Current State**:

- Phone numbers and emails stored in plaintext in `outbound_messages` table
- Masking used only in logs (`Masking` class)

**Desired Clarification**:

- Should messages be encrypted at rest?
- How to handle "right to deletion" requests?
- Should package include GDPR compliance helpers?

**Impact**: High (affects legal compliance)

---

### Question 16: Webhook Source Verification

**Status**: Signature validation only

**Question**: Should Bird Flock validate webhook source IPs to prevent spoofing?

**Current State**:

- Signature validation provides cryptographic proof
- No IP whitelist enforcement

**Desired Clarification**:

- Is IP whitelisting necessary (defense-in-depth)?
- How to maintain IP lists as providers update infrastructure?

**Impact**: Low (signature validation is sufficient)

---

## Performance & Optimization

### Question 17: Large Batch Dispatch Performance

**Status**: Chunked inserts implemented

**Question**: What is the maximum safe batch size for `BirdFlock::dispatchBatch()`?

**Current State**:

- Batch insert uses chunks (default 500 per chunk)
- No documented maximum batch size

**Desired Clarification**:

- What is the tested/recommended max batch size (10k, 100k, 1M)?
- Should batches > 10k be split into multiple transactions?

**Impact**: Medium (affects bulk campaign performance)

---

### Question 18: Database Query Optimization

**Status**: Basic indexes present

**Question**: Are there recommended additional indexes for high-volume deployments?

**Current State**:

- Migrations include primary key, idempotency key unique index
- No composite indexes for status/channel/timestamp queries

**Desired Clarification**:

- Should indexes be added for common query patterns (status + queued_at, channel + status)?
- Impact on write performance?

**Impact**: Low (can be added as needed)

---

## Documentation & Onboarding

### Question 19: Video Tutorials or Interactive Guides

**Status**: Text documentation only

**Question**: Are video tutorials or interactive onboarding planned?

**Desired Clarification**:

- Should maintainers create screencasts?
- Interactive CLI setup wizard?

**Impact**: Low (text docs are sufficient, but multimedia would improve adoption)

---

### Question 20: Migration from Other Messaging Libraries

**Status**: No migration guides

**Question**: How to migrate from other Laravel messaging packages (e.g., `laravel-notification-channels/*`)?

**Desired Clarification**:

- Should package include migration scripts or adapters?
- Compatibility shims for common packages?

**Impact**: Low (case-by-case migration is workable)

---

## Summary

**High Priority Questions** (require clarification before production use):

- Multi-tenancy support (#1)
- Multi-provider routing (#4)
- Rate limiting per recipient (#9)
- PII/GDPR compliance (#15)

**Medium Priority Questions** (affect convenience and performance):

- Horizontal scaling strategy (#2)
- Template management (#5)
- Idempotency key expiration (#7)
- Message cancellation (#8)
- Metrics backend integration (#13)
- Large batch performance (#17)

**Low Priority Questions** (nice-to-haves, workarounds exist):

- Database transaction isolation (#3)
- Webhook authentication strategies (#6)
- Dead-letter retention policy (#10)
- Provider sandbox testing (#12)
- Alerting thresholds (#14)
- Webhook source verification (#16)
- Database query optimization (#18)
- Video tutorials (#19)
- Migration guides (#20)

---

## Contact

For clarifications, please contact:

**Package Maintainer**: Gabriel Ruelas ([gruelas@gruelas.com](mailto:gruelas@gruelas.com))

Or open an issue in the package repository.
