<?php

/**
 * Fake Twilio message list for testing.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Support;

class TwilioMessageListFake
{
    public function create(string $to, array $params = [])
    {
        return null;
    }
}
