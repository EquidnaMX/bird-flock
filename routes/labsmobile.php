<?php

use Illuminate\Support\Facades\Route;
use Equidna\BirdFlock\Http\Controllers\LabsmobileWebhookController;

Route::prefix('bird-flock')->middleware('throttle:60,1')->group(function () {
    Route::get(
        '/webhooks/labsmobile/ack',
        [LabsmobileWebhookController::class, 'ack']
    )->name('bird-flock.labsmobile.ack');
});
