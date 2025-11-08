<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Mail\MailerInterface;
use Toporia\Framework\Mail\MailManager;
use Toporia\Framework\Mail\MailManagerInterface;
use Toporia\Framework\Queue\QueueInterface;

/**
 * Mail Service Provider
 *
 * Registers mail services into the container with multi-driver support.
 */
final class MailServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Register MailManager (manages multiple drivers)
        $container->singleton(MailManager::class, function ($c) {
            $config = $c->get('config')->get('mail', []);

            // Get queue if available
            $queue = null;
            if ($c->has(QueueInterface::class)) {
                $queue = $c->get(QueueInterface::class);
            }

            return new MailManager($config, $queue);
        });

        // Bind MailManagerInterface
        $container->bind(MailManagerInterface::class, fn($c) => $c->get(MailManager::class));

        // Bind MailerInterface (uses default driver)
        $container->bind(MailerInterface::class, fn($c) => $c->get(MailManager::class));

        // Bind 'mailer' alias
        $container->bind('mailer', fn($c) => $c->get(MailManager::class));

        // Bind 'mail' alias
        $container->bind('mail', fn($c) => $c->get(MailManager::class));
    }

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        // Nothing to boot
    }
}
