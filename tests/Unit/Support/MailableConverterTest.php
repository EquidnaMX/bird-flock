<?php

/**
 * Unit tests for MailableConverter.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Unit\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Unit\Support;

use Equidna\BirdFlock\Support\MailableConverter;
use Equidna\BirdFlock\Tests\Support\SetsUpViewFactory;
use Equidna\BirdFlock\Tests\TestCase;
use Illuminate\Mail\Mailable;

final class MailableConverterTest extends TestCase
{
    use SetsUpViewFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpViewFactory();
    }

    protected function tearDown(): void
    {
        $this->tearDownViewFactory();
        parent::tearDown();
    }

    public function testConvertMailableWithHtmlView(): void
    {
        $this->createView('test-email', '<h1>Hello <?php echo $name; ?></h1><p>Welcome to Bird Flock!</p>');

        $mailable = new class extends Mailable {
            public function build()
            {
                return $this->view('test-email')
                    ->subject('Test Email')
                    ->with(['name' => 'John']);
            }
        };

        $flightPlan = MailableConverter::convert(
            mailable: $mailable,
            to: 'john@example.com'
        );

        $this->assertSame('email', $flightPlan->channel);
        $this->assertSame('john@example.com', $flightPlan->to);
        $this->assertSame('Test Email', $flightPlan->subject);
        $this->assertStringContainsString('Hello John', $flightPlan->html);
        $this->assertStringContainsString('Welcome to Bird Flock!', $flightPlan->html);
        $this->assertNotNull($flightPlan->text);
        $this->assertStringContainsString('Hello John', $flightPlan->text);
    }

    public function testConvertMailableWithTextView(): void
    {
        $this->createView('test-email-html', '<h1>Hello <?php echo $name; ?></h1>');
        $this->createView('test-email-text', 'Hello <?php echo $name; ?>');

        $mailable = new class extends Mailable {
            public function build()
            {
                return $this->view('test-email-html')
                    ->text('test-email-text')
                    ->subject('Test Email')
                    ->with(['name' => 'Jane']);
            }
        };

        $flightPlan = MailableConverter::convert(
            mailable: $mailable,
            to: 'jane@example.com'
        );

        $this->assertSame('email', $flightPlan->channel);
        $this->assertSame('jane@example.com', $flightPlan->to);
        $this->assertSame('Test Email', $flightPlan->subject);
        $this->assertStringContainsString('Hello Jane', $flightPlan->html);
        $this->assertStringContainsString('Hello Jane', $flightPlan->text);
    }

    public function testConvertMailableWithIdempotencyKey(): void
    {
        $this->createView('simple', '<p>Simple email</p>');

        $mailable = new class extends Mailable {
            public function build()
            {
                return $this->view('simple')->subject('Simple');
            }
        };

        $idempotencyKey = 'user:123:welcome';
        $flightPlan = MailableConverter::convert(
            mailable: $mailable,
            to: 'user@example.com',
            idempotencyKey: $idempotencyKey
        );

        $this->assertSame($idempotencyKey, $flightPlan->idempotencyKey);
    }

    public function testConvertMailableWithScheduledSendTime(): void
    {
        $this->createView('scheduled', '<p>Scheduled email</p>');

        $mailable = new class extends Mailable {
            public function build()
            {
                return $this->view('scheduled')->subject('Scheduled');
            }
        };

        $sendAt = new \DateTimeImmutable('+1 hour');
        $flightPlan = MailableConverter::convert(
            mailable: $mailable,
            to: 'user@example.com',
            sendAt: $sendAt
        );

        $this->assertSame($sendAt, $flightPlan->sendAt);
    }

    public function testConvertMailableWithMetadata(): void
    {
        $this->createView('metadata', '<p>Email with metadata</p>');

        $mailable = new class extends Mailable {
            public function build()
            {
                return $this->view('metadata')->subject('Metadata Test');
            }
        };

        $metadata = ['user_id' => 123, 'campaign' => 'welcome'];
        $flightPlan = MailableConverter::convert(
            mailable: $mailable,
            to: 'user@example.com',
            metadata: $metadata
        );

        $this->assertSame(123, $flightPlan->metadata['user_id']);
        $this->assertSame('welcome', $flightPlan->metadata['campaign']);
    }

    public function testHtmlToTextConversion(): void
    {
        $this->createView('html-complex', '
            <html>
                <head><style>body { color: red; }</style></head>
                <body>
                    <h1>Welcome!</h1>
                    <p>This is a <strong>test</strong> email.</p>
                    <br>
                    <script>alert("test");</script>
                    <p>Thanks!</p>
                </body>
            </html>
        ');

        $mailable = new class extends Mailable {
            public function build()
            {
                return $this->view('html-complex')->subject('Complex HTML');
            }
        };

        $flightPlan = MailableConverter::convert(
            mailable: $mailable,
            to: 'user@example.com'
        );

        // Text should be extracted from HTML, stripping tags and scripts
        $this->assertStringContainsString('Welcome!', $flightPlan->text);
        $this->assertStringContainsString('test email', $flightPlan->text);
        $this->assertStringContainsString('Thanks!', $flightPlan->text);
        $this->assertStringNotContainsString('<script>', $flightPlan->text);
        $this->assertStringNotContainsString('alert', $flightPlan->text);
        $this->assertStringNotContainsString('<style>', $flightPlan->text);
    }
}
