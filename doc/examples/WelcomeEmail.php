<?php

/**
 * Example Mailable for Bird Flock documentation.
 *
 * This is a sample Laravel Mailable that can be used with Bird Flock.
 * Place this file in your app/Mail directory.
 *
 * PHP 8.1+
 *
 * @package   App\Mail
 */

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Welcome email sent to new users.
 */
class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param string $userName        User's name
     * @param string $activationLink  Account activation link
     */
    public function __construct(
        public readonly string $userName,
        public readonly string $activationLink,
    ) {}

    /**
     * Build the message.
     *
     * @return self
     */
    public function build()
    {
        return $this->view('emails.welcome')
            ->text('emails.welcome-text')
            ->subject('Welcome to Bird Flock!')
            ->with([
                'userName' => $this->userName,
                'activationLink' => $this->activationLink,
            ]);
    }
}
