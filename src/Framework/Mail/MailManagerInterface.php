<?php

declare(strict_types=1);

namespace Toporia\Framework\Mail;

/**
 * Mail Manager Interface
 *
 * Manages multiple mail drivers and provides driver switching.
 * Extends MailerInterface so it can be used as a mailer itself.
 */
interface MailManagerInterface extends MailerInterface
{
    /**
     * Get a mailer driver instance.
     *
     * @param string|null $driver Driver name (null = default).
     * @return MailerInterface
     */
    public function driver(?string $driver = null): MailerInterface;

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver(): string;
}
