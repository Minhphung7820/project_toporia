<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue\Contracts;

/**
 * Job Dispatcher Interface
 *
 * Contract for dispatching jobs to queues.
 * Follows Interface Segregation Principle.
 *
 * @package Toporia\Framework\Queue\Contracts
 */
interface Dispatcher
{
    /**
     * Dispatch a job to its designated queue.
     *
     * @param object $job Job instance
     * @return mixed Job ID or result
     */
    public function dispatch(object $job): mixed;

    /**
     * Dispatch a job to a specific queue.
     *
     * @param object $job Job instance
     * @param string|null $queue Queue name
     * @return mixed Job ID or result
     */
    public function dispatchToQueue(object $job, ?string $queue = null): mixed;

    /**
     * Dispatch a job after a delay.
     *
     * @param object $job Job instance
     * @param int $delay Delay in seconds
     * @param string|null $queue Queue name
     * @return mixed Job ID
     */
    public function dispatchAfter(object $job, int $delay, ?string $queue = null): mixed;

    /**
     * Dispatch a job immediately (sync).
     *
     * @param object $job Job instance
     * @return mixed Job result
     */
    public function dispatchSync(object $job): mixed;
}
