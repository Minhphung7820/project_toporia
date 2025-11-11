<?php

declare(strict_types=1);

namespace App\Pipes;

use Closure;
use Toporia\Framework\Pipeline\Contracts\PipeInterface;

/**
 * Example pipe: Normalize user data.
 *
 * Demonstrates transformation pipe implementing PipeInterface.
 */
final class NormalizeData implements PipeInterface
{
    /**
     * Handle the user through the pipeline.
     *
     * @param mixed $user User object
     * @param Closure $next Next pipe
     * @return mixed
     */
    public function handle(mixed $user, Closure $next): mixed
    {
        // Normalize email to lowercase
        if (isset($user->email)) {
            $user->email = strtolower(trim($user->email));
        }

        // Normalize name (trim and capitalize)
        if (isset($user->name)) {
            $user->name = ucwords(strtolower(trim($user->name)));
        }

        // Pass to next pipe
        return $next($user);
    }
}
