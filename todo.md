# Improvements / TODOs

This file lists suggested improvements and follow-ups to make idempotency, docs, and robustness stronger.

- Make `dispatch()` DB-race safe: catch unique constraint errors on create and return existing message id (handle concurrent creates).
  - IMPLEMENTED: `src/BirdFlock.php` now wraps the repository `create()` call in a bounded retry loop and catches `QueryException` unique-constraint errors. On conflict it re-queries `findByIdempotencyKey` and returns the existing message id when present.
  - Open: add unit tests that simulate `QueryException` races (see item below).
- Add unit tests for idempotency behavior:
  - dispatch with same key returns same id when queued
  - failed record + dispatch with same key resets and requeues
  - concurrent dispatch attempts simulate unique constraint race
- Add an `.env.example` showing `TWILIO_MESSAGING_SERVICE_SID` and `TWILIO_FROM_*` fallback examples.
- Add short comments to `config/bird-flock.php` explaining Messaging Service vs explicit From and the idempotency key usage.
- Add a small integration test (isolated, using in-memory DB) that verifies unique index exists and deduplication works.
- Add monitoring/metrics hooks for:
  - duplicate dispatches (`bird-flock.dispatch.duplicate_skipped`)
  - idempotency collisions
  - frequent retries (alerting threshold)
- Document recommended idempotency key format in README (include tenant/account prefix for multi-tenant apps).

- Validate and normalize all phone/env inputs (trim quotes/spaces).
- Add boot-time config validation with explicit errors/warnings.
- Make package sandbox-aware (warn + docs; optionally use sandbox From).
- Log Twilio error_code + error_message and expose it in exception messages.
- Add unit tests for normalization and sandbox logic.
- Document .env examples and sandbox opt-in steps.