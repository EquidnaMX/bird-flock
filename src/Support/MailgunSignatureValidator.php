<?php

/**
 * Mailgun webhook signature validator.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Support;

use Illuminate\Http\Request;

/**
 * Validates Mailgun webhook signatures using HMAC-SHA256.
 *
 * Mailgun signs webhook requests with a signing key.
 */
final class MailgunSignatureValidator
{
    /**
     * Validates a Mailgun webhook signature.
     *
     * Mailgun uses HMAC-SHA256 computed over timestamp + token with the signing key.
     *
     * @param Request $request    HTTP request with webhook data
     * @param string  $signingKey Mailgun webhook signing key
     *
     * @return bool True if signature is valid
     */
    public static function validate(Request $request, string $signingKey): bool
    {
        $signature = $request->input('signature');

        if (!is_array($signature)) {
            return false;
        }

        $timestamp = $signature['timestamp'] ?? null;
        $token = $signature['token'] ?? null;
        $providedSignature = $signature['signature'] ?? null;

        if (!$timestamp || !$token || !$providedSignature) {
            return false;
        }

        // Compute HMAC-SHA256 over timestamp + token
        $data = $timestamp . $token;
        $computedSignature = hash_hmac('sha256', $data, $signingKey);

        return hash_equals($computedSignature, $providedSignature);
    }

    /**
     * Validates timestamp to prevent replay attacks.
     *
     * @param Request $request     HTTP request
     * @param int     $maxAgeSeconds Maximum age in seconds (default 300 = 5 minutes)
     *
     * @return bool True if timestamp is within acceptable range
     */
    public static function validateTimestamp(Request $request, int $maxAgeSeconds = 300): bool
    {
        $signature = $request->input('signature');

        if (!is_array($signature)) {
            return false;
        }

        $timestamp = $signature['timestamp'] ?? null;

        if (!$timestamp || !is_numeric($timestamp)) {
            return false;
        }

        $age = time() - (int) $timestamp;

        return $age >= 0 && $age <= $maxAgeSeconds;
    }
}
