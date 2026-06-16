<?php

/**
 * LabsMobile SMS sender implementation.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Senders\Labsmobile
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Senders\Labsmobile;

use DateTimeImmutable;
use DateTimeZone;
use Equidna\BirdFlock\Contracts\MessageSenderInterface;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\DTO\ProviderSendResult;
use Equidna\BirdFlock\Support\CircuitBreaker;
use Equidna\BirdFlock\Support\Logger;
use Equidna\BirdFlock\Support\Masking;
use Equidna\BirdFlock\Support\PayloadNormalizer;
use GuzzleHttp\ClientInterface;
use Throwable;

/**
 * Sends SMS messages via LabsMobile HTTP/POST API.
 */
final class LabsmobileSmsSender implements MessageSenderInterface
{
    private readonly CircuitBreaker $circuitBreaker;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly string $endpoint,
        private readonly ?string $from = null,
        private readonly ?string $ackUrl = null,
        private readonly bool $test = false,
        private readonly bool $long = false,
        private readonly bool $ucs2 = false,
        private readonly bool $shortlink = false,
    ) {
        $this->circuitBreaker = new CircuitBreaker(
            service: 'labsmobile_sms',
            failureThreshold: config('bird-flock.circuit_breaker.failure_threshold', 5),
            timeout: config('bird-flock.circuit_breaker.timeout', 60),
            successThreshold: config('bird-flock.circuit_breaker.success_threshold', 2)
        );
    }

    public function send(FlightPlan $payload): ProviderSendResult
    {
        if (!$this->circuitBreaker->isAvailable()) {
            Logger::warning('bird-flock.sender.labsmobile_sms.circuit_open', [
                'to' => Masking::maskPhone($payload->to),
            ]);

            return ProviderSendResult::failed(
                errorCode: 'CIRCUIT_OPEN',
                errorMessage: 'LabsMobile SMS service is temporarily unavailable due to repeated failures'
            );
        }

        try {
            $requestPayload = $this->buildRequestPayload($payload);
            $response = $this->client->request('POST', $this->endpoint, [
                'json' => $requestPayload,
                'http_errors' => false,
            ]);

            $httpStatus = $response->getStatusCode();
            $body = (string) $response->getBody();
            $json = json_decode($body, true);

            if (!is_array($json)) {
                $this->circuitBreaker->recordFailure();

                Logger::warning('bird-flock.sender.labsmobile_sms.invalid_response', [
                    'http_code' => $httpStatus,
                    'body' => substr($body, 0, 500),
                ]);

                return ProviderSendResult::failed(
                    errorCode: 'INVALID_RESPONSE',
                    errorMessage: 'LabsMobile returned a non-JSON response',
                    raw: [
                        'http_code' => $httpStatus,
                        'body' => $body,
                    ],
                );
            }

            $providerCode = (string) ($json['code'] ?? '');
            $providerMessage = (string) ($json['message'] ?? '');
            $subid = isset($json['subid']) ? (string) $json['subid'] : '';

            $raw = [
                'http_code' => $httpStatus,
                'code' => $providerCode,
                'message' => $providerMessage,
                'subid' => $subid,
            ];

            if ($httpStatus >= 200 && $httpStatus < 300 && (int) $providerCode === 0) {
                $messageId = $subid !== '' ? $subid : (string) ($payload->idempotencyKey ?? uniqid('labsmobile_', true));

                Logger::info('bird-flock.sender.labsmobile_sms.success', [
                    'provider_message_id' => $messageId,
                    'to' => Masking::maskPhone($payload->to),
                    'from' => $this->from,
                ]);

                $this->circuitBreaker->recordSuccess();

                return ProviderSendResult::success(
                    providerMessageId: $messageId,
                    raw: $raw,
                );
            }

            Logger::warning('bird-flock.sender.labsmobile_sms.failed', [
                'http_code' => $httpStatus,
                'provider_error_code' => $providerCode,
                'provider_error_message' => $providerMessage,
                'to' => Masking::maskPhone($payload->to),
            ]);

            if ($this->isTransient($httpStatus, $providerCode)) {
                $this->circuitBreaker->recordFailure();

                return ProviderSendResult::failed(
                    errorCode: $providerCode !== '' ? $providerCode : (string) $httpStatus,
                    errorMessage: $providerMessage !== '' ? $providerMessage : 'LabsMobile temporary error',
                    raw: $raw,
                );
            }

            return ProviderSendResult::undeliverable(
                errorCode: $providerCode !== '' ? $providerCode : (string) $httpStatus,
                errorMessage: $providerMessage !== '' ? $providerMessage : 'LabsMobile request rejected',
                raw: $raw,
            );
        } catch (Throwable $e) {
            $this->circuitBreaker->recordFailure();

            Logger::error('bird-flock.sender.labsmobile_sms.unhandled', [
                'message' => $e->getMessage(),
            ]);

            return ProviderSendResult::failed(
                errorCode: 'LABSMOBILE_ERROR',
                errorMessage: $e->getMessage(),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequestPayload(FlightPlan $payload): array
    {
        $to = ltrim(PayloadNormalizer::normalizePhoneNumber($payload->to), '+');

        $requestPayload = [
            'message' => $payload->text ?? '',
            'recipient' => [
                ['msisdn' => $to],
            ],
        ];

        if ($this->from !== null && trim($this->from) !== '') {
            $requestPayload['tpoa'] = $this->from;
        }

        if ($this->ackUrl !== null && trim($this->ackUrl) !== '') {
            $requestPayload['ackurl'] = $this->ackUrl;
        }

        if ($payload->sendAt !== null) {
            $requestPayload['scheduled'] = (new DateTimeImmutable('@' . $payload->sendAt->getTimestamp()))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        }

        foreach (['test', 'long', 'ucs2', 'shortlink'] as $flag) {
            if ($this->{$flag}) {
                $requestPayload[$flag] = 1;
            }
        }

        return $requestPayload;
    }

    private function isTransient(int $httpStatus, string $providerCode): bool
    {
        if (in_array($httpStatus, [408, 425, 429], true) || $httpStatus >= 500) {
            return true;
        }

        return $providerCode === '30';
    }
}
