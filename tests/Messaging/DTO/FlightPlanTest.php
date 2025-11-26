<?php

namespace Equidna\BirdFlock\Tests\Messaging\DTO;

use PHPUnit\Framework\TestCase;
use Equidna\BirdFlock\DTO\FlightPlan;

class FlightPlanTest extends TestCase
{
    public function testFromArrayCreatesPayload(): void
    {
        $data = [
            'channel' => 'sms',
            'to' => '+15005550006',
            'text' => 'Hello',
            'template_key' => 'welcome',
            'template_data' => ['name' => 'John'],
            'media_urls' => ['https://example.com/image.jpg'],
            'metadata' => ['key' => 'value'],
            'idempotency_key' => 'idempotency_123',
        ];

        $payload = FlightPlan::fromArray($data);

        $this->assertEquals('sms', $payload->channel);
        $this->assertEquals('+15005550006', $payload->to);
        $this->assertEquals('Hello', $payload->text);
        $this->assertEquals('welcome', $payload->templateKey);
        $this->assertEquals(['name' => 'John'], $payload->templateData);
        $this->assertEquals(['https://example.com/image.jpg'], $payload->mediaUrls);
        $this->assertEquals(['key' => 'value'], $payload->metadata);
        $this->assertEquals('idempotency_123', $payload->idempotencyKey);
    }

    public function testToArrayConvertsPayload(): void
    {
        $payload = new FlightPlan(
            channel: 'email',
            to: 'test@example.com',
            subject: 'Test Subject',
            text: 'Plain text',
            html: '<p>HTML</p>',
        );

        $array = $payload->toArray();

        $this->assertEquals('email', $array['channel']);
        $this->assertEquals('test@example.com', $array['to']);
        $this->assertEquals('Test Subject', $array['subject']);
        $this->assertEquals('Plain text', $array['text']);
        $this->assertEquals('<p>HTML</p>', $array['html']);
    }
}





