<?php

declare(strict_types=1);

namespace App\Application\Mail;

use Toporia\Framework\Mail\Mailable;

/**
 * Welcome Email
 *
 * Sent to new users when they register.
 */
final class WelcomeEmail extends Mailable
{
    /**
     * @param string $userEmail User's email address.
     * @param string $userName User's name.
     */
    public function __construct(
        private string $userEmail,
        private string $userName
    ) {}

    /**
     * Build the message.
     *
     * @return void
     */
    public function buildMessage(): void
    {
        $this->from(config('mail.from.address'), config('mail.from.name'))
            ->to($this->userEmail)
            ->subject('Welcome to ' . config('app.name', 'Our Platform') . '!')
            ->view('welcome', [
                'name' => $this->userName,
            ]);
    }
}
