<?php

/**
 * Job for sending email messages.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Jobs
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Jobs;

/**
 * Sends email messages via SendGrid.
 */
final class SendEmailJob extends AbstractSendJob
{
    /**
     * Returns the channel name for this job.
     *
     * @return non-empty-string Channel identifier.
     */
    protected function getChannel(): string
    {
        return 'email';
    }
}
