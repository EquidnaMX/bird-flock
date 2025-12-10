<?php

/**
 * Unit tests for BirdFlock Mailable support.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Unit
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Unit;

use Equidna\BirdFlock\BirdFlock;
use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Equidna\BirdFlock\Tests\TestCase;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\View;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;

final class BirdFlockMailableTest extends TestCase
{
    private string $tempViewPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary directory for views
        $this->tempViewPath = sys_get_temp_dir() . '/bird-flock-test-views-' . uniqid();
        mkdir($this->tempViewPath, 0777, true);

        // Set up view factory
        $this->setupViewFactory();
    }

    protected function tearDown(): void
    {
        // Clean up temporary views
        if (is_dir($this->tempViewPath)) {
            $files = glob($this->tempViewPath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempViewPath);
        }

        parent::tearDown();
    }

    private function setupViewFactory(): void
    {
        $container = Container::getInstance();
        $filesystem = new Filesystem();
        
        // Set up view finder
        $finder = new FileViewFinder($filesystem, [$this->tempViewPath]);
        
        // Set up engine resolver
        $resolver = new EngineResolver();
        
        // Add PHP engine
        $resolver->register('php', function () {
            return new \Illuminate\View\Engines\PhpEngine(new Filesystem());
        });
        
        // Create view factory
        $factory = new Factory($resolver, $finder, new \Illuminate\Events\Dispatcher($container));
        
        // Register view factory in container and facade
        $container->instance('view', $factory);
        View::setFacadeApplication($container);
        View::swap($factory);
    }

    private function createView(string $name, string $content): void
    {
        file_put_contents($this->tempViewPath . '/' . $name . '.php', $content);
    }

    public function testDispatchMailable(): void
    {
        $this->createView('welcome', '<h1>Welcome <?php echo $name; ?>!</h1>');

        $mailable = new class extends Mailable {
            public function build()
            {
                return $this->view('welcome')
                    ->subject('Welcome to Bird Flock')
                    ->with(['name' => 'Alice']);
            }
        };

        // Mock repository
        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByIdempotencyKey')
            ->willReturn(null);
        
        $repository->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) {
                $this->assertSame('email', $data['channel']);
                $this->assertSame('alice@example.com', $data['to']);
                $this->assertSame('Welcome to Bird Flock', $data['subject']);
                $this->assertSame('queued', $data['status']);
                $this->assertIsArray($data['payload']);
                $this->assertStringContainsString('Welcome Alice!', $data['payload']['html']);
                return true;
            }));

        $messageId = BirdFlock::dispatchMailable(
            mailable: $mailable,
            to: 'alice@example.com',
            repository: $repository
        );

        $this->assertNotEmpty($messageId);
        $this->assertIsString($messageId);
    }

    public function testDispatchMailableWithIdempotencyKey(): void
    {
        $this->createView('order-confirmation', '<p>Order confirmed!</p>');

        $mailable = new class extends Mailable {
            public function build()
            {
                return $this->view('order-confirmation')
                    ->subject('Order Confirmation');
            }
        };

        $idempotencyKey = 'user:456:order:789:confirmation';

        // Mock repository
        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByIdempotencyKey')
            ->with($idempotencyKey)
            ->willReturn(null);
        
        $repository->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) use ($idempotencyKey) {
                $this->assertSame($idempotencyKey, $data['idempotencyKey']);
                return true;
            }));

        $messageId = BirdFlock::dispatchMailable(
            mailable: $mailable,
            to: 'bob@example.com',
            idempotencyKey: $idempotencyKey,
            repository: $repository
        );

        $this->assertNotEmpty($messageId);
    }

    public function testDispatchMailableWithScheduledSendTime(): void
    {
        $this->createView('newsletter', '<p>Monthly newsletter</p>');

        $mailable = new class extends Mailable {
            public function build()
            {
                return $this->view('newsletter')
                    ->subject('Monthly Newsletter');
            }
        };

        $sendAt = new \DateTimeImmutable('+1 day');

        // Mock repository
        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByIdempotencyKey')
            ->willReturn(null);
        
        $repository->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) use ($sendAt) {
                $this->assertIsArray($data['payload']);
                $this->assertSame(
                    $sendAt->format('Y-m-d\TH:i:s\Z'),
                    $data['payload']['send_at']
                );
                return true;
            }));

        $messageId = BirdFlock::dispatchMailable(
            mailable: $mailable,
            to: 'subscriber@example.com',
            sendAt: $sendAt,
            repository: $repository
        );

        $this->assertNotEmpty($messageId);
    }

    public function testDispatchMailableWithMetadata(): void
    {
        $this->createView('campaign', '<p>Campaign email</p>');

        $mailable = new class extends Mailable {
            public function build()
            {
                return $this->view('campaign')
                    ->subject('Campaign');
            }
        };

        $metadata = [
            'campaign_id' => 'spring-2024',
            'user_segment' => 'premium',
        ];

        // Mock repository
        $repository = $this->createMock(OutboundMessageRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByIdempotencyKey')
            ->willReturn(null);
        
        $repository->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) use ($metadata) {
                $this->assertIsArray($data['payload']);
                $this->assertArrayHasKey('metadata', $data['payload']);
                $this->assertSame('spring-2024', $data['payload']['metadata']['campaign_id']);
                $this->assertSame('premium', $data['payload']['metadata']['user_segment']);
                return true;
            }));

        $messageId = BirdFlock::dispatchMailable(
            mailable: $mailable,
            to: 'premium@example.com',
            metadata: $metadata,
            repository: $repository
        );

        $this->assertNotEmpty($messageId);
    }
}
