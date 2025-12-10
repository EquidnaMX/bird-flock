<?php

/**
 * Service provider for Bird Flock package registration and bootstrapping.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SendGrid;
use Twilio\Rest\Client as TwilioClient;
use Equidna\BirdFlock\Console\Commands\DeadLetterCommand;
use Equidna\BirdFlock\Console\Commands\SendTestEmailCommand;
use Equidna\BirdFlock\Console\Commands\SendTestSmsCommand;
use Equidna\BirdFlock\Console\Commands\SendTestWhatsappCommand;
use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Equidna\BirdFlock\Repositories\EloquentOutboundMessageRepository;
use Equidna\BirdFlock\Support\ConfigValidator;
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
                    throw new RuntimeException(
                        'Bird Flock Twilio credentials are not configured. ' .
                        'Set TWILIO_ACCOUNT_SID and TWILIO_AUTH_TOKEN environment variables.'
                    );
                }

                // Configure HTTP timeouts via CurlClient options
                $timeout = config('bird-flock.twilio.timeout', 30);
                $connectTimeout = config('bird-flock.twilio.connect_timeout', 10);

                $httpClient = new \Twilio\Http\CurlClient([
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                ]);

                $client = new TwilioClient($accountSid, $authToken, null, null, $httpClient);

                return $client;
            }
        );

        $this->app->singleton(
            SendGrid::class,
            function (): SendGrid {
                $apiKey = config('bird-flock.sendgrid.api_key');

                if (!$apiKey) {
                    throw new RuntimeException(
                        'Bird Flock SendGrid API key is not configured. ' .
                        'Set SENDGRID_API_KEY environment variable.'
                    );
                }

                $client = new SendGrid($apiKey);

                // Configure HTTP timeouts
                $timeout = config('bird-flock.sendgrid.timeout', 30);
                $connectTimeout = config('bird-flock.sendgrid.connect_timeout', 10);

                $client->client->setCurlOptions([
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                ]);

                return $client;
            }
        );

        $this->app->singleton(
            \Vonage\Client::class,
            function (): \Vonage\Client {
                $apiKey = config('bird-flock.vonage.api_key');
                $apiSecret = config('bird-flock.vonage.api_secret');

                if (!$apiKey || !$apiSecret) {
                    throw new RuntimeException(
                        'Bird Flock Vonage credentials are not configured. ' .
                        'Set VONAGE_API_KEY and VONAGE_API_SECRET environment variables.'
                    );
                }

                $credentials = new \Vonage\Client\Credentials\Basic($apiKey, $apiSecret);
                $client = new \Vonage\Client($credentials);

                return $client;
            }
        );

        $this->app->singleton(
            \Mailgun\Mailgun::class,
            function (): \Mailgun\Mailgun {
                $apiKey = config('bird-flock.mailgun.api_key');

                if (!$apiKey) {
                    throw new RuntimeException(
                        'Bird Flock Mailgun API key is not configured. ' .
                        'Set MAILGUN_API_KEY environment variable.'
                    );
                }

                $endpoint = config('bird-flock.mailgun.endpoint', 'api.mailgun.net');
                $client = \Mailgun\Mailgun::create($apiKey, $endpoint);

                return $client;
            }
        );

        // Bind metrics collector implementation (default no-op logger-backed).
        $this->app->bind(
            \Equidna\BirdFlock\Contracts\MetricsCollectorInterface::class,
            \Equidna\BirdFlock\Support\MetricsCollector::class
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Run centralized config validation. Throwing validations will
        // surface during application boot; non-fatal issues are logged.
        try {
            (new ConfigValidator())->validateAll();
        } catch (\Throwable $e) {
            // Rethrow so consuming applications fail-fast on fatal config errors
            throw $e;
        }

        $this->registerCommands();

        $this->publishes([
            __DIR__ . '/../config/bird-flock.php' => config_path('bird-flock.php'),
        ], 'bird-flock-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/bird-flock'),
        ], 'bird-flock-migrations');
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
                \Equidna\BirdFlock\Console\Commands\ConfigValidateCommand::class,
                \Equidna\BirdFlock\Console\Commands\DeadLetterStatsCommand::class,
            ]);
        }
    }
}
