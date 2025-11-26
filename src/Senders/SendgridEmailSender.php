<?php

/**
 * SendGrid email sender implementation.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Senders
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Senders;

use SendGrid;
use SendGrid\Mail\Mail;
use Equidna\BirdFlock\Contracts\MessageSenderInterface;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\DTO\ProviderSendResult;
use Equidna\BirdFlock\Support\Logger;
use Exception;
use Throwable;

/**
 * Sends email messages via SendGrid.
 */
final class SendgridEmailSender implements MessageSenderInterface
{
    private const MAX_ATTACHMENT_SIZE = 10485760;

    /**
     * Create a new SendGrid email sender.
     *
     * @param SendGrid             $client    SendGrid client instance
     * @param string               $fromEmail From email address
     * @param string               $fromName  From name
     * @param string|null          $replyTo   Reply-to address
     * @param array<string,string> $templates Template key to ID mapping
     */
    public function __construct(
        private readonly SendGrid $client,
        private readonly string $fromEmail,
        private readonly string $fromName,
        private readonly ?string $replyTo = null,
        private readonly array $templates = [],
    ) {
        //
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
        try {
            $mail = new Mail();
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addTo($payload->to);

            if ($this->replyTo) {
                $mail->setReplyTo($this->replyTo);
            }

            if ($payload->templateKey && isset($this->templates[$payload->templateKey])) {
                $mail->setTemplateId($this->templates[$payload->templateKey]);

                if (!empty($payload->templateData)) {
                    $mail->addDynamicTemplateDatas($payload->templateData);
                }
            } else {
                if ($payload->subject) {
                    $mail->setSubject($payload->subject);
                }

                if ($payload->text) {
                    $mail->addContent('text/plain', $payload->text);
                }

                if ($payload->html) {
                    $mail->addContent('text/html', $payload->html);
                }
            }

            if (isset($payload->metadata['attachments'])) {
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
                                errorMessage: 'Attachment exceeds size limit',
                            );
                        }

                        $mail->addAttachment(
                            $attachment['content'],
                            $attachment['type'] ?? 'application/octet-stream',
                            $attachment['filename'],
                        );
                    }
                }
            }

            $response = $this->client->send($mail);
            $statusCode = $response->statusCode();

            Logger::info('bird-flock.sender.sendgrid.response', [
                'status_code' => $statusCode,
            ]);

            if ($statusCode >= 200 && $statusCode < 300) {
                $messageId = $response->headers()['X-Message-Id'] ?? null;

                return ProviderSendResult::success(
                    providerMessageId: $messageId ?? 'unknown',
                    raw: [
                        'status_code' => $statusCode,
                        'body' => $response->body(),
                    ],
                );
            }

            if ($statusCode === 429 || $statusCode >= 500) {
                return ProviderSendResult::failed(
                    errorCode: (string) $statusCode,
                    errorMessage: $response->body(),
                );
            }

            return ProviderSendResult::undeliverable(
                errorCode: (string) $statusCode,
                errorMessage: $response->body(),
            );
        } catch (Exception $e) {
            Logger::warning('bird-flock.sender.sendgrid.exception', [
                'message' => $e->getMessage(),
            ]);

            return ProviderSendResult::failed(
                errorCode: 'SENDGRID_ERROR',
                errorMessage: $e->getMessage(),
            );
        } catch (Throwable $e) {
            Logger::error('bird-flock.sender.sendgrid.unhandled', [
                'message' => $e->getMessage(),
            ]);

            return ProviderSendResult::failed(
                errorCode: 'UNKNOWN',
                errorMessage: $e->getMessage(),
            );
        }
    }
}
