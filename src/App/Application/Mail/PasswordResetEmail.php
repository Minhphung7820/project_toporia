<?php

declare(strict_types=1);

namespace App\Application\Mail;

use Toporia\Framework\Mail\Mailable;

/**
 * Password Reset Email
 *
 * Sent when user requests password reset.
 */
final class PasswordResetEmail extends Mailable
{
    /**
     * @param string $userEmail User's email.
     * @param string $resetToken Reset token.
     * @param string $resetUrl Reset URL.
     */
    public function __construct(
        private string $userEmail,
        private string $resetToken,
        private string $resetUrl
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
            ->subject('Reset Your Password')
            ->view('password-reset', [
                'resetUrl' => $this->resetUrl,
                'resetToken' => $this->resetToken,
            ]);
    }
}
