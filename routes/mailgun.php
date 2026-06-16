<?php

use Illuminate\Support\Facades\Route;
use Equidna\BirdFlock\Http\Controllers\MailgunWebhookController;

Route::prefix('bird-flock')->middleware('throttle:60,1')->group(function () {
    Route::post(
        '/webhooks/mailgun/events',
        [MailgunWebhookController::class, 'events']
    )->name('bird-flock.mailgun.events');
});
