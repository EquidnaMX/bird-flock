<?php

/**
 * Payload normalization utilities for message data.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Support;

/**
 * Normalizes and validates message payload data.
 */
final class PayloadNormalizer
{
    /**
     * Normalize WhatsApp recipient to E.164 format with prefix.
     *
     * @param string $to Raw phone number
     *
     * @return string Normalized WhatsApp format (whatsapp:+E164)
     */
    public static function normalizeWhatsAppRecipient(string $to): string
    {
        if (str_starts_with($to, 'whatsapp:')) {
            return $to;
        }

        $cleaned = preg_replace('/[^0-9+]/', '', $to);

        if (!str_starts_with($cleaned, '+')) {
            $cleaned = '+' . $cleaned;
        }

        return 'whatsapp:' . $cleaned;
    }

    /**
     * Normalize phone number to E.164 format.
     *
     * @param string $phone Raw phone number
     *
     * @return string Normalized phone in E.164 format
     */
    public static function normalizePhoneNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);

        if (!str_starts_with($cleaned, '+')) {
            $cleaned = '+' . $cleaned;
        }

        return $cleaned;
    }

    /**
     * Validate email address.
     *
     * @param string $email Email address
     *
     * @return bool True if valid
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
