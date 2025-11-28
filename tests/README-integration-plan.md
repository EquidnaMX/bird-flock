# Integration Test Plan — DB Unique Index & Idempotency Deduplication

This plan is for the non‑unit test owners (QA/Platform) to cover the DB behavior end‑to‑end. Agents only write unit tests.

## Goal

Verify that the DB unique index on `idempotencyKey` prevents duplicate rows and that `BirdFlock::dispatch()` behaves correctly under concurrent inserts, returning the canonical message id.

## Setup (SQLite in‑memory)

1. Configure a dedicated test suite `Integration` in `phpunit.xml` that points to `tests/Integration/**`.
2. Use an SQLite `:memory:` connection and apply the package migrations programmatically before each test (or once per suite):

```php
// Bootstrap snippet (example)
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

$db = new DB();
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$db->bootEloquent();
$db->setAsGlobal();

// Run outbound_messages migration (port from database/migrations/create_outbound_messages_table.php)
DB::schema()->create('bird_flock_outbound_messages', function (Blueprint $t) {
    $t->string('id_outboundMessage')->primary();
    $t->string('channel');
    $t->string('to');
    $t->string('subject')->nullable();
    $t->string('templateKey')->nullable();
    $t->json('payload');
    $t->string('status');
    $t->string('idempotencyKey')->nullable()->unique('uniq_idempotencyKey');
    $t->timestamp('queuedAt')->nullable();
});
```

3. Bind the package repository to an Eloquent implementation that uses the same SQLite connection.

## Scenarios

1. Unique index enforcement

- Insert one row with `idempotencyKey = 'K1'`.
- Attempt to insert another row with the same key — expect a unique‑constraint error at the DB level.

2. Concurrent create (race) — deduplication behavior

- Simulate two near‑simultaneous `BirdFlock::dispatch()` calls with the same `idempotencyKey`.
- Expect one create to succeed; the other to catch the unique‑constraint error, re‑query by key, and return the existing `id_outboundMessage`.
- Assert both dispatch calls return the same message id.

Implementation hints:

- Use parallel processes or interleave the calls with a hook in the repository to delay the first transaction’s commit briefly.
- Alternatively, run two dispatches back‑to‑back and mock a small transaction delay in the repository create method via `usleep` to broaden the race window.

3. Existing failed record → reset for retry

- Seed a row with status `failed` and matching `idempotencyKey`.
- Call `BirdFlock::dispatch()`; expect the same message id to be reused and status reset to `queued`.

## Observability assertions

- Listen to events:
  - `MessageCreateConflict` during the race scenario.
  - `MessageDuplicateSkipped` when the status is already `queued/sending/sent/delivered`.
- Optionally assert metrics collector increments if a real collector is bound.

## Notes

- Keep these tests separate from the Unit suite and guarded under an `Integration` testsuite. They will touch a real DB connection and schema.
- The package’s unit tests already cover the logic path with mocked `QueryException`. This integration plan validates DB constraints and transaction timing in a realistic environment.
