<?php

/**
 * Factory for creating message senders and clients.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock;

use Equidna\BirdFlock\Contracts\MessageSenderInterface;
use Equidna\BirdFlock\Support\SenderResolver;

/**
 * Creates sender instances based on configuration.
 */
final class MessageFactory
{
    /**
     * Create a sender for the specified channel.
     *
     * @param string $channel Channel name (sms|whatsapp|email)
     *
     * @return MessageSenderInterface Sender instance
     *
     * @throws \InvalidArgumentException If channel is not supported
     */
    public static function createSender(string $channel): MessageSenderInterface
    {
        return app(SenderResolver::class)->resolve($channel);
    }
}
