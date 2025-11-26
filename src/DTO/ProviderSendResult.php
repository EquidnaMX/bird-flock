<?php

/**
 * Data transfer object for provider send results.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\DTO
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\DTO;

/**
 * Encapsulates the result of a message send operation from a provider.
 */
final class ProviderSendResult
{
    /**
     * Create a new provider send result.
     *
     * @param string|null          $providerMessageId Provider's message identifier
     * @param string               $status            Status (sent|failed|undeliverable)
     * @param string|null          $errorCode         Error code if failed
     * @param string|null          $errorMessage      Error message if failed
     * @param array<string, mixed> $raw               Raw provider response
     */
    public function __construct(
        public readonly ?string $providerMessageId,
        public readonly string $status,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly array $raw = [],
    ) {
        //
    }

    /**
     * Create a successful result.
     *
     * @param string               $providerMessageId Provider's message identifier
     * @param array<string, mixed> $raw               Raw provider response
     *
     * @return self
     */
    public static function success(
        string $providerMessageId,
        array $raw = []
    ): self {
        return new self(
            providerMessageId: $providerMessageId,
            status: 'sent',
            raw: $raw,
        );
    }

    /**
     * Create a failed result.
     *
     * @param string|null          $errorCode    Error code
     * @param string|null          $errorMessage Error message
     * @param array<string, mixed> $raw          Raw provider response
     *
     * @return self
     */
    public static function failed(
        ?string $errorCode = null,
        ?string $errorMessage = null,
        array $raw = []
    ): self {
        return new self(
            providerMessageId: null,
            status: 'failed',
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            raw: $raw,
        );
    }

    /**
     * Create an undeliverable result.
     *
     * @param string|null          $errorCode    Error code
     * @param string|null          $errorMessage Error message
     * @param array<string, mixed> $raw          Raw provider response
     *
     * @return self
     */
    public static function undeliverable(
        ?string $errorCode = null,
        ?string $errorMessage = null,
        array $raw = []
    ): self {
        return new self(
            providerMessageId: null,
            status: 'undeliverable',
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            raw: $raw,
        );
    }
}
