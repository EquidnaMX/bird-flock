<?php

/**
 * Unit tests for payload normalizer edge cases.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Messaging\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Messaging\Support;

use Equidna\BirdFlock\Support\PayloadNormalizer;
use Equidna\BirdFlock\Tests\TestCase;

final class PayloadNormalizerExtraTest extends TestCase
{
    public function testNormalizePhoneStripsQuotesAndAddsPlus(): void
    {
        $result = PayloadNormalizer::normalizePhoneNumber('"15551234567"');
        $this->assertSame('+15551234567', $result);
    }

    public function testNormalizePhoneThrowsOnTooShortNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PayloadNormalizer::normalizePhoneNumber('123');
    }

    public function testNormalizeWhatsAppAddsPrefixAndValidatesLength(): void
    {
        $res = PayloadNormalizer::normalizeWhatsAppRecipient("'521234567890'");
        $this->assertSame('whatsapp:+521234567890', $res);
    }
}
