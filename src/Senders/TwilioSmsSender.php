<?php

/**
 * Twilio SMS sender implementation.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Senders
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Senders;

use Twilio\Rest\Client;
use Equidna\BirdFlock\Contracts\MessageSenderInterface;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\DTO\ProviderSendResult;
use Equidna\BirdFlock\Support\CircuitBreaker;
use Equidna\BirdFlock\Support\Logger;
use Equidna\BirdFlock\Support\Masking;
use Equidna\BirdFlock\Support\PayloadNormalizer;
use Exception;
use Throwable;

/**
 * Sends SMS messages via Twilio.
 */
final class TwilioSmsSender implements MessageSenderInterface
{
    private readonly CircuitBreaker $circuitBreaker;

    /**
     * Create a new Twilio SMS sender.
     *
     * @param Client $client Twilio client instance
     * @param string $from   From phone number
     * @param string|null $messagingServiceSid Optional messaging service SID
     * @param string|null $statusCallback Optional status callback URL
     */
    public function __construct(
        private readonly Client $client,
        private readonly string $from,
        private readonly ?string $messagingServiceSid = null,
        private readonly ?string $statusCallback = null,
    ) {
        // configuration-driven sandbox behavior; do not accept sandbox flags here
        $this->circuitBreaker = new CircuitBreaker(
            service: 'twilio_sms',
            failureThreshold: config('bird-flock.circuit_breaker.failure_threshold', 5),
            timeout: config('bird-flock.circuit_breaker.timeout', 60),
            successThreshold: config('bird-flock.circuit_breaker.success_threshold', 2)
        );
    }

    /**
     * Send an SMS message.
     *
     * @param  FlightPlan $payload Message data
     * @return ProviderSendResult  Result of send operation
     * @throws \Throwable          When an unexpected error occurs during send
     */
    public function send(FlightPlan $payload): ProviderSendResult
    {
        // Check circuit breaker before attempting send
        if (!$this->circuitBreaker->isAvailable()) {
            Logger::warning('bird-flock.sender.twilio_sms.circuit_open', [
                'to' => $payload->to,
            ]);

            return ProviderSendResult::failed(
                errorCode: 'CIRCUIT_OPEN',
                errorMessage: 'Twilio SMS service is temporarily unavailable due to repeated failures'
            );
        }

        try {
            $params = [
                'body' => $payload->text ?? '',
            ];

            if ($this->messagingServiceSid) {
                $params['messagingServiceSid'] = $this->messagingServiceSid;
            } else {
                // If sandbox mode is enabled and no explicit sandboxFrom was
                // provided, infer it from the configured `from` value. This keeps
                // configuration minimal while allowing an override via
                // `TWILIO_SANDBOX_FROM`.
                if (config('bird-flock.twilio.sandbox_mode', false)) {
                    $configuredSandbox = config('bird-flock.twilio.sandbox_from');
                    $effectiveSandboxFrom = $configuredSandbox ?: $this->from;
                    Logger::warning('bird-flock.sender.twilio_sms.sandbox_from_used', [
                        'sandbox_from' => $effectiveSandboxFrom,
                        'inferred' => $configuredSandbox ? false : true,
                    ]);
                    $params['from'] = $effectiveSandboxFrom;
                } else {
                    $params['from'] = $this->from;
                }
            }

            if ($this->statusCallback) {
                $params['statusCallback'] = $this->statusCallback;
            }

            $to = PayloadNormalizer::normalizePhoneNumber($payload->to);

            $message = $this->client->messages->create(
                $to,
                $params
            );

            Logger::info('bird-flock.sender.twilio_sms.success', [
                'provider_message_id' => $message->sid,
                'to' => Masking::maskPhone($message->to ?? ''),
                'from' => Masking::maskPhone($message->from ?? ''),
            ]);

            $result = ProviderSendResult::success(
                providerMessageId: $message->sid,
                raw: [
                    'status' => $message->status,
                    'to' => $message->to,
                    'from' => $message->from,
                ],
            );

            // Record success with circuit breaker
            $this->circuitBreaker->recordSuccess();

            return $result;
        } catch (Exception $e) {
            // Use getCode() and message as a safe fallback; attempt to parse JSON
            // encoded provider details if present in the exception message.
            $statusCode = (int) $e->getCode();
            $providerErrorCode = null;
            $providerErrorMessage = $e->getMessage();

            $maybe = json_decode($e->getMessage(), true);
            if (is_array($maybe)) {
                $providerErrorCode = (string) ($maybe['code'] ?? $maybe['error_code'] ?? '');
                $providerErrorMessage = $maybe['message'] ?? $providerErrorMessage;
            }

            Logger::warning('bird-flock.sender.twilio_sms.exception', [
                'http_code' => $statusCode,
                'provider_error_code' => $providerErrorCode,
                'message' => strlen($providerErrorMessage) > 500
                    ? substr($providerErrorMessage, 0, 500) . '...'
                    : $providerErrorMessage,
                'to' => Masking::maskPhone($payload->to),
            ]);

            $errorCodeForResult = $providerErrorCode ?: (string) $statusCode;

            $raw = [
                'provider_error_code' => $providerErrorCode,
                'provider_error_message' => $providerErrorMessage,
                'http_code' => $statusCode,
            ];

            // Classify errors: transient (retry) vs permanent (undeliverable)
            $transientCodes = [408, 425, 429, 503, 504];
            $isTransient = in_array($statusCode, $transientCodes, true) || $statusCode >= 500;

            if ($isTransient) {
                // Record failure for transient errors (rate limits, timeouts, server errors)
                $this->circuitBreaker->recordFailure();

                return ProviderSendResult::failed(
                    errorCode: $errorCodeForResult,
                    errorMessage: $providerErrorMessage,
                    raw: $raw,
                );
            }

            // Permanent client errors (400-404, 422, etc.) don't trigger circuit breaker
            return ProviderSendResult::undeliverable(
                errorCode: $errorCodeForResult,
                errorMessage: $providerErrorMessage,
                raw: $raw,
            );
        } catch (Throwable $e) {
            // Record failure for unexpected errors
            $this->circuitBreaker->recordFailure();

            Logger::error('bird-flock.sender.twilio_sms.unhandled', [
                'message' => $e->getMessage(),
            ]);

            return ProviderSendResult::failed(
                errorCode: 'UNKNOWN',
                errorMessage: $e->getMessage(),
            );
        }
    }
}
