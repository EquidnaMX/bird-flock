<?php

return [
    'api_key' => env('SENDGRID_API_KEY'),
    'from_email' => env('SENDGRID_FROM_EMAIL'),
    'from_name' => env('SENDGRID_FROM_NAME'),
    'reply_to' => env('SENDGRID_REPLY_TO'),
    'templates' => [],
    'webhook_public_key' => env('SENDGRID_WEBHOOK_PUBLIC_KEY'),
    'require_signed_webhooks' => env('SENDGRID_REQUIRE_SIGNED_WEBHOOKS', true),
    'timeout' => env('SENDGRID_TIMEOUT', 30),
    'connect_timeout' => env('SENDGRID_CONNECT_TIMEOUT', 10),
];
