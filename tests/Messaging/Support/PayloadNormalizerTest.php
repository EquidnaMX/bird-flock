<?php

namespace Equidna\BirdFlock\Tests\Messaging\Support;

use PHPUnit\Framework\TestCase;
use Equidna\BirdFlock\Support\PayloadNormalizer;

class PayloadNormalizerTest extends TestCase
{
    public function testNormalizeWhatsAppRecipientWithPrefix(): void
    {
        $result = PayloadNormalizer::normalizeWhatsAppRecipient('whatsapp:+5215512345678');
        $this->assertEquals('whatsapp:+5215512345678', $result);
    }

    public function testNormalizeWhatsAppRecipientWithoutPrefix(): void
    {
        $result = PayloadNormalizer::normalizeWhatsAppRecipient('+5215512345678');
        $this->assertEquals('whatsapp:+5215512345678', $result);
    }

    public function testNormalizeWhatsAppRecipientWithoutPlusSign(): void
    {
        $result = PayloadNormalizer::normalizeWhatsAppRecipient('5215512345678');
        $this->assertEquals('whatsapp:+5215512345678', $result);
    }

    public function testNormalizePhoneNumberWithPlus(): void
    {
        $result = PayloadNormalizer::normalizePhoneNumber('+5215512345678');
        $this->assertEquals('+5215512345678', $result);
    }

    public function testNormalizePhoneNumberWithoutPlus(): void
    {
        $result = PayloadNormalizer::normalizePhoneNumber('5215512345678');
        $this->assertEquals('+5215512345678', $result);
    }

    public function testNormalizePhoneNumberWithSpaces(): void
    {
        $result = PayloadNormalizer::normalizePhoneNumber('+52 155 1234 5678');
        $this->assertEquals('+5215512345678', $result);
    }

    public function testIsValidEmailWithValidEmail(): void
    {
        $this->assertTrue(PayloadNormalizer::isValidEmail('test@example.com'));
    }

    public function testIsValidEmailWithInvalidEmail(): void
    {
        $this->assertFalse(PayloadNormalizer::isValidEmail('invalid-email'));
    }
}





