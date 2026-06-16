<?php

return [
    'api_key' => env('VONAGE_API_KEY'),
    'api_secret' => env('VONAGE_API_SECRET'),
    'from_sms' => env('VONAGE_FROM_SMS'),
    'timeout' => env('VONAGE_TIMEOUT', 30),
    'signature_secret' => env('VONAGE_SIGNATURE_SECRET'),
    'require_signed_webhooks' => env('VONAGE_REQUIRE_SIGNED_WEBHOOKS', true),
    'delivery_receipt_url' => env('VONAGE_DELIVERY_RECEIPT_URL'),
    'inbound_url' => env('VONAGE_INBOUND_URL'),
];
