<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;
use Toporia\Framework\Schedule\Scheduler;
use Toporia\Framework\Schedule\ScheduledTask;
use Toporia\Framework\Queue\Contracts\JobInterface;

/**
 * Schedule Service Accessor
 *
 * Provides static-like access to the task scheduler.
 *
 * @method static ScheduledTask call(callable $callback, array $parameters = []) Schedule callback
 * @method static ScheduledTask exec(string $command) Schedule shell command
 * @method static ScheduledTask job(JobInterface $job, string $queue = 'default') Schedule queue job
 * @method static void runDueTasks() Run all due tasks
 * @method static array getTasks() Get all scheduled tasks
 *
 * @see Scheduler
 *
 * @example
 * // Schedule callback
 * Schedule::call(function() {
 *     // Cleanup old files
 * })->daily();
 *
 * // Schedule command
 * Schedule::exec('php artisan cache:clear')->everyMinute();
 *
 * // Schedule job
 * Schedule::job(new SendNewsletterJob())->weekly();
 */
final class Schedule extends ServiceAccessor
{
    protected static function getServiceName(): string
    {
        return 'schedule';
    }
}
