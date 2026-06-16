<?php

return [
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
];
