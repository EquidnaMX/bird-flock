<?php

use Illuminate\Support\Facades\Route;
use Equidna\BirdFlock\Http\Controllers\TwilioWebhookController;
use Equidna\BirdFlock\Http\Controllers\SendgridWebhookController;
use Equidna\BirdFlock\Http\Controllers\VonageWebhookController;
use Equidna\BirdFlock\Http\Controllers\MailgunWebhookController;
use Equidna\BirdFlock\Http\Controllers\HealthCheckController;

Route::prefix('bird-flock')->group(function () {
    // Health check endpoints (no rate limit) - configurable via BIRD_FLOCK_HEALTH_ENABLED
    if (config('bird-flock.health.enabled', true)) {
        Route::get(
            '/health',
            [HealthCheckController::class, 'check']
        )->name('bird-flock.health');

        Route::get(
            '/health/circuit-breakers',
            [HealthCheckController::class, 'circuitBreakers']
        )->name('bird-flock.health.circuits');
    }

    // Webhook routes with rate limiting (60 requests per minute)
    Route::middleware('throttle:60,1')->group(function () {
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

        Route::post(
            '/webhooks/vonage/delivery-receipt',
            [VonageWebhookController::class, 'deliveryReceipt']
        )->name('bird-flock.vonage.dlr');

        Route::post(
            '/webhooks/vonage/inbound',
            [VonageWebhookController::class, 'inbound']
        )->name('bird-flock.vonage.inbound');

        Route::post(
            '/webhooks/mailgun/events',
            [MailgunWebhookController::class, 'events']
        )->name('bird-flock.mailgun.events');
    });
});
