<?php

use Illuminate\Support\Facades\Route;
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
});
