<?php

namespace Equidna\BirdFlock\Tests\Unit\Support;

use Equidna\BirdFlock\Support\PayloadNormalizer;
use PHPUnit\Framework\TestCase;

final class PayloadNormalizerTest extends TestCase
{
    public function testNormalizePhoneNumberTrimsAndAddsPlus(): void
    {
        $this->assertSame('+15005550006', PayloadNormalizer::normalizePhoneNumber(' 15005550006 '));
        $this->assertSame('+15005550006', PayloadNormalizer::normalizePhoneNumber("'15005550006'"));
        $this->assertSame('+15005550006', PayloadNormalizer::normalizePhoneNumber('(+1) 500-555-0006'));
    }

    public function testNormalizePhoneNumberRejectsTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PayloadNormalizer::normalizePhoneNumber('123');
    }

    public function testNormalizeWhatsAppRecipientAddsPrefixAndStripsQuotes(): void
    {
        $this->assertSame('whatsapp:+15005550006', PayloadNormalizer::normalizeWhatsAppRecipient('15005550006'));
        $this->assertSame('whatsapp:+15005550006', PayloadNormalizer::normalizeWhatsAppRecipient("'15005550006'"));
        $this->assertSame(
            'whatsapp:+15005550006',
            PayloadNormalizer::normalizeWhatsAppRecipient('whatsapp:+15005550006')
        );
    }

    public function testIsValidEmail(): void
    {
        $this->assertTrue(PayloadNormalizer::isValidEmail('user@example.com'));
        $this->assertFalse(PayloadNormalizer::isValidEmail('not-an-email'));
    }
}
