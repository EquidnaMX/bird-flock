<?php

namespace Equidna\BirdFlock\Tests\Messaging\DTO;

use PHPUnit\Framework\TestCase;
use Equidna\BirdFlock\DTO\ProviderSendResult;

class ProviderSendResultTest extends TestCase
{
    public function testSuccessFactory(): void
    {
        $result = ProviderSendResult::success(
            providerMessageId: 'msg123',
            raw: ['status' => 'queued'],
        );

        $this->assertEquals('msg123', $result->providerMessageId);
        $this->assertEquals('sent', $result->status);
        $this->assertNull($result->errorCode);
        $this->assertNull($result->errorMessage);
        $this->assertEquals(['status' => 'queued'], $result->raw);
    }

    public function testFailedFactory(): void
    {
        $result = ProviderSendResult::failed(
            errorCode: '429',
            errorMessage: 'Rate limit exceeded',
            raw: ['error' => 'too many requests'],
        );

        $this->assertNull($result->providerMessageId);
        $this->assertEquals('failed', $result->status);
        $this->assertEquals('429', $result->errorCode);
        $this->assertEquals('Rate limit exceeded', $result->errorMessage);
        $this->assertEquals(['error' => 'too many requests'], $result->raw);
    }

    public function testUndeliverableFactory(): void
    {
        $result = ProviderSendResult::undeliverable(
            errorCode: 'INVALID_NUMBER',
            errorMessage: 'Invalid phone number',
        );

        $this->assertNull($result->providerMessageId);
        $this->assertEquals('undeliverable', $result->status);
        $this->assertEquals('INVALID_NUMBER', $result->errorCode);
        $this->assertEquals('Invalid phone number', $result->errorMessage);
    }
}





