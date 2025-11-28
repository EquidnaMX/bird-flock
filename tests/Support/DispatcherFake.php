<?php

/**
 * Fake event dispatcher for testing.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Support;

final class DispatcherFake implements \Illuminate\Contracts\Bus\Dispatcher
{
    public array $dispatched = [];

    public function dispatch($command)
    {
        $this->dispatched[] = $command;

        return $command;
    }

    public function dispatchSync($command, $handler = null)
    {
        return $this->dispatch($command);
    }

    public function dispatchNow($command, $handler = null)
    {
        return $this->dispatch($command);
    }

    public function hasCommandHandler($command)
    {
        return false;
    }

    public function getCommandHandler($command)
    {
        return null;
    }

    public function pipeThrough(array $pipes)
    {
        return $this;
    }

    public function map(array $map)
    {
        return $this;
    }
}
