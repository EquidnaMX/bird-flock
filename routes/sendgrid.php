<?php

use Illuminate\Support\Facades\Route;
use Equidna\BirdFlock\Http\Controllers\SendgridWebhookController;

Route::prefix('bird-flock')->middleware('throttle:60,1')->group(function () {
    Route::post(
        '/webhooks/sendgrid/events',
        [SendgridWebhookController::class, 'events']
    )->name('bird-flock.sendgrid.events');
});
