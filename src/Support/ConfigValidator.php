<?php

/**
 * Boot-time configuration validator.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Support;

use RuntimeException;

/**
 * Validates runtime configuration for Bird Flock.
 *
 * Checks credentials and emits warnings/errors via the package logger.
 */
final class ConfigValidator
{
    /**
     * Validate all configured settings.
     *
     * Throws RuntimeException on fatal missing credentials; otherwise emits
     * warnings via the package logger.
     *
     * @return void
     * @throws RuntimeException When critical credentials are missing
     */
    public function validateAll(): void
    {
        $this->validateCore();
        $this->validateTwilio();
        $this->validateSendgrid();
    }

    /**
     * Validate core configuration (table prefix, default queue).
     *
     * @return void
     */
    private function validateCore(): void
    {
        $prefix = config('bird-flock.tables.prefix');
        if (! is_string($prefix) || trim((string) $prefix) === '') {
            Logger::warning('bird-flock.config.table_prefix_missing', [
                'hint' => 'Set BIRD_FLOCK_TABLE_PREFIX to avoid unexpected table names',
            ]);
        }

        $queue = config('bird-flock.default_queue');
        if (! is_string($queue) || trim((string) $queue) === '') {
            Logger::info('bird-flock.config.default_queue_missing', [
                'hint' => 'Using default queue. Set BIRD_FLOCK_DEFAULT_QUEUE to customize',
            ]);
        }
    }

    /**
     * Validate Twilio credentials and From/Messaging Service configuration.
     *
     * @return void
     * @throws RuntimeException When TWILIO_ACCOUNT_SID or TWILIO_AUTH_TOKEN is missing
     */
    private function validateTwilio(): void
    {
        $accountSid = config('bird-flock.twilio.account_sid');
        $authToken = config('bird-flock.twilio.auth_token');

        if (! $accountSid || ! $authToken) {
            throw new RuntimeException('Missing TWILIO_ACCOUNT_SID or TWILIO_AUTH_TOKEN.');
        }

        $messagingService = config('bird-flock.twilio.messaging_service_sid');
        $fromSms = config('bird-flock.twilio.from_sms');
        $fromWhatsApp = config('bird-flock.twilio.from_whatsapp');
        $sandboxMode = config('bird-flock.twilio.sandbox_mode', false);
        $sandboxFrom = config('bird-flock.twilio.sandbox_from');

        if (! $messagingService && ! $fromSms) {
            Logger::warning('bird-flock.twilio.sms_sender_not_configured', [
                'hint' => 'Set TWILIO_MESSAGING_SERVICE_SID or TWILIO_FROM_SMS to enable SMS sends',
            ]);
        }

        if (! $fromWhatsApp) {
            if (! $sandboxMode) {
                Logger::warning('bird-flock.twilio.whatsapp_sender_missing', [
                    'hint' => 'Set TWILIO_FROM_WHATSAPP when using WhatsApp in production',
                ]);
            } else {
                Logger::info('bird-flock.twilio.whatsapp_sandbox_no_from', [
                    'hint' => 'Sandbox mode: WhatsApp FROM may be inferred from TWILIO_FROM_WHATSAPP',
                ]);
            }
        }

        if ($sandboxFrom && ! str_starts_with((string) $sandboxFrom, 'whatsapp:')) {
            Logger::warning('bird-flock.twilio.sandbox_from_missing_prefix', [
                'value' => $sandboxFrom,
                'hint' => 'WhatsApp sandbox From should include the "whatsapp:" prefix',
            ]);
        }
    }

    /**
     * Validate SendGrid webhook signing and From email configuration.
     *
     * @return void
     * @throws RuntimeException When SENDGRID_WEBHOOK_PUBLIC_KEY is missing while webhook signing is required
     */
    private function validateSendgrid(): void
    {
        $requireSigned = config('bird-flock.sendgrid.require_signed_webhooks');
        $publicKey     = config('bird-flock.sendgrid.webhook_public_key');

        if ($requireSigned && ! $publicKey) {
            throw new RuntimeException(
                'Bird Flock requires SENDGRID_WEBHOOK_PUBLIC_KEY when signed SendGrid webhooks are enabled.'
            );
        }

        if (! $requireSigned && ! $publicKey) {
            Logger::info('bird-flock.sendgrid.webhook_signing_disabled');
        }

        $fromEmail = config('bird-flock.sendgrid.from_email');
        if (! $fromEmail) {
            Logger::warning('bird-flock.sendgrid.from_email_missing', [
                'hint' => 'Set SENDGRID_FROM_EMAIL to ensure proper envelope from address',
            ]);
        } elseif (! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            Logger::warning('bird-flock.sendgrid.from_email_invalid', [
                'value' => $fromEmail,
            ]);
        }
    }
}
