<?php

return [
    'username' => env('LABSMOBILE_USERNAME'),
    'token' => env('LABSMOBILE_TOKEN'),
    'from_sms' => env('LABSMOBILE_FROM_SMS'),
    'ack_url' => env('LABSMOBILE_ACK_URL'),
    'webhook_token' => env('LABSMOBILE_WEBHOOK_TOKEN'),
    'test' => env('LABSMOBILE_TEST', false),
    'long' => env('LABSMOBILE_LONG', false),
    'ucs2' => env('LABSMOBILE_UCS2', false),
    'shortlink' => env('LABSMOBILE_SHORTLINK', false),
    'endpoint' => env('LABSMOBILE_ENDPOINT', 'https://api.labsmobile.com/json/send'),
    'timeout' => env('LABSMOBILE_TIMEOUT', 30),
    'connect_timeout' => env('LABSMOBILE_CONNECT_TIMEOUT', 10),
];
