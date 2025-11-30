<?php

/**
 * Vonage SMS sender implementation.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Senders
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Senders;

use Vonage\Client as VonageClient;
use Vonage\SMS\Message\SMS;
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
 * Sends SMS messages via Vonage (formerly Nexmo).
 */
final class VonageSmsSender implements MessageSenderInterface
{
    private readonly CircuitBreaker $circuitBreaker;

    /**
     * Create a new Vonage SMS sender.
     *
     * @param VonageClient $client Vonage client instance
     * @param string       $from   From phone number or alphanumeric sender ID
     */
    public function __construct(
        private readonly VonageClient $client,
        private readonly string $from,
    ) {
        $this->circuitBreaker = new CircuitBreaker(
            service: 'vonage_sms',
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
            Logger::warning('bird-flock.sender.vonage_sms.circuit_open', [
                'to' => $payload->to,
            ]);

            return ProviderSendResult::failed(
                errorCode: 'CIRCUIT_OPEN',
                errorMessage: 'Vonage SMS service is temporarily unavailable due to repeated failures'
            );
        }

        try {
            $to = PayloadNormalizer::normalizePhoneNumber($payload->to);
            $text = $payload->text ?? '';

            $message = new SMS($to, $this->from, $text);

            $response = $this->client->sms()->send($message);
            $current = $response->current();

            if ($current->getStatus() === 0) {
                $messageId = $current->getMessageId();

                Logger::info('bird-flock.sender.vonage_sms.success', [
                    'provider_message_id' => $messageId,
                    'to' => Masking::maskPhone($to),
                    'from' => Masking::maskPhone($this->from),
                    'remaining_balance' => $current->getRemainingBalance(),
                    'message_price' => $current->getMessagePrice(),
                ]);

                $result = ProviderSendResult::success(
                    providerMessageId: $messageId,
                    raw: [
                        'status' => $current->getStatus(),
                        'to' => $to,
                        'from' => $this->from,
                        'network' => $current->getNetwork(),
                        'message_price' => $current->getMessagePrice(),
                        'remaining_balance' => $current->getRemainingBalance(),
                    ],
                );

                $this->circuitBreaker->recordSuccess();

                return $result;
            }

            // Non-zero status indicates failure
            $statusCode = $current->getStatus();
            // Map Vonage status codes to error messages
            $errorMessages = [
                1 => 'Throttled',
                2 => 'Missing params',
                3 => 'Invalid params',
                4 => 'Invalid credentials',
                5 => 'Internal error',
                6 => 'Invalid message',
                7 => 'Number barred',
                8 => 'Partner account barred',
                9 => 'Partner quota exceeded',
            ];
            $errorMessage = $errorMessages[$statusCode] ?? 'Unknown error';

            Logger::warning('bird-flock.sender.vonage_sms.failed', [
                'status_code' => $statusCode,
                'error_text' => $errorMessage,
                'to' => Masking::maskPhone($to),
            ]);

            $raw = [
                'status' => $statusCode,
                'error_text' => $errorMessage,
                'to' => $to,
                'from' => $this->from,
            ];

            // Classify errors based on Vonage status codes
            // Status codes: https://developer.vonage.com/en/api-errors/sms
            // Transient: 1 (throttled), 5 (internal error), 9 (partner quota exceeded)
            // Permanent: 3 (missing params), 4 (invalid signature), 6 (invalid message),
            //            7 (invalid number), 8 (barred destination), etc.
            $transientCodes = [1, 5, 9];
            $isTransient = in_array($statusCode, $transientCodes, true);

            if ($isTransient) {
                $this->circuitBreaker->recordFailure();

                return ProviderSendResult::failed(
                    errorCode: (string) $statusCode,
                    errorMessage: $errorMessage,
                    raw: $raw,
                );
            }

            // Permanent errors
            return ProviderSendResult::undeliverable(
                errorCode: (string) $statusCode,
                errorMessage: $errorMessage,
                raw: $raw,
            );
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();

            Logger::warning('bird-flock.sender.vonage_sms.exception', [
                'message' => strlen($errorMsg) > 500
                    ? substr($errorMsg, 0, 500) . '...'
                    : $errorMsg,
                'to' => Masking::maskPhone($payload->to),
            ]);

            $this->circuitBreaker->recordFailure();

            return ProviderSendResult::failed(
                errorCode: 'VONAGE_ERROR',
                errorMessage: $e->getMessage(),
            );
        } catch (Throwable $e) {
            Logger::error('bird-flock.sender.vonage_sms.unhandled', [
                'message' => $e->getMessage(),
            ]);

            $this->circuitBreaker->recordFailure();

            return ProviderSendResult::failed(
                errorCode: 'UNKNOWN',
                errorMessage: $e->getMessage(),
            );
        }
    }
}
