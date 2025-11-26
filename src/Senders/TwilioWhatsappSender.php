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
use Equidna\BirdFlock\Support\PayloadNormalizer;
use Equidna\BirdFlock\Support\Logger;
use Exception;
use Throwable;

/**
 * Sends WhatsApp messages via Twilio.
 */
final class TwilioWhatsappSender implements MessageSenderInterface
{
    /**
     * Create a new Twilio WhatsApp sender.
     *
     * @param Client      $client         Twilio client instance
     * @param string      $from           From WhatsApp number (whatsapp:+...)
     * @param bool        $sandboxMode    Whether sandbox mode is enabled
     * @param string|null $statusCallback Optional status callback URL
     */
    public function __construct(
        private readonly Client $client,
        private readonly string $from,
        private readonly bool $sandboxMode = false,
        private readonly ?string $statusCallback = null,
    ) {
        //
    }

    /**
     * Send a WhatsApp message.
     *
     * @param FlightPlan $payload Message data
     *
     * @return ProviderSendResult Result of send operation
     */
    public function send(FlightPlan $payload): ProviderSendResult
    {
        try {
            if (!$this->sandboxMode && !$payload->templateKey) {
                return ProviderSendResult::undeliverable(
                    errorCode: 'TEMPLATE_REQUIRED',
                    errorMessage: 'Template key is required in production mode',
                );
            }

            $params = [
                'from' => $this->from,
            ];

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

            Logger::warning('bird-flock.sender.twilio_whatsapp.exception', [
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
            Logger::error('bird-flock.sender.twilio_whatsapp.unhandled', [
                'message' => $e->getMessage(),
            ]);

            return ProviderSendResult::failed(
                errorCode: 'UNKNOWN',
                errorMessage: $e->getMessage(),
            );
        }
    }
}
