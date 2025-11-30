<?php

/**
 * Vonage webhook signature validator.
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
 * Validates Vonage webhook signatures.
 *
 * Vonage supports both signature-based and timestamp-based validation
 * for delivery receipt (DLR) webhooks.
 */
final class VonageSignatureValidator
{
    /**
     * Validates a Vonage webhook signature.
     *
     * Vonage uses HMAC-SHA256 with signature secret. The signature is computed
     * over the concatenated query parameters in alphabetical order.
     *
     * @param Request $request        HTTP request with webhook data
     * @param string  $signatureSecret Vonage signature secret
     *
     * @return bool True if signature is valid
     */
    public static function validate(Request $request, string $signatureSecret): bool
    {
        $providedSignature = $request->input('sig');

        if (!$providedSignature) {
            return false;
        }

        // Get all query parameters except 'sig'
        $params = $request->query();
        unset($params['sig']);

        // Sort parameters alphabetically by key
        ksort($params);

        // Concatenate key=value pairs with '&'
        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = $key . '=' . $value;
        }
        $concatenated = '&' . implode('&', $parts) . $signatureSecret;

        // Generate signature
        $computedSignature = hash('sha256', $concatenated);

        return hash_equals($computedSignature, $providedSignature);
    }

    /**
     * Validates Vonage timestamp to prevent replay attacks.
     *
     * @param Request $request     HTTP request
     * @param int     $maxAgeSeconds Maximum age in seconds (default 300 = 5 minutes)
     *
     * @return bool True if timestamp is within acceptable range
     */
    public static function validateTimestamp(Request $request, int $maxAgeSeconds = 300): bool
    {
        $timestamp = $request->input('message-timestamp');

        if (!$timestamp) {
            return false;
        }

        // Vonage sends timestamp in ISO 8601 format
        $messageTime = strtotime($timestamp);

        if ($messageTime === false) {
            return false;
        }

        $age = time() - $messageTime;

        return $age >= 0 && $age <= $maxAgeSeconds;
    }
}
