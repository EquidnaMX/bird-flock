<?php

namespace Equidna\BirdFlock\Tests\Messaging\Support;

use PHPUnit\Framework\TestCase;
use Equidna\BirdFlock\Support\BackoffStrategy;

class BackoffStrategyTest extends TestCase
{
    public function testDecorrelatedJitterFirstAttempt(): void
    {
        $delay = BackoffStrategy::decorrelatedJitter(0, 1000, 60000, 0);
        $this->assertEquals(1000, $delay);
    }

    public function testDecorrelatedJitterRespectsMax(): void
    {
        $delay = BackoffStrategy::decorrelatedJitter(10, 1000, 5000, 2000);
        $this->assertLessThanOrEqual(5000, $delay);
        $this->assertGreaterThanOrEqual(1000, $delay);
    }

    public function testExponentialWithJitterFirstAttempt(): void
    {
        $delay = BackoffStrategy::exponentialWithJitter(0, 1000, 60000);
        $this->assertGreaterThanOrEqual(1000, $delay);
        $this->assertLessThanOrEqual(1500, $delay);
    }

    public function testExponentialWithJitterGrowsExponentially(): void
    {
        $delay1 = BackoffStrategy::exponentialWithJitter(1, 1000, 60000);
        $this->assertGreaterThanOrEqual(2000, $delay1);
        $this->assertLessThanOrEqual(3000, $delay1);
    }

    public function testExponentialWithJitterRespectsMax(): void
    {
        $delay = BackoffStrategy::exponentialWithJitter(20, 1000, 10000);
        $this->assertLessThanOrEqual(10000, $delay);
    }
}





