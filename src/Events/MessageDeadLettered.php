<?php

/**
 * Event dispatched when a message is moved to the dead-letter queue.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Events
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Events;

final class MessageDeadLettered
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $channel,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null
    ) {
    }
}
