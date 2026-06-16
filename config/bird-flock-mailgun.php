<?php

return [
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
];
