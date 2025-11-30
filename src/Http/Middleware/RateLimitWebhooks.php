<?php

/**
 * Rate limiting middleware for webhook endpoints.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Http\Middleware
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Rate limits webhook requests to prevent abuse.
 *
 * Allows 60 requests per minute per IP address.
 */
final class RateLimitWebhooks
{
    /**
     * Create a new middleware instance.
     *
     * @param RateLimiter $limiter Rate limiter instance
     */
    public function __construct(
        private readonly RateLimiter $limiter,
    ) {
        //
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request $request HTTP request
     * @param  Closure $next    Next middleware
     * @return SymfonyResponse
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $key = $this->resolveRequestKey($request);
        $maxAttempts = config('bird-flock.webhook_rate_limit', 60);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);

            return new Response('Too Many Requests', 429, [
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        $this->limiter->hit($key, 60);

        $response = $next($request);

        return $this->addRateLimitHeaders($response, $key, $maxAttempts);
    }

    /**
     * Resolve the rate limiting key for the request.
     *
     * @param  Request $request HTTP request
     * @return string           Rate limit key
     */
    private function resolveRequestKey(Request $request): string
    {
        $ip = $request->ip();
        $path = $request->path();

        return sprintf('bird-flock:webhook:%s:%s', $path, $ip);
    }

    /**
     * Add rate limit headers to the response.
     *
     * @param  SymfonyResponse $response    HTTP response
     * @param  string          $key         Rate limit key
     * @param  int             $maxAttempts Maximum attempts
     * @return SymfonyResponse
     */
    private function addRateLimitHeaders(
        SymfonyResponse $response,
        string $key,
        int $maxAttempts
    ): SymfonyResponse {
        $remaining = $this->limiter->remaining($key, $maxAttempts);

        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', max(0, $remaining));

        if ($remaining === 0) {
            $response->headers->set(
                'Retry-After',
                $this->limiter->availableIn($key)
            );
        }

        return $response;
    }
}
