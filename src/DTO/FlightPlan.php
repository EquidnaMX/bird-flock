<?php

/**
 * Flight plan describing how a message should be delivered.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\DTO
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\DTO;

/**
 * Encapsulates message data for transmission across channels.
 */
final class FlightPlan
{
    /**
     * Create a new message payload.
     *
     * @param string               $channel         Channel type ('sms'|'whatsapp'|'email')
     * @param string               $to              Recipient address
     * @param string|null          $subject         Email subject
     * @param string|null          $text            Plain text content
     * @param string|null          $html            HTML content
     * @param string|null          $templateKey     Template identifier
     * @param array<string, mixed> $templateData    Template variables
     * @param array<string>        $mediaUrls       Media attachment URLs
     * @param array<string, mixed> $metadata        Additional metadata
     * @param string|null          $idempotencyKey  Unique key for idempotent operations
     * @param \DateTimeInterface|null $sendAt       Scheduled send time (null for immediate)
     */
    public function __construct(
        public readonly string $channel,
        public readonly string $to,
        public readonly ?string $subject = null,
        public readonly ?string $text = null,
        public readonly ?string $html = null,
        public readonly ?string $templateKey = null,
        public readonly array $templateData = [],
        public readonly array $mediaUrls = [],
        public readonly array $metadata = [],
        public readonly ?string $idempotencyKey = null,
        public readonly ?\DateTimeInterface $sendAt = null,
    ) {
        // Validate channel
        $validChannels = ['sms', 'whatsapp', 'email'];
        if (!in_array($channel, $validChannels, true)) {
            throw new \InvalidArgumentException(
                "Invalid channel '{$channel}'. Must be one of: " . implode(', ', $validChannels)
            );
        }

        // Validate recipient
        if (trim($to) === '') {
            throw new \InvalidArgumentException('Recipient (to) cannot be empty');
        }

        // Validate email format for email channel
        if ($channel === 'email' && !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(
                "Invalid email address '{$to}' for email channel"
            );
        }

        // Validate phone number length for sms/whatsapp
        if (in_array($channel, ['sms', 'whatsapp'], true)) {
            $cleaned = preg_replace('/[^0-9+]/', '', $to);
            if (strlen($cleaned) < 8 || strlen($cleaned) > 20) {
                throw new \InvalidArgumentException(
                    "Invalid phone number '{$to}' for {$channel} channel (must be 8-20 digits)"
                );
            }
        }

        // Validate idempotency key length
        if ($idempotencyKey !== null && strlen($idempotencyKey) > 128) {
            throw new \InvalidArgumentException(
                'Idempotency key cannot exceed 128 characters'
            );
        }
    }

    /**
     * Create from array data.
     *
     * @param array<string, mixed> $data Payload data
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $sendAt = null;
        if (isset($data['send_at'])) {
            $sendAt = is_string($data['send_at'])
                ? new \DateTimeImmutable($data['send_at'])
                : $data['send_at'];
        }

        return new self(
            channel: $data['channel'] ?? '',
            to: $data['to'] ?? '',
            subject: $data['subject'] ?? null,
            text: $data['text'] ?? null,
            html: $data['html'] ?? null,
            templateKey: $data['template_key'] ?? null,
            templateData: $data['template_data'] ?? [],
            mediaUrls: $data['media_urls'] ?? [],
            metadata: $data['metadata'] ?? [],
            idempotencyKey: $data['idempotency_key'] ?? null,
            sendAt: $sendAt,
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'channel'         => $this->channel,
            'to'              => $this->to,
            'subject'         => $this->subject,
            'text'            => $this->text,
            'html'            => $this->html,
            'template_key'    => $this->templateKey,
            'template_data'   => $this->templateData,
            'media_urls'      => $this->mediaUrls,
            'metadata'        => $this->metadata,
            'idempotency_key' => $this->idempotencyKey,
            'send_at'         => $this->sendAt?->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
