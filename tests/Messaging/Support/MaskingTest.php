<?php

namespace Equidna\BirdFlock\Tests\Messaging\Support;

use PHPUnit\Framework\TestCase;
use Equidna\BirdFlock\Support\Masking;

class MaskingTest extends TestCase
{
    public function testMaskEmail(): void
    {
        $masked = Masking::maskEmail('testuser@example.com');
        $this->assertEquals('t******r@example.com', $masked);
    }

    public function testMaskShortEmail(): void
    {
        $masked = Masking::maskEmail('ab@example.com');
        $this->assertEquals('**@example.com', $masked);
    }

    public function testMaskInvalidEmail(): void
    {
        $masked = Masking::maskEmail('notanemail');
        $this->assertEquals('***', $masked);
    }

    public function testMaskPhone(): void
    {
        $masked = Masking::maskPhone('+5215512345678');
        $this->assertEquals('+5**********78', $masked);
    }

    public function testMaskShortPhone(): void
    {
        $masked = Masking::maskPhone('1234');
        $this->assertEquals('****', $masked);
    }

    public function testMaskApiKey(): void
    {
        $masked = Masking::maskApiKey('sk_test_1234567890abcdef');
        $this->assertEquals('sk_t****************cdef', $masked);
    }

    public function testMaskShortApiKey(): void
    {
        $masked = Masking::maskApiKey('short');
        $this->assertEquals('*****', $masked);
    }
}





