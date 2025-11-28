<?php

/**
 * Mailgun email sender implementation.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Senders
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Senders;

use Mailgun\Mailgun;
use Equidna\BirdFlock\Contracts\MessageSenderInterface;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\DTO\ProviderSendResult;
use Equidna\BirdFlock\Support\CircuitBreaker;
use Equidna\BirdFlock\Support\Logger;
use Equidna\BirdFlock\Support\Masking;
use Exception;
use Throwable;

/**
 * Sends email messages via Mailgun.
 */
final class MailgunEmailSender implements MessageSenderInterface
{
    private const MAX_ATTACHMENT_SIZE = 25165824; // 24MB (Mailgun limit)

    private readonly CircuitBreaker $circuitBreaker;

    /**
     * Create a new Mailgun email sender.
     *
     * @param Mailgun              $client    Mailgun client instance
     * @param string               $domain    Mailgun domain
     * @param string               $fromEmail From email address
     * @param string               $fromName  From name
     * @param string|null          $replyTo   Reply-to address
     * @param array<string,string> $templates Template key to name mapping
     */
    public function __construct(
        private readonly Mailgun $client,
        private readonly string $domain,
        private readonly string $fromEmail,
        private readonly string $fromName,
        private readonly ?string $replyTo = null,
        private readonly array $templates = [],
    ) {
        $this->circuitBreaker = new CircuitBreaker(
            service: 'mailgun_email',
            failureThreshold: config('bird-flock.circuit_breaker.failure_threshold', 5),
            timeout: config('bird-flock.circuit_breaker.timeout', 60),
            successThreshold: config('bird-flock.circuit_breaker.success_threshold', 2)
        );
    }

    /**
     * Send an email message.
     *
     * @param FlightPlan $payload Message data
     *
     * @return ProviderSendResult Result of send operation
     */
    public function send(FlightPlan $payload): ProviderSendResult
    {
        // Check circuit breaker before attempting send
        if (!$this->circuitBreaker->isAvailable()) {
            Logger::warning('bird-flock.sender.mailgun_email.circuit_open', [
                'to' => $payload->to,
            ]);

            return ProviderSendResult::failed(
                errorCode: 'CIRCUIT_OPEN',
                errorMessage: 'Mailgun service is temporarily unavailable due to repeated failures'
            );
        }

        try {
            $params = [
                'from' => sprintf('%s <%s>', $this->fromName, $this->fromEmail),
                'to' => $payload->to,
            ];

            if ($this->replyTo) {
                $params['h:Reply-To'] = $this->replyTo;
            }

            Logger::info('bird-flock.sender.mailgun.preparing', [
                'to' => Masking::maskEmail($payload->to),
                'template_key' => $payload->templateKey,
            ]);

            // Template-based sending
            if ($payload->templateKey && isset($this->templates[$payload->templateKey])) {
                $params['template'] = $this->templates[$payload->templateKey];

                if (!empty($payload->templateData)) {
                    foreach ($payload->templateData as $key => $value) {
                        $params["v:{$key}"] = $value;
                    }
                }

                // For template-based emails, subject is optional (template defines it)
                if ($payload->subject) {
                    $params['subject'] = $payload->subject;
                }
            } else {
                // Non-template sending
                if (!$payload->subject) {
                    return ProviderSendResult::undeliverable(
                        errorCode: 'MISSING_SUBJECT',
                        errorMessage: 'Subject is required for non-template emails',
                    );
                }

                $params['subject'] = $payload->subject;

                if ($payload->text) {
                    $params['text'] = $payload->text;
                }

                if ($payload->html) {
                    $params['html'] = $payload->html;
                }
            }

            // Handle attachments
            if (isset($payload->metadata['attachments'])) {
                $attachments = [];

                foreach ($payload->metadata['attachments'] as $attachment) {
                    if (isset($attachment['content'], $attachment['filename'])) {
                        $decoded = base64_decode($attachment['content'], true);

                        if ($decoded === false) {
                            return ProviderSendResult::undeliverable(
                                errorCode: 'ATTACHMENT_INVALID_BASE64',
                                errorMessage: 'Attachment content is not valid base64',
                            );
                        }

                        $size = strlen($decoded);

                        if ($size > self::MAX_ATTACHMENT_SIZE) {
                            return ProviderSendResult::undeliverable(
                                errorCode: 'ATTACHMENT_TOO_LARGE',
                                errorMessage: sprintf(
                                    'Attachment exceeds size limit of %d bytes',
                                    self::MAX_ATTACHMENT_SIZE
                                ),
                            );
                        }

                        $attachments[] = [
                            'fileContent' => $decoded,
                            'filename' => $attachment['filename'],
                        ];
                    }
                }

                if (!empty($attachments)) {
                    $params['attachment'] = $attachments;
                }
            }

            $response = $this->client->messages()->send($this->domain, $params);

            $messageId = $response->getId();

            Logger::info('bird-flock.sender.mailgun.success', [
                'provider_message_id' => $messageId,
                'to' => Masking::maskEmail($payload->to),
            ]);

            $result = ProviderSendResult::success(
                providerMessageId: $messageId,
                raw: [
                    'id' => $messageId,
                    'message' => $response->getMessage(),
                ],
            );

            $this->circuitBreaker->recordSuccess();

            return $result;
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            $httpCode = (int) $e->getCode();

            Logger::warning('bird-flock.sender.mailgun.exception', [
                'http_code' => $httpCode,
                'message' => strlen($errorMsg) > 500
                    ? substr($errorMsg, 0, 500) . '...'
                    : $errorMsg,
                'to' => Masking::maskEmail($payload->to),
            ]);

            $raw = [
                'http_code' => $httpCode,
                'error_message' => $errorMsg,
            ];

            // Classify errors: transient (retry) vs permanent (undeliverable)
            $transientCodes = [408, 425, 429, 503, 504];
            $isTransient = in_array($httpCode, $transientCodes, true) || $httpCode >= 500;

            if ($isTransient) {
                $this->circuitBreaker->recordFailure();

                return ProviderSendResult::failed(
                    errorCode: (string) $httpCode,
                    errorMessage: $errorMsg,
                    raw: $raw,
                );
            }

            // Permanent client errors
            return ProviderSendResult::undeliverable(
                errorCode: (string) $httpCode,
                errorMessage: $errorMsg,
                raw: $raw,
            );
        } catch (Throwable $e) {
            Logger::error('bird-flock.sender.mailgun.unhandled', [
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
