<?php

declare(strict_types=1);

namespace App\Pipes;

use Closure;
use Toporia\Framework\Pipeline\Contracts\PipeInterface;

/**
 * Example pipe: Enrich user data with additional information.
 *
 * Demonstrates pipe with dependencies via constructor injection + PipeInterface.
 */
final class EnrichUserData implements PipeInterface
{
    /**
     * @param array $config Configuration data (injected by container)
     */
    public function __construct(
        private array $config = []
    ) {}

    /**
     * Handle the user through the pipeline.
     *
     * @param mixed $user User object
     * @param Closure $next Next pipe
     * @return mixed
     */
    public function handle(mixed $user, Closure $next): mixed
    {
        // Add timestamps
        $user->created_at = $user->created_at ?? time();
        $user->updated_at = time();

        // Add default role
        $user->role = $user->role ?? 'user';

        // Add verified flag
        $user->verified = $user->verified ?? false;

        // Pass to next pipe
        return $next($user);
    }

    /**
     * Alternative method name (can be called with ->via('process'))
     *
     * @param mixed $user User object
     * @param Closure $next Next pipe
     * @return mixed
     */
    public function process(mixed $user, Closure $next): mixed
    {
        return $this->handle($user, $next);
    }
}
