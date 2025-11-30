<?php

/**
 * Event dispatched when a message send attempt is finalized.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Events
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Events;

use Equidna\BirdFlock\DTO\ProviderSendResult;

final class MessageFinalized
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $channel,
        public readonly ProviderSendResult $result
    ) {
    }
}
