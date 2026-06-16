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
    'default_queue' => env('BIRD_FLOCK_DEFAULT_QUEUE', 'default'),

    'tables' => [
        'prefix' => env('BIRD_FLOCK_TABLE_PREFIX', 'bird_flock_'),
        'outbound_messages' => env('BIRD_FLOCK_TABLE_PREFIX', 'bird_flock_') . 'outbound_messages',
    ],

    'channels' => [
        'sms' => [
            'strategy' => env('BIRD_FLOCK_SMS_VENDOR_STRATEGY', 'round_robin'),
            'retry' => [
                'max_attempts' => env('BIRD_FLOCK_SMS_MAX_ATTEMPTS', 3),
                'base_delay_ms' => env('BIRD_FLOCK_SMS_BASE_DELAY_MS', 1000),
                'max_delay_ms' => env('BIRD_FLOCK_SMS_MAX_DELAY_MS', 60000),
            ],
            'senders' => [
                'twilio' => \Equidna\BirdFlock\Senders\Twilio\TwilioSmsSenderDefinition::class,
            ],
        ],
        'whatsapp' => [
            'strategy' => env('BIRD_FLOCK_WHATSAPP_VENDOR_STRATEGY', 'round_robin'),
            'retry' => [
                'max_attempts' => env('BIRD_FLOCK_WHATSAPP_MAX_ATTEMPTS', 3),
                'base_delay_ms' => env('BIRD_FLOCK_WHATSAPP_BASE_DELAY_MS', 1000),
                'max_delay_ms' => env('BIRD_FLOCK_WHATSAPP_MAX_DELAY_MS', 60000),
            ],
            'senders' => [
                'twilio' => \Equidna\BirdFlock\Senders\Twilio\TwilioWhatsappSenderDefinition::class,
            ],
        ],
        'email' => [
            'strategy' => env('BIRD_FLOCK_EMAIL_VENDOR_STRATEGY', 'round_robin'),
            'retry' => [
                'max_attempts' => env('BIRD_FLOCK_EMAIL_MAX_ATTEMPTS', 3),
                'base_delay_ms' => env('BIRD_FLOCK_EMAIL_BASE_DELAY_MS', 1000),
                'max_delay_ms' => env('BIRD_FLOCK_EMAIL_MAX_DELAY_MS', 60000),
            ],
            'senders' => [
                'mailgun' => \Equidna\BirdFlock\Senders\Mailgun\MailgunEmailSenderDefinition::class,
            ],
        ],
    ],

    'logging' => [
        'enabled' => env('BIRD_FLOCK_LOGGING_ENABLED', true),
        'channel' => env('BIRD_FLOCK_LOG_CHANNEL'),
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
