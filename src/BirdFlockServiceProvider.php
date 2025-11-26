<?php

namespace Equidna\BirdFlock;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Equidna\BirdFlock\Repositories\EloquentOutboundMessageRepository;
use Equidna\BirdFlock\Console\Commands\DeadLetterCommand;
use Equidna\BirdFlock\Console\Commands\SendTestSmsCommand;
use Equidna\BirdFlock\Console\Commands\SendTestWhatsappCommand;
use Equidna\BirdFlock\Console\Commands\SendTestEmailCommand;
use Equidna\BirdFlock\Support\Logger as BirdFlockLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SendGrid;
use Twilio\Rest\Client as TwilioClient;
use RuntimeException;

class BirdFlockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bird-flock.php', 'bird-flock');
        $this->registerLogger();

        $this->app->bind(
            OutboundMessageRepositoryInterface::class,
            EloquentOutboundMessageRepository::class
        );

        $this->app->singleton(
            TwilioClient::class,
            function (): TwilioClient {
                $accountSid = config('bird-flock.twilio.account_sid');
                $authToken  = config('bird-flock.twilio.auth_token');

                if (!$accountSid || !$authToken) {
                    throw new RuntimeException('Bird Flock Twilio credentials are not configured.');
                }

                return new TwilioClient($accountSid, $authToken);
            }
        );

        $this->app->singleton(
            SendGrid::class,
            function (): SendGrid {
                $apiKey = config('bird-flock.sendgrid.api_key');

                if (!$apiKey) {
                    throw new RuntimeException('Bird Flock SendGrid API key is not configured.');
                }

                return new SendGrid($apiKey);
            }
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->ensureTwilioConfiguration();
        $this->ensureSendgridConfiguration();
        $this->registerCommands();

        $this->publishes([
            __DIR__ . '/../config/bird-flock.php' => config_path('bird-flock.php'),
        ], 'bird-flock-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/bird-flock'),
        ], 'bird-flock-migrations');
    }

    /**
     * Ensure SendGrid webhook signing is configured correctly.
     */
    private function ensureSendgridConfiguration(): void
    {
        $requireSigned = config('bird-flock.sendgrid.require_signed_webhooks');
        $publicKey     = config('bird-flock.sendgrid.webhook_public_key');

        if ($requireSigned && !$publicKey) {
            throw new \RuntimeException(
                'Bird Flock requires SENDGRID_WEBHOOK_PUBLIC_KEY when signed SendGrid webhooks are enabled.'
            );
        }

        if (!$requireSigned && !$publicKey) {
            BirdFlockLogger::info('bird-flock.sendgrid.webhook_signing_disabled');
        }
    }

    /**
     * Ensure Twilio configuration is valid.
     */
    private function ensureTwilioConfiguration(): void
    {
        $accountSid = config('bird-flock.twilio.account_sid');
        $authToken = config('bird-flock.twilio.auth_token');

        if (!$accountSid || !$authToken) {
            throw new RuntimeException('Bird Flock requires TWILIO_ACCOUNT_SID and TWILIO_AUTH_TOKEN to be configured.');
        }

        if (!config('bird-flock.twilio.messaging_service_sid') && !config('bird-flock.twilio.from_sms')) {
            BirdFlockLogger::warning('bird-flock.twilio.sms_sender_not_configured');
        }

        if (!config('bird-flock.twilio.from_whatsapp')) {
            BirdFlockLogger::warning('bird-flock.twilio.whatsapp_sender_missing');
        }
    }

    /**
     * Register the logging binding.
     */
    private function registerLogger(): void
    {
        $this->app->singleton('bird-flock.logger', function (): LoggerInterface {
            if (!config('bird-flock.logging.enabled', true)) {
                return new NullLogger();
            }

            $channel = config('bird-flock.logging.channel');
            $driver = $channel ?: config('logging.default', 'stack');

            try {
                return Log::channel($driver);
            } catch (\Throwable) {
                return new NullLogger();
            }
        });
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DeadLetterCommand::class,
                SendTestSmsCommand::class,
                SendTestWhatsappCommand::class,
                SendTestEmailCommand::class,
            ]);
        }
    }
}
