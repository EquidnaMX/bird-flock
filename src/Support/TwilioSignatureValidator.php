<?php

/**
 * Twilio webhook signature validator.
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
 * Validates Twilio webhook signatures for security.
 */
final class TwilioSignatureValidator
{
    /**
     * Validate Twilio request signature.
     *
     * @param Request $request   HTTP request
     * @param string  $authToken Twilio auth token
     * @param string  $url       Full webhook URL
     *
     * @return bool True if signature is valid
     */
    public static function validate(
        Request $request,
        string $authToken,
        string $url
    ): bool {
        $signature = $request->header('X-Twilio-Signature');

        if (!$signature) {
            Logger::warning('bird-flock.webhook.twilio.signature_missing', [
                'url' => $url,
                'ip' => $request->ip(),
            ]);
            return false;
        }

        $data = $url;

        $params = $request->all();
        ksort($params);

        foreach ($params as $key => $value) {
            $data .= $key . $value;
        }

        $computed = base64_encode(hash_hmac('sha1', $data, $authToken, true));

        $isValid = hash_equals($computed, $signature);

        if (!$isValid) {
            Logger::warning('bird-flock.webhook.twilio.signature_invalid', [
                'url' => $url,
                'ip' => $request->ip(),
            ]);
        }

        return $isValid;
    }
}
