<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue\Exceptions;

/**
 * Job Already Running Exception
 *
 * Thrown when attempting to execute a job that's already running.
 * Used by WithoutOverlapping middleware.
 *
 * @package Toporia\Framework\Queue\Exceptions
 */
class JobAlreadyRunningException extends \RuntimeException
{
}
