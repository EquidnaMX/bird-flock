<?php

/**
 * Twilio WhatsApp sender implementation.
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
 * Sends WhatsApp messages via Twilio.
 */
final class TwilioWhatsappSender implements MessageSenderInterface
{
    private readonly CircuitBreaker $circuitBreaker;

    /**
     * Create a new Twilio WhatsApp sender.
     *
     * @param Client      $client         Twilio client instance
     * @param string      $from           From WhatsApp number (whatsapp:+...)
     * @param string|null $statusCallback Optional status callback URL
     */
    public function __construct(
        private readonly Client $client,
        private readonly string $from,
        private readonly ?string $statusCallback = null,
    ) {
        // configuration-driven sandbox behavior; do not accept sandbox flags here
        $this->circuitBreaker = new CircuitBreaker(
            service: 'twilio_whatsapp',
            failureThreshold: config('bird-flock.circuit_breaker.failure_threshold', 5),
            timeout: config('bird-flock.circuit_breaker.timeout', 60),
            successThreshold: config('bird-flock.circuit_breaker.success_threshold', 2)
        );
    }

    /**
     * Send a WhatsApp message.
     *
     * @param  FlightPlan $payload Message data
     * @return ProviderSendResult  Result of send operation
     * @throws \Throwable          When an unexpected error occurs during send
     */
    public function send(FlightPlan $payload): ProviderSendResult
    {
        // Check circuit breaker before attempting send
        if (!$this->circuitBreaker->isAvailable()) {
            Logger::warning('bird-flock.sender.twilio_whatsapp.circuit_open', [
                'to' => $payload->to,
            ]);

            return ProviderSendResult::failed(
                errorCode: 'CIRCUIT_OPEN',
                errorMessage: 'Twilio WhatsApp service is temporarily unavailable due to repeated failures'
            );
        }

        try {
            if (! config('bird-flock.twilio.sandbox_mode', false) && ! $payload->templateKey) {
                return ProviderSendResult::undeliverable(
                    errorCode: 'TEMPLATE_REQUIRED',
                    errorMessage: 'Template key is required in production mode',
                );
            }

            $params = [
                'from' => $this->from,
            ];

            if (config('bird-flock.twilio.sandbox_mode', false)) {
                $configuredSandbox = config('bird-flock.twilio.sandbox_from');
                $effectiveSandboxFrom = $configuredSandbox ?: $this->from;

                if (! str_starts_with((string) $effectiveSandboxFrom, 'whatsapp:')) {
                    $effectiveSandboxFrom = 'whatsapp:' . $effectiveSandboxFrom;
                }

                Logger::warning('bird-flock.sender.twilio_whatsapp.sandbox_from_used', [
                    'sandbox_from' => $effectiveSandboxFrom,
                    'inferred' => $configuredSandbox ? false : true,
                ]);

                $params['from'] = $effectiveSandboxFrom;
            }

            if ($payload->text) {
                $params['body'] = $payload->text;
            }

            if ($this->statusCallback) {
                $params['statusCallback'] = $this->statusCallback;
            }

            if (!empty($payload->mediaUrls)) {
                $params['mediaUrl'] = $payload->mediaUrls;
            }

            $to = PayloadNormalizer::normalizeWhatsAppRecipient($payload->to);

            $message = $this->client->messages->create(
                $to,
                $params
            );

            Logger::info('bird-flock.sender.twilio_whatsapp.success', [
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

            $this->circuitBreaker->recordSuccess();

            return $result;
        } catch (Exception $e) {
            // Attempt to extract provider error code from JSON-encoded message,
            // fall back to exception code.
            $statusCode = (int) $e->getCode();
            $providerErrorCode = null;
            $providerErrorMessage = $e->getMessage();

            $maybe = json_decode($e->getMessage(), true);
            if (is_array($maybe)) {
                $providerErrorCode = (string) ($maybe['code'] ?? $maybe['error_code'] ?? '');
                $providerErrorMessage = $maybe['message'] ?? $providerErrorMessage;
            }

            Logger::warning('bird-flock.sender.twilio_whatsapp.exception', [
                'http_code' => $statusCode,
                'provider_error_code' => $providerErrorCode,
                'message' => strlen($providerErrorMessage) > 500
                    ? substr($providerErrorMessage, 0, 500) . '...'
                    : $providerErrorMessage,
                'to' => Masking::maskPhone($payload->to),
            ]);

            $raw = [
                'provider_error_code' => $providerErrorCode,
                'provider_error_message' => $providerErrorMessage,
                'http_code' => $statusCode,
            ];

            $errorCodeForResult = $providerErrorCode ?: (string) $statusCode;

            // Classify errors: transient (retry) vs permanent (undeliverable)
            $transientCodes = [408, 425, 429, 503, 504];
            $isTransient = in_array($statusCode, $transientCodes, true) || $statusCode >= 500;

            if ($isTransient) {
                // Record circuit breaker failure for transient errors
                $this->circuitBreaker->recordFailure();

                return ProviderSendResult::failed(
                    errorCode: $errorCodeForResult,
                    errorMessage: $providerErrorMessage,
                    raw: $raw,
                );
            }

            // Permanent client errors don't trigger circuit breaker
            return ProviderSendResult::undeliverable(
                errorCode: $errorCodeForResult,
                errorMessage: $providerErrorMessage,
                raw: $raw,
            );
        } catch (Throwable $e) {
            Logger::error('bird-flock.sender.twilio_whatsapp.unhandled', [
                'message' => $e->getMessage(),
            ]);

            // Record failure for unexpected errors
            $this->circuitBreaker->recordFailure();

            return ProviderSendResult::failed(
                errorCode: 'UNKNOWN',
                errorMessage: $e->getMessage(),
            );
        }
    }
}
