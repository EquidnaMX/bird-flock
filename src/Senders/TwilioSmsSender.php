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
use Equidna\BirdFlock\Support\PayloadNormalizer;
use Exception;
use Throwable;
use Equidna\BirdFlock\Support\Logger;

/**
 * Sends SMS messages via Twilio.
 */
final class TwilioSmsSender implements MessageSenderInterface
{
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
        //
    }

    /**
     * Send an SMS message.
     *
     * @param FlightPlan $payload Message data
     *
     * @return ProviderSendResult Result of send operation
     */
    public function send(FlightPlan $payload): ProviderSendResult
    {
        try {
            $params = [
                'body' => $payload->text ?? '',
            ];

            if ($this->messagingServiceSid) {
                $params['messagingServiceSid'] = $this->messagingServiceSid;
            } else {
                $params['from'] = $this->from;
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
                'to' => $message->to,
                'from' => $message->from,
            ]);

            return ProviderSendResult::success(
                providerMessageId: $message->sid,
                raw: [
                    'status' => $message->status,
                    'to' => $message->to,
                    'from' => $message->from,
                ],
            );
        } catch (Exception $e) {
            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 0;

            Logger::warning('bird-flock.sender.twilio_sms.exception', [
                'code' => $statusCode,
                'message' => $e->getMessage(),
            ]);

            if ($statusCode === 429 || $statusCode >= 500) {
                return ProviderSendResult::failed(
                    errorCode: (string) $statusCode,
                    errorMessage: $e->getMessage(),
                );
            }

            return ProviderSendResult::undeliverable(
                errorCode: (string) $statusCode,
                errorMessage: $e->getMessage(),
            );
        } catch (Throwable $e) {
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
