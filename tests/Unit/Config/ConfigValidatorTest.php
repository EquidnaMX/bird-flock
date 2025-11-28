<?php

namespace Equidna\BirdFlock\Tests\Unit\Config;

use Equidna\BirdFlock\Support\ConfigValidator;
use Equidna\BirdFlock\Tests\Support\InMemoryLogger;
use Equidna\BirdFlock\Tests\TestCase;
use RuntimeException;
use Illuminate\Container\Container;

final class ConfigValidatorTest extends TestCase
{
    public function testValidateAllThrowsWhenTwilioMissing(): void
    {
        $this->expectException(RuntimeException::class);

        // By default TestCase leaves twilio auth_token null; ensure account_sid is missing
        $this->setConfigValue('bird-flock.twilio.account_sid', null);

        $validator = new ConfigValidator();
        $validator->validateAll();
    }

    public function testValidateLogsWarningsAndInfo(): void
    {
        // Provide required Twilio creds to avoid fatal exception
        $this->setConfigValue('bird-flock.twilio.account_sid', 'AC123');
        $this->setConfigValue('bird-flock.twilio.auth_token', 'secret');

        // Ensure SendGrid doesn't require signing and no public key provided
        $this->setConfigValue('bird-flock.sendgrid.require_signed_webhooks', false);
        $this->setConfigValue('bird-flock.sendgrid.webhook_public_key', null);

        // Force missing from email and empty table prefix to trigger warnings
        $this->setConfigValue('bird-flock.sendgrid.from_email', null);
        $this->setConfigValue('bird-flock.tables.prefix', '');

        $logger = new InMemoryLogger();
        Container::getInstance()->instance('bird-flock.logger', $logger);

        $validator = new ConfigValidator();
        $validator->validateAll();

        $this->assertTrue($logger->has('bird-flock.sendgrid.from_email_missing'));
        $this->assertTrue($logger->has('bird-flock.config.table_prefix_missing'));
    }
}
