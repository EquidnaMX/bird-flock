<?php

return [
    'default_queue' => env('BIRD_FLOCK_DEFAULT_QUEUE', env('MESSAGING_QUEUE', 'default')),
    'tables' => [
        'prefix' => env('BIRD_FLOCK_TABLE_PREFIX', 'bird_flock_'),
        'outbound_messages' => env(
            'BIRD_FLOCK_OUTBOUND_TABLE',
            env('BIRD_FLOCK_TABLE_PREFIX', 'bird_flock_') . 'outbound_messages'
        ),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from_sms' => env('TWILIO_FROM_SMS'),
        'from_whatsapp' => env('TWILIO_FROM_WHATSAPP'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        'status_webhook_url' => env('TWILIO_STATUS_WEBHOOK_URL'),
        'sandbox_mode' => env('TWILIO_SANDBOX_MODE', true),
    ],

    'sendgrid' => [
        'api_key' => env('SENDGRID_API_KEY'),
        'from_email' => env('SENDGRID_FROM_EMAIL'),
        'from_name' => env('SENDGRID_FROM_NAME'),
        'reply_to' => env('SENDGRID_REPLY_TO'),
        'templates' => [],
        'webhook_public_key' => env('SENDGRID_WEBHOOK_PUBLIC_KEY'),
        'require_signed_webhooks' => env('SENDGRID_REQUIRE_SIGNED_WEBHOOKS', false),
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
        'table' => env(
            'BIRD_FLOCK_DLQ_TABLE',
            env('BIRD_FLOCK_TABLE_PREFIX', 'bird_flock_') . 'dead_letters'
        ),
    ],
];
