Bird Flock CLI

This document contains expanded examples for the Bird Flock artisan commands.

Send test WhatsApp

- Basic: send a text message

  php artisan bird-flock:send-whatsapp "+14155551234" "Hello from Bird Flock"

- With media and idempotency key (repeatable `--media`):

  php artisan bird-flock:send-whatsapp "+14155551234" "Hi with image" --media="https://example.com/image.jpg" --media="https://example.com/file.pdf" --idempotency="order-1234-whatsapp"

Send test SMS

- Basic SMS

  php artisan bird-flock:send-sms "+14155551234" "Your OTP is 1234"

- With idempotency key:

  php artisan bird-flock:send-sms "+14155551234" "Your OTP is 1234" --idempotency="otp-2025-03-01-1234"

Send test Email

- Simple text email

  php artisan bird-flock:send-email "to@example.com" --text="Plain text body"

- HTML + text with idempotency:

  php artisan bird-flock:send-email "to@example.com" --text="Plain" --html="<p>Hello</p>" --idempotency="welcome-2025-03-01"

Dead-letter management

- List entries (paginated/limited):

  php artisan bird-flock:dead-letter list --limit=50

- Replay a single message (careful: replay may re-send external providers):

  php artisan bird-flock:dead-letter replay {message_id} --force

- Purge by message id or time range:

  php artisan bird-flock:dead-letter purge {message_id}
  php artisan bird-flock:dead-letter purge --before="2025-01-01"

Notes

- Idempotency: pass a stable `--idempotency` key to deduplicate logically identical sends. The library persists `idempotencyKey` to `outbound_messages` and enforces a DB unique index. For concurrency-safety prefer stable keys derived from the domain (order IDs, webhook event IDs, etc.).
- Safety: replaying dead-letter entries should be done with caution; confirm idempotency keys to avoid duplicate downstream side effects.
- For `whatsapp` sends, use E.164 recipients and include `whatsapp:` prefix on `From` values when configuring raw from addresses; prefer `TWILIO_MESSAGING_SERVICE_SID` in production for number management.

See README.md for quick setup and configuration examples.
