<?php

/**
 * Unit tests for config validation CLI command.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Messaging\Console
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Messaging\Console;

use Equidna\BirdFlock\Console\Commands\ConfigValidateCommand;
use Equidna\BirdFlock\Tests\Support\InMemoryLogger;
use Equidna\BirdFlock\Tests\TestCase;
use Illuminate\Container\Container;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Illuminate\Console\OutputStyle;

final class ConfigValidateCommandTest extends TestCase
{
    public function testReturnsNonZeroWhenCredentialsMissing(): void
    {
        $cmd = new ConfigValidateCommand();
        $cmd->setLaravel(Container::getInstance());
        // Attach a minimal OutputStyle so InteractsWithIO works
        $style = new OutputStyle(new ArrayInput([]), new BufferedOutput());
        $cmd->setOutput($style);

        // Ensure Twilio credentials are missing
        $this->setConfigValue('bird-flock.twilio.account_sid', null);
        $this->setConfigValue('bird-flock.twilio.auth_token', null);

        Container::getInstance()->instance('bird-flock.logger', new InMemoryLogger());

        $code = $cmd->handle();

        $this->assertSame(2, $code);
    }

    public function testReturnsZeroWhenValidAndEmitsWarnings(): void
    {
        $cmd = new ConfigValidateCommand();
        $cmd->setLaravel(Container::getInstance());
        $style = new OutputStyle(new ArrayInput([]), new BufferedOutput());
        $cmd->setOutput($style);

        // Provide required Twilio creds
        $this->setConfigValue('bird-flock.twilio.account_sid', 'AC123');
        $this->setConfigValue('bird-flock.twilio.auth_token', 'secret');

        // Provide minimal SendGrid so it does not fatal
        $this->setConfigValue('bird-flock.sendgrid.api_key', 'SG.x');

        Container::getInstance()->instance('bird-flock.logger', new InMemoryLogger());

        $code = $cmd->handle();

        $this->assertSame(0, $code);
    }
}
