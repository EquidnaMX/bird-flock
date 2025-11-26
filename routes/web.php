<?php

use Illuminate\Support\Facades\Route;
use Equidna\BirdFlock\Http\Controllers\TwilioWebhookController;
use Equidna\BirdFlock\Http\Controllers\SendgridWebhookController;

Route::prefix('bird-flock')->group(function () {
    Route::post(
        '/webhooks/twilio/status',
        [TwilioWebhookController::class, 'status']
    )->name('bird-flock.twilio.status');

    Route::post(
        '/webhooks/twilio/inbound',
        [TwilioWebhookController::class, 'inbound']
    )->name('bird-flock.twilio.inbound');

    Route::post(
        '/webhooks/sendgrid/events',
        [SendgridWebhookController::class, 'events']
    )->name('bird-flock.sendgrid.events');
});
