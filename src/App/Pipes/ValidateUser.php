<?php

declare(strict_types=1);

namespace App\Pipes;

use Closure;
use Toporia\Framework\Pipeline\Contracts\PipeInterface;

/**
 * Example pipe: Validate user data.
 *
 * Demonstrates pipe class implementing PipeInterface.
 */
final class ValidateUser implements PipeInterface
{
    /**
     * Handle the user through the pipeline.
     *
     * @param mixed $user User object
     * @param Closure $next Next pipe
     * @return mixed
     * @throws \InvalidArgumentException If validation fails
     */
    public function handle(mixed $user, Closure $next): mixed
    {
        // Validate user data
        if (!isset($user->email) || !filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address');
        }

        if (!isset($user->name) || strlen($user->name) < 3) {
            throw new \InvalidArgumentException('Name must be at least 3 characters');
        }

        // Pass to next pipe
        return $next($user);
    }
}
