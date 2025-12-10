<?php

/**
 * Health check controller for monitoring package status.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Http\Controllers
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Equidna\BirdFlock\Services\HealthService;

/**
 * Handles health check requests for package monitoring.
 */
final class HealthCheckController extends Controller
{
    /**
     * Health service instance.
     */
    private HealthService $healthService;

    /**
     * Create a new controller instance.
     */
    public function __construct(HealthService $healthService)
    {
        $this->healthService = $healthService;
    }

    /**
     * Check package health status.
     *
     * Returns JSON with connectivity and configuration status for monitoring.
     *
     * @return JsonResponse Health status and diagnostics
     */
    public function check(): JsonResponse
    {
        $health = $this->healthService->getHealthStatus();
        $healthy = $health['status'] === 'healthy';

        return response()->json(
            data: $health,
            status: $healthy ? 200 : 503
        );
    }

    /**
     * Get detailed circuit breaker status for all providers.
     *
     * Returns comprehensive circuit breaker diagnostics including state,
     * failure counts, recovery timestamps, and configuration.
     *
     * @return JsonResponse Circuit breaker states and diagnostics
     */
    public function circuitBreakers(): JsonResponse
    {
        $circuitStatus = $this->healthService->getCircuitBreakerStatus();

        return response()->json($circuitStatus);
    }
}
