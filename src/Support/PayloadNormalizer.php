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
        $to = trim($to);
        $to = self::stripSurroundingQuotes($to);

        // If already has the prefix, normalize the rest and validate.
        $hasPrefix = str_starts_with($to, 'whatsapp:');

        $raw = $hasPrefix ? substr($to, 9) : $to;
        $cleaned = preg_replace('/[^0-9+]/', '', $raw);

        if ($cleaned === '' || $cleaned === '+') {
            throw new \InvalidArgumentException('Invalid WhatsApp recipient — empty after normalization');
        }

        // Ensure leading plus
        if (! str_starts_with($cleaned, '+')) {
            $cleaned = '+' . $cleaned;
        }

        // Validate digit length (E.164 allows up to 15 digits excluding '+').
        $digits = preg_replace('/\D/', '', $cleaned);
        $len = strlen($digits);
        if ($len < 6 || $len > 15) {
            throw new \InvalidArgumentException(sprintf('WhatsApp recipient has invalid length (%d digits)', $len));
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
        $phone = trim($phone);
        $phone = self::stripSurroundingQuotes($phone);

        $cleaned = preg_replace('/[^0-9+]/', '', $phone);

        if ($cleaned === '' || $cleaned === '+') {
            throw new \InvalidArgumentException('Invalid phone number — empty after normalization');
        }

        if (! str_starts_with($cleaned, '+')) {
            $cleaned = '+' . $cleaned;
        }

        $digits = preg_replace('/\D/', '', $cleaned);
        $len = strlen($digits);
        if ($len < 6 || $len > 15) {
            throw new \InvalidArgumentException(sprintf('Phone number has invalid length (%d digits)', $len));
        }

        return $cleaned;
    }

    private static function stripSurroundingQuotes(string $s): string
    {
        if (strlen($s) >= 2) {
            $first = $s[0];
            $last = $s[strlen($s) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($s, 1, -1);
            }
        }

        return $s;
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
