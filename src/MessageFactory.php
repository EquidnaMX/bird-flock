<?php

/**
 * Factory for creating message senders and clients.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock;

use SendGrid;
use Twilio\Rest\Client as TwilioClient;
use Equidna\BirdFlock\Contracts\MessageSenderInterface;
use Equidna\BirdFlock\Senders\SendgridEmailSender;
use Equidna\BirdFlock\Senders\TwilioSmsSender;
use Equidna\BirdFlock\Senders\TwilioWhatsappSender;

/**
 * Creates sender instances based on configuration.
 */
final class MessageFactory
{
    /**
     * Create a sender for the specified channel.
     *
     * @param string $channel Channel name (sms|whatsapp|email)
     *
     * @return MessageSenderInterface Sender instance
     *
     * @throws \InvalidArgumentException If channel is not supported
     */
    public static function createSender(string $channel): MessageSenderInterface
    {
        return match ($channel) {
            'sms' => self::createTwilioSmsSender(),
            'whatsapp' => self::createTwilioWhatsappSender(),
            'email' => self::createSendgridEmailSender(),
            default => throw new \InvalidArgumentException("Unsupported channel: {$channel}"),
        };
    }

    /**
     * Create Twilio SMS sender.
     *
     * @return TwilioSmsSender
     */
    private static function createTwilioSmsSender(): TwilioSmsSender
    {
        /** @var TwilioClient $client */
        $client = app(TwilioClient::class);

        return new TwilioSmsSender(
            client: $client,
            from: config('bird-flock.twilio.from_sms'),
            messagingServiceSid: config('bird-flock.twilio.messaging_service_sid'),
            statusCallback: config('bird-flock.twilio.status_webhook_url'),
        );
    }

    /**
     * Create Twilio WhatsApp sender.
     *
     * @return TwilioWhatsappSender
     */
    private static function createTwilioWhatsappSender(): TwilioWhatsappSender
    {
        /** @var TwilioClient $client */
        $client = app(TwilioClient::class);

        return new TwilioWhatsappSender(
            client: $client,
            from: config('bird-flock.twilio.from_whatsapp'),
            statusCallback: config('bird-flock.twilio.status_webhook_url'),
        );
    }

    /**
     * Create SendGrid email sender.
     *
     * @return SendgridEmailSender
     */
    private static function createSendgridEmailSender(): SendgridEmailSender
    {
        /** @var SendGrid $client */
        $client = app(SendGrid::class);

        return new SendgridEmailSender(
            client: $client,
            fromEmail: config('bird-flock.sendgrid.from_email'),
            fromName: config('bird-flock.sendgrid.from_name'),
            replyTo: config('bird-flock.sendgrid.reply_to'),
            templates: config('bird-flock.sendgrid.templates', []),
        );
    }
}
