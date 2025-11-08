<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;
use Toporia\Framework\Mail\MailManagerInterface;
use Toporia\Framework\Mail\MailerInterface;
use Toporia\Framework\Mail\MessageInterface;
use Toporia\Framework\Mail\Mailable;

/**
 * Mail Accessor
 *
 * Provides static-like access to the mail system.
 *
 * @method static MailerInterface driver(?string $driver = null) Get mail driver
 * @method static bool send(MessageInterface $message) Send email
 * @method static bool sendMailable(Mailable $mailable) Send mailable
 * @method static bool queue(MessageInterface $message, int $delay = 0) Queue email
 * @method static bool queueMailable(Mailable $mailable, int $delay = 0) Queue mailable
 */
final class Mail extends ServiceAccessor
{
    /**
     * Get the service identifier.
     *
     * @return string
     */
    protected static function getServiceIdentifier(): string
    {
        return MailManagerInterface::class;
    }
}
