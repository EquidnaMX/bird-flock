<?php

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
            return false;
        }

        $eventWebhook = new EventWebhook();

        try {
            return $eventWebhook->verifySignature(
                $request->getContent(),
                $signature,
                $timestamp,
                $publicKey
            );
        } catch (Throwable) {
            return false;
        }
    }
}
