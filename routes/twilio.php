<?php

use Illuminate\Support\Facades\Route;
use Equidna\BirdFlock\Http\Controllers\TwilioWebhookController;

Route::prefix('bird-flock')->middleware('throttle:60,1')->group(function () {
    Route::post(
        '/webhooks/twilio/status',
        [TwilioWebhookController::class, 'status']
    )->name('bird-flock.twilio.status');

    Route::post(
        '/webhooks/twilio/inbound',
        [TwilioWebhookController::class, 'inbound']
    )->name('bird-flock.twilio.inbound');
});
