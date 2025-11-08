<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue;

/**
 * Queue Manager Interface
 *
 * Contract for multi-driver queue management.
 */
interface QueueManagerInterface extends QueueInterface
{
    /**
     * Get a queue driver instance
     *
     * @param string|null $driver Driver name (null = default)
     * @return QueueInterface
     */
    public function driver(?string $driver = null): QueueInterface;

    /**
     * Get default driver name
     *
     * @return string
     */
    public function getDefaultDriver(): string;
}
