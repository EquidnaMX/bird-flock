<?php

/**
 * Edge case tests for FlightPlan validation.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Unit\DTO
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Unit\DTO;

use Equidna\BirdFlock\DTO\FlightPlan;
use PHPUnit\Framework\TestCase;

final class FlightPlanValidationTest extends TestCase
{
    public function testInvalidChannelThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid channel 'invalid'");

        new FlightPlan(
            channel: 'invalid',
            to: '+15005550006'
        );
    }

    public function testEmptyRecipientThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Recipient (to) cannot be empty');

        new FlightPlan(
            channel: 'sms',
            to: ''
        );
    }

    public function testWhitespaceOnlyRecipientThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Recipient (to) cannot be empty');

        new FlightPlan(
            channel: 'email',
            to: '   '
        );
    }

    public function testInvalidEmailFormatThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid email address 'not-an-email'");

        new FlightPlan(
            channel: 'email',
            to: 'not-an-email'
        );
    }

    public function testPhoneNumberTooShortThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid phone number '+123'");

        new FlightPlan(
            channel: 'sms',
            to: '+123'
        );
    }

    public function testPhoneNumberTooLongThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid phone number");

        new FlightPlan(
            channel: 'sms',
            to: '+123456789012345678901234567890'
        );
    }

    public function testIdempotencyKeyTooLongThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Idempotency key cannot exceed 128 characters');

        new FlightPlan(
            channel: 'sms',
            to: '+15005550006',
            idempotencyKey: str_repeat('a', 129)
        );
    }

    public function testValidSmsWithMinimalPhoneNumber(): void
    {
        $payload = new FlightPlan(
            channel: 'sms',
            to: '+12345678'
        );

        $this->assertSame('sms', $payload->channel);
        $this->assertSame('+12345678', $payload->to);
    }

    public function testValidEmailWithPlusAddressing(): void
    {
        $payload = new FlightPlan(
            channel: 'email',
            to: 'user+tag@example.com'
        );

        $this->assertSame('email', $payload->to);
    }

    public function testValidWhatsAppWithPrefix(): void
    {
        $payload = new FlightPlan(
            channel: 'whatsapp',
            to: 'whatsapp:+14155551234'
        );

        $this->assertSame('whatsapp:+14155551234', $payload->to);
    }

    public function testIdempotencyKeyExactly128CharsAccepted(): void
    {
        $key = str_repeat('a', 128);

        $payload = new FlightPlan(
            channel: 'sms',
            to: '+15005550006',
            idempotencyKey: $key
        );

        $this->assertSame($key, $payload->idempotencyKey);
    }

    public function testAllChannelsAccepted(): void
    {
        $channels = ['sms', 'whatsapp', 'email'];

        foreach ($channels as $channel) {
            $to = $channel === 'email' ? 'test@example.com' : '+15005550006';

            $payload = new FlightPlan(
                channel: $channel,
                to: $to
            );

            $this->assertSame($channel, $payload->channel);
        }
    }
}
