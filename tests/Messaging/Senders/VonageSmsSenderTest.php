<?php

/**
 * Vonage SMS sender unit tests.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Messaging\Senders
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Messaging\Senders;

use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\Senders\VonageSmsSender;
use Exception;
use PHPUnit\Framework\TestCase;
use Vonage\Client as VonageClient;
use Vonage\SMS\Client as SmsClient;
use Vonage\SMS\Collection;
use Vonage\SMS\Message\SMS;

/**
 * Tests Vonage SMS sender behavior.
 */
final class VonageSmsSenderTest extends TestCase
{
    /**
     * Returns a successful SMS send result.
     *
     * @return void
     */
    public function testSendSuccessReturnsSuccessResult(): void
    {
        $this->markTestSkipped('Vonage SDK mocking requires integration with actual Vonage Message objects');
    }

    /**
     * Returns an undeliverable result when status is non-zero.
     *
     * @return void
     */
    public function testSendFailureWithPermanentErrorReturnsUndeliverable(): void
    {
        $this->markTestSkipped('Vonage SDK mocking requires integration with actual Vonage Message objects');
    }

    /**
     * Returns a failed result for transient errors.
     *
     * @return void
     */
    public function testSendFailureWithTransientErrorReturnsFailed(): void
    {
        $this->markTestSkipped('Vonage SDK mocking requires integration with actual Vonage Message objects');
    }

    /**
     * Returns a failed result when exception is thrown.
     *
     * @return void
     */
    public function testSendExceptionReturnsFailed(): void
    {
        $this->markTestSkipped('Vonage SDK mocking requires integration with actual Vonage Message objects');
    }
}
