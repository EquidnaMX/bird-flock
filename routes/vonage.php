<?php

use Illuminate\Support\Facades\Route;
use Equidna\BirdFlock\Http\Controllers\VonageWebhookController;

Route::prefix('bird-flock')->middleware('throttle:60,1')->group(function () {
    Route::post(
        '/webhooks/vonage/delivery-receipt',
        [VonageWebhookController::class, 'deliveryReceipt']
    )->name('bird-flock.vonage.dlr');

    Route::post(
        '/webhooks/vonage/inbound',
        [VonageWebhookController::class, 'inbound']
    )->name('bird-flock.vonage.inbound');
});
