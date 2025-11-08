<?php

declare(strict_types=1);

namespace Toporia\Framework\Mail;

use Toporia\Framework\Queue\QueueInterface;

/**
 * Mail Manager
 *
 * Manages multiple mail drivers with lazy loading.
 * Follows Strategy pattern for driver selection.
 */
final class MailManager implements MailManagerInterface
{
    /**
     * @var array<string, MailerInterface> Resolved driver instances.
     */
    private array $drivers = [];

    /**
     * @param array $config Mail configuration.
     * @param QueueInterface|null $queue Queue instance for async sending.
     */
    public function __construct(
        private array $config,
        private ?QueueInterface $queue = null
    ) {}

    /**
     * {@inheritdoc}
     */
    public function driver(?string $driver = null): MailerInterface
    {
        $driver = $driver ?? $this->getDefaultDriver();

        // Return cached driver if exists
        if (isset($this->drivers[$driver])) {
            return $this->drivers[$driver];
        }

        // Create and cache driver
        $this->drivers[$driver] = $this->createDriver($driver);

        return $this->drivers[$driver];
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultDriver(): string
    {
        return $this->config['default'] ?? 'smtp';
    }

    /**
     * {@inheritdoc}
     */
    public function send(MessageInterface $message): bool
    {
        return $this->driver()->send($message);
    }

    /**
     * {@inheritdoc}
     */
    public function sendMailable(Mailable $mailable): bool
    {
        return $this->driver()->sendMailable($mailable);
    }

    /**
     * {@inheritdoc}
     */
    public function queue(MessageInterface $message, int $delay = 0): bool
    {
        return $this->driver()->queue($message, $delay);
    }

    /**
     * {@inheritdoc}
     */
    public function queueMailable(Mailable $mailable, int $delay = 0): bool
    {
        return $this->driver()->queueMailable($mailable, $delay);
    }

    /**
     * Create a driver instance.
     *
     * @param string $driver Driver name.
     * @return MailerInterface
     * @throws \InvalidArgumentException
     */
    private function createDriver(string $driver): MailerInterface
    {
        $mailers = $this->config['mailers'] ?? [];

        if (!isset($mailers[$driver])) {
            throw new \InvalidArgumentException("Mail driver [{$driver}] is not configured.");
        }

        $config = $mailers[$driver];
        $transport = $config['transport'] ?? $driver;

        return match ($transport) {
            'smtp' => new SmtpMailer($config, $this->queue),
            'log' => new LogMailer($config['path'] ?? ''),
            'array' => new ArrayMailer(),
            default => throw new \InvalidArgumentException("Unsupported mail transport [{$transport}]"),
        };
    }
}
