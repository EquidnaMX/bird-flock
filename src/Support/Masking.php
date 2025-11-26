<?php

/**
 * Masking utility for sensitive data in logs.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Support;

/**
 * Provides methods to mask sensitive information.
 */
final class Masking
{
    /**
     * Mask email address.
     *
     * @param string $email Email to mask
     *
     * @return string Masked email
     */
    public static function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return '***';
        }

        [$local, $domain] = explode('@', $email, 2);
        $localLen = strlen($local);

        if ($localLen <= 2) {
            return str_repeat('*', $localLen) . '@' . $domain;
        }

        return $local[0] . str_repeat('*', $localLen - 2) . $local[$localLen - 1] . '@' . $domain;
    }

    /**
     * Mask phone number.
     *
     * @param string $phone Phone to mask
     *
     * @return string Masked phone
     */
    public static function maskPhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);
        $len = strlen($cleaned);

        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return substr($cleaned, 0, 2) . str_repeat('*', $len - 4) . substr($cleaned, -2);
    }

    /**
     * Mask API key or token.
     *
     * @param string $key Key to mask
     *
     * @return string Masked key
     */
    public static function maskApiKey(string $key): string
    {
        $len = strlen($key);

        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return substr($key, 0, 4) . str_repeat('*', $len - 8) . substr($key, -4);
    }
}
