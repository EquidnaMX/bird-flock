<?php

/**
 * Message sender contract for channel-specific implementations.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Contracts
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Contracts;

use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\BirdFlock\DTO\ProviderSendResult;

/**
 * Defines the contract for sending messages through various channels.
 */
interface MessageSenderInterface
{
    /**
     * Send a message through the channel.
     *
     * @param FlightPlan $payload Message data to send
     *
     * @return ProviderSendResult Result of the send operation
     */
    public function send(FlightPlan $payload): ProviderSendResult;
}
