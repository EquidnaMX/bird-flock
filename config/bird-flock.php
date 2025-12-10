<?php

/**
 * Bird Flock configuration.
 *
 * Messaging Service vs explicit FROM (Twilio):
 * - If TWILIO_MESSAGING_SERVICE_SID is set Twilio selects the sender; explicit from_* values are ignored.
 * - If not set fallback to TWILIO_FROM_SMS / TWILIO_FROM_WHATSAPP (WhatsApp must include the `whatsapp:` prefix).
 *
 * Idempotency:
 * - Messages may include an idempotency key stored on outbound_messages.
 * - Keys include tenant/account + domain + purpose (e.g. `tenant:42:order:1234:shipping-sms`).
 * - Unique index enforces one row; dispatcher is race safe and reuses on concurrent creates.
 */

return [
    'default_queue' => env(
        'BIRD_FLOCK_DEFAULT_QUEUE',
        env('MESSAGING_QUEUE', 'default')
    ),
    'tables' => [
        'prefix' => env('BIRD_FLOCK_TABLE_PREFIX', 'bird_flock_'),
        'outbound_messages' => env('BIRD_FLOCK_TABLE_PREFIX', 'bird_flock_') . 'outbound_messages',
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from_sms' => env('TWILIO_FROM_SMS'),
        'from_whatsapp' => env('TWILIO_FROM_WHATSAPP'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        'status_webhook_url' => env('TWILIO_STATUS_WEBHOOK_URL'),
        'sandbox_mode' => env('TWILIO_SANDBOX_MODE', true),
        'sandbox_from' => env('TWILIO_SANDBOX_FROM'),
        'timeout' => env('TWILIO_TIMEOUT', 30),
        'connect_timeout' => env('TWILIO_CONNECT_TIMEOUT', 10),
    ],

    'sendgrid' => [
        'api_key' => env('SENDGRID_API_KEY'),
        'from_email' => env('SENDGRID_FROM_EMAIL'),
        'from_name' => env('SENDGRID_FROM_NAME'),
        'reply_to' => env('SENDGRID_REPLY_TO'),
        'templates' => [],
        'webhook_public_key' => env('SENDGRID_WEBHOOK_PUBLIC_KEY'),
        'require_signed_webhooks' => env('SENDGRID_REQUIRE_SIGNED_WEBHOOKS', true),
        'timeout' => env('SENDGRID_TIMEOUT', 30),
        'connect_timeout' => env('SENDGRID_CONNECT_TIMEOUT', 10),
    ],

    'vonage' => [
        'api_key' => env('VONAGE_API_KEY'),
        'api_secret' => env('VONAGE_API_SECRET'),
        'from_sms' => env('VONAGE_FROM_SMS'),
        'timeout' => env('VONAGE_TIMEOUT', 30),
        'signature_secret' => env('VONAGE_SIGNATURE_SECRET'),
        'require_signed_webhooks' => env('VONAGE_REQUIRE_SIGNED_WEBHOOKS', true),
        'delivery_receipt_url' => env('VONAGE_DELIVERY_RECEIPT_URL'),
        'inbound_url' => env('VONAGE_INBOUND_URL'),
    ],

    'mailgun' => [
        'api_key' => env('MAILGUN_API_KEY'),
        'domain' => env('MAILGUN_DOMAIN'),
        'from_email' => env('MAILGUN_FROM_EMAIL'),
        'from_name' => env('MAILGUN_FROM_NAME'),
        'reply_to' => env('MAILGUN_REPLY_TO'),
        'templates' => [],
        'timeout' => env('MAILGUN_TIMEOUT', 30),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'webhook_signing_key' => env('MAILGUN_WEBHOOK_SIGNING_KEY'),
        'require_signed_webhooks' => env('MAILGUN_REQUIRE_SIGNED_WEBHOOKS', true),
        'webhook_url' => env('MAILGUN_WEBHOOK_URL'),
    ],

    'logging' => [
        'enabled' => env('BIRD_FLOCK_LOGGING_ENABLED', true),
        'channel' => env('BIRD_FLOCK_LOG_CHANNEL'),
    ],

    'retry' => [
        'channels' => [
            'sms' => [
                'max_attempts' => env('BIRD_FLOCK_SMS_MAX_ATTEMPTS', 3),
                'base_delay_ms' => env('BIRD_FLOCK_SMS_BASE_DELAY_MS', 1000),
                'max_delay_ms' => env('BIRD_FLOCK_SMS_MAX_DELAY_MS', 60000),
            ],
            'whatsapp' => [
                'max_attempts' => env('BIRD_FLOCK_WHATSAPP_MAX_ATTEMPTS', 3),
                'base_delay_ms' => env('BIRD_FLOCK_WHATSAPP_BASE_DELAY_MS', 1000),
                'max_delay_ms' => env('BIRD_FLOCK_WHATSAPP_MAX_DELAY_MS', 60000),
            ],
            'email' => [
                'max_attempts' => env('BIRD_FLOCK_EMAIL_MAX_ATTEMPTS', 3),
                'base_delay_ms' => env('BIRD_FLOCK_EMAIL_BASE_DELAY_MS', 1000),
                'max_delay_ms' => env('BIRD_FLOCK_EMAIL_MAX_DELAY_MS', 60000),
            ],
        ],
    ],

    'dead_letter' => [
        'enabled' => env('BIRD_FLOCK_DLQ_ENABLED', true),
        'table' => env('BIRD_FLOCK_TABLE_PREFIX', 'bird_flock_') . 'dead_letters',
    ],

    'circuit_breaker' => [
        'failure_threshold' => env('BIRD_FLOCK_CIRCUIT_BREAKER_FAILURE_THRESHOLD', 5),
        'timeout' => env('BIRD_FLOCK_CIRCUIT_BREAKER_TIMEOUT', 60),
        'success_threshold' => env('BIRD_FLOCK_CIRCUIT_BREAKER_SUCCESS_THRESHOLD', 2),
    ],

    // Maximum payload size in bytes (default 256KB to stay under queue limits)
    'max_payload_size' => env('BIRD_FLOCK_MAX_PAYLOAD_SIZE', 262144),

    // Batch insert chunk size to avoid DB packet size limits
    'batch_insert_chunk_size' => env('BIRD_FLOCK_BATCH_INSERT_CHUNK_SIZE', 500),

    // Webhook rate limit (requests per minute per IP)
    'webhook_rate_limit' => env('BIRD_FLOCK_WEBHOOK_RATE_LIMIT', 60),

    // Health check endpoints
    'health' => [
        'enabled' => env('BIRD_FLOCK_HEALTH_ENABLED', true),
    ],
];
