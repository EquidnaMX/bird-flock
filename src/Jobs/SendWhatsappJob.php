<?php

/**
 * Job for sending WhatsApp messages.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Jobs
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Jobs;

/**
 * Sends WhatsApp messages via Twilio.
 */
final class SendWhatsappJob extends AbstractSendJob
{
    /**
     * Returns the channel name for this job.
     *
     * @return non-empty-string Channel identifier.
     */
    protected function getChannel(): string
    {
        return 'whatsapp';
    }
}
