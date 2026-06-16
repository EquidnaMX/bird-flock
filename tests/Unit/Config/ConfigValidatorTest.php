<?php

namespace Equidna\BirdFlock\Tests\Unit\Config;

use Equidna\BirdFlock\Senders\Sendgrid\SendgridEmailSenderDefinition;
use Equidna\BirdFlock\Support\ConfigValidator;
use Equidna\BirdFlock\Tests\Support\ConfigurableSenderDefinition;
use Equidna\BirdFlock\Tests\Support\InMemoryLogger;
use Equidna\BirdFlock\Tests\Support\ConfigurableSender;
use Equidna\BirdFlock\Tests\Support\RequiresScalarSender;
use Equidna\BirdFlock\Tests\Support\WrongSender;
use Equidna\BirdFlock\Tests\TestCase;
use Illuminate\Container\Container;
use InvalidArgumentException;

final class ConfigValidatorTest extends TestCase
{
    public function testValidateAllWarnsWhenTwilioMissing(): void
    {
        $this->setConfigValue('bird-flock-twilio.account_sid', null);
        $this->setConfigValue('bird-flock-twilio.auth_token', null);

        $logger = new InMemoryLogger();
        Container::getInstance()->instance('bird-flock.logger', $logger);

        $validator = new ConfigValidator();
        $validator->validateAll();

        $this->assertTrue($logger->has('bird-flock-twilio.credentials_missing'));
    }

    public function testValidateLogsWarningsAndInfo(): void
    {
        // Provide required Twilio creds to avoid fatal exception
        $this->setConfigValue('bird-flock-twilio.account_sid', 'AC123');
        $this->setConfigValue('bird-flock-twilio.auth_token', 'secret');

        $this->setConfigValue('bird-flock.channels.email', [
            'strategy' => 'round_robin',
            'senders' => [
                'sendgrid' => SendgridEmailSenderDefinition::class,
            ],
        ]);

        $this->setConfigValue('bird-flock-sendgrid.require_signed_webhooks', false);
        $this->setConfigValue('bird-flock-sendgrid.webhook_public_key', null);
        $this->setConfigValue('bird-flock-sendgrid.from_email', null);
        $this->setConfigValue('bird-flock.tables.prefix', '');

        $logger = new InMemoryLogger();
        Container::getInstance()->instance('bird-flock.logger', $logger);

        $validator = new ConfigValidator();
        $validator->validateAll();

        $this->assertTrue($logger->has('bird-flock-sendgrid.from_email_missing'));
        $this->assertTrue($logger->has('bird-flock.config.table_prefix_missing'));
    }

    public function testExternalSenderWithoutValidatorDoesNotFail(): void
    {
        $this->setConfigValue('bird-flock.channels', [
            'sms' => [
                'strategy' => 'round_robin',
                'senders' => [
                    'acme' => [
                        'sender' => ConfigurableSender::class,
                        'arguments' => [
                            'apiKey' => 'secret',
                            'from' => '+15551234567',
                        ],
                    ],
                ],
            ],
        ]);

        $validator = new ConfigValidator();
        $validator->validateAll();

        $this->assertTrue(true);
    }

    public function testInvalidSenderClassThrows(): void
    {
        $this->setConfigValue('bird-flock.channels', [
            'sms' => [
                'strategy' => 'round_robin',
                'senders' => [
                    'acme' => [
                        'sender' => 'App\\Messaging\\MissingSender',
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Sender class 'App\\Messaging\\MissingSender' for 'sms:acme' does not exist");

        (new ConfigValidator())->validateAll();
    }

    public function testSenderMustImplementMessageSenderInterface(): void
    {
        $this->setConfigValue('bird-flock.channels', [
            'sms' => [
                'strategy' => 'round_robin',
                'senders' => [
                    'acme' => WrongSender::class,
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        (new ConfigValidator())->validateAll();
    }

    public function testMissingRequiredScalarArgumentThrows(): void
    {
        $this->setConfigValue('bird-flock.channels', [
            'sms' => [
                'strategy' => 'round_robin',
                'senders' => [
                    'acme' => RequiresScalarSender::class,
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("missing required constructor argument 'apiKey'");

        (new ConfigValidator())->validateAll();
    }

    public function testNullConfigReferenceLogsWarningForRequiredArgument(): void
    {
        $this->setConfigValue('services.acme.api_key', null);
        $this->setConfigValue('bird-flock.channels', [
            'sms' => [
                'strategy' => 'round_robin',
                'senders' => [
                    'acme' => [
                        'sender' => ConfigurableSender::class,
                        'arguments' => [
                            'apiKey' => 'config:services.acme.api_key',
                            'from' => '+15551234567',
                        ],
                    ],
                ],
            ],
        ]);

        $logger = new InMemoryLogger();
        Container::getInstance()->instance('bird-flock.logger', $logger);

        (new ConfigValidator())->validateAll();

        $this->assertTrue($logger->has('bird-flock.config.sender_argument_null'));
    }

    public function testSenderDefinitionValidatorRuns(): void
    {
        $this->setConfigValue('services.acme.api_key', 'secret');
        $this->setConfigValue('services.acme.from_sms', '+15551234567');
        $this->setConfigValue('bird-flock.channels', [
            'sms' => [
                'strategy' => 'round_robin',
                'senders' => [
                    'acme' => ConfigurableSenderDefinition::class,
                ],
            ],
        ]);

        $logger = new InMemoryLogger();
        Container::getInstance()->instance('bird-flock.logger', $logger);

        (new ConfigValidator())->validateAll();

        $this->assertTrue($logger->has('bird-flock.test_sender.validator'));
    }
}
