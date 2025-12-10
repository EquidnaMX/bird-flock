<?php

/**
 * Trait for setting up view factory in tests.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Support;

use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\View;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;

/**
 * Provides functionality to set up a view factory for testing Mailables.
 */
trait SetsUpViewFactory
{
    protected string $tempViewPath;

    /**
     * Set up temporary view directory and view factory.
     */
    protected function setUpViewFactory(): void
    {
        // Create a temporary directory for views
        $this->tempViewPath = sys_get_temp_dir() . '/bird-flock-test-views-' . uniqid();
        mkdir($this->tempViewPath, 0777, true);

        // Set up view factory
        $this->configureViewFactory();
    }

    /**
     * Clean up temporary view directory.
     */
    protected function tearDownViewFactory(): void
    {
        // Clean up temporary views
        if (isset($this->tempViewPath) && is_dir($this->tempViewPath)) {
            $files = glob($this->tempViewPath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempViewPath);
        }
    }

    /**
     * Configure the view factory with temporary view path.
     */
    private function configureViewFactory(): void
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

    /**
     * Create a view file with the given name and content.
     *
     * @param string $name    View name (without .php extension)
     * @param string $content View content
     */
    protected function createView(string $name, string $content): void
    {
        file_put_contents($this->tempViewPath . '/' . $name . '.php', $content);
    }
}
