<?php

/**
 * SendGrid webhook signature validator using Ed25519.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Support;

use Illuminate\Http\Request;
use SendGrid\EventWebhook\EventWebhook;
use SendGrid\EventWebhook\EventWebhookHeader;
use Throwable;

/**
 * Validates SendGrid webhook signatures using the official helper.
 */
final class SendgridSignatureValidator
{
    /**
     * Validate the request signature using SendGrid's public key.
     *
     * @param Request $request   Incoming HTTP request
     * @param string  $publicKey Ed25519 public key supplied by SendGrid
     *
     * @return bool
     */
    public static function validate(Request $request, string $publicKey): bool
    {
        $signature = $request->header(EventWebhookHeader::SIGNATURE);
        $timestamp = $request->header(EventWebhookHeader::TIMESTAMP);

        if (!$signature || !$timestamp || empty($publicKey)) {
            Logger::warning('bird-flock.webhook.sendgrid.signature_missing', [
                'has_signature' => !empty($signature),
                'has_timestamp' => !empty($timestamp),
                'has_public_key' => !empty($publicKey),
            ]);
            return false;
        }

        $eventWebhook = new EventWebhook();

        try {
            $isValid = $eventWebhook->verifySignature(
                $request->getContent(),
                $signature,
                $timestamp,
                $publicKey
            );

            if (!$isValid) {
                Logger::warning('bird-flock.webhook.sendgrid.signature_invalid', [
                    'timestamp' => $timestamp,
                    'ip' => $request->ip(),
                ]);
            }

            return $isValid;
        } catch (Throwable $e) {
            Logger::warning('bird-flock.webhook.sendgrid.signature_error', [
                'message' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);
            return false;
        }
    }
}
